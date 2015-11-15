<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new \Slim\Slim();

$scc = new sccClient();

function respond($msgTrue = '', $msgFalse = '', $status = true)
{
    if ($status) {
        die(
            json_encode(
                array(
                    'status' => 'success',
                    'result' => $msgTrue
                ),
                JSON_NUMERIC_CHECK
            )
        );
    } else {
        die(
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

    $response = $scc->login($username, $password);

    respond('token: '.$response, 'something went wrong', $response);
});

$app->get('/search/:search/:token', function ($search, $token) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/json');

    $scc->setToken($token);

    $result = $scc->search($search);

    respond($result, 'something went wrong', $result);
});

$app->get('/download/:id/:token', function ($id, $token) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/x-bittorrent');
    $app->response->headers->set('Content-Disposition', 'attachment; filename="'.$id.'.torrent"');

    $scc->setToken($token);

    echo $scc->downloadTorrentById($id, false);
});

$app->post('/multidownload/:token', function ($token) use ($app, $scc) {
    $scc->setToken($token);
    
    /* Validates given json data */

    if ($jsonDecoded = json_decode($app->request->getBody(), true)) {
        if (array_key_exists('idlist', $jsonDecoded) && is_array($jsonDecoded['idlist'])) {
            foreach ($jsonDecoded['idlist'] as $id => $sccId) {
                if (is_numeric($sccId)) {
                    if (!$scc->torrentExists($sccId)) {
                        respond('', $sccId.' is unknown id, use search first', false);
                    }
                } else {
                    respond('', 'one or more ids in given list not numeric', false);
                }
            }
        } else {
            respond('', 'idlist missing from json', false);
        }
    } else {
        respond('', 'Invalid json', false);
    }

    $zipFile = tempnam("tmp", "zip");
    $zipArchive = new ZipArchive();
    $zipArchive->open($zipFile, ZipArchive::OVERWRITE);

    foreach ($jsonDecoded['idlist'] as $id => $sccId) {
        $zipArchive->addFromString($sccId.'.torrent', $scc->downloadTorrentById($sccId, false));
    }

    $zipArchive->close();

    $app->response->headers->set('Content-Type', 'application/zip');
    $app->response->headers->set('Content-Length', filesize($zipFile));
    $app->response->headers->set('Content-Disposition', 'attachment; filename="multi_'.time().'.zip"');

    readfile($zipFile);
    unlink($zipFile);
});

$app->run();
