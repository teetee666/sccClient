<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new \Slim\Slim();

$scc = new sccClient();

function respond($msgTrue = '', $msgFalse = '', $status = true)
{
    if ($status) {
        return(
            json_encode(
                array(
                    'status' => 'success',
                    'result' => $msgTrue
                ),
                JSON_NUMERIC_CHECK
            )
        );
    } else {
        return(
            json_encode(
                array(
                    'status' => 'failure',
                    'reason' => $msgFalse
                ),
                JSON_NUMERIC_CHECK
            )
        );
    }
}

$app->post('/login/:username/:password', function ($username, $password) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/json');

    $apiResponse = $scc->login($username, $password);

    try {
        return $app->response->write(respond('token: '.$apiResponse, 'something went wrong', $apiResponse));
    } catch (Exception $e) {
        return $app->response->write(respond('', $e->getMessage(), false));
    }
});

$app->get('/search/:search/:token', function ($search, $token) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/json');

    $scc->setToken($token);
    $result = $scc->search($search);

    try {
        return $app->response->write(respond($result, 'something went wrong', $result));
    } catch (Exception $e) {
        return $app->response->write(respond('', $e->getMessage(), false));
    }
});

$app->get('/download/:id/:token', function ($id, $token) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/x-bittorrent');
    $app->response->headers->set('Content-Disposition', 'attachment; filename="'.$id.'.torrent"');

    $scc->setToken($token);

    try {
        return $app->response->write($scc->downloadTorrentById($id, false));
    } catch (Exception $e) {
        return $app->response->write(respond('', $e->getMessage(), false));
    }
});

$app->post('/multidownload/:token', function ($token) use ($app, $scc) {
    $scc->setToken($token);
    
    /* Validates given json data */

    if ($jsonDecoded = json_decode($app->request->getBody(), true)) {
        if (is_array($jsonDecoded)) {
            foreach ($jsonDecoded as $id => $sccId) {
                if (is_numeric($sccId)) {
                    if (!$scc->torrentExists($sccId)) {
                        return $app->response->write(respond('', $sccId.' is unknown id, use search first', false));
                    }
                } else {
                    return $app->response->write(respond('', 'one or more ids in given list not numeric', false));
                }
            }
        } else {
            return $app->response->write(respond('', 'not valid array', false));
        }
    } else {
        return $app->response->write(respond('', 'Invalid json', false));
    }

    try {
        $zipFile = tempnam("tmp", "zip");
        $zipArchive = new ZipArchive();
        $zipArchive->open($zipFile, ZipArchive::OVERWRITE);

        foreach ($jsonDecoded as $id => $sccId) {
            $zipArchive->addFromString($sccId.'.torrent', $scc->downloadTorrentById($sccId, false));
        }

        $zipArchive->close();

        $app->response->headers->set('Content-Type', 'application/zip'); $app->response->headers->set('Content-Length', filesize($zipFile));
        $app->response->headers->set('Content-Disposition', 'attachment; filename="multi_'.time().'.zip"');

        $zipData = file_get_contents($zipFile);
        unlink($zipFile);

        return $app->response->write($zipData);
    } catch (Exception $e) {
        return $app->response->write(respond('', $e->getMessage(), false));
    }
});

$app->run();
