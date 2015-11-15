<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new \Slim\Slim();

$scc = new sccClient();

$app->post('/login/:username/:password', function ($username, $password) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/json');

    $response = $scc->login($username, $password);

    if ($response) {
        $status = 'success';
    } else {
        $status = 'failed';
    }

    echo json_encode(
        array(
            'status' => $status,
            'token' => $response,
        )
    );

});

$app->get('/search/:search/:token', function ($search, $token) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/json');
    $scc->setToken($token);

    try {
        $result = $scc->search($search);
    } catch (Exception $e) {
        die(
            json_encode(
                array(
                    'status' => 'failed',
                    'result' => $e->getMessage(),
                )
            )
        );
    }

    echo json_encode(
        array(
            'status' => 'success',
            'result' => $result,
        ),
        JSON_NUMERIC_CHECK
    );
});

$app->get('/download/:id/:token', function ($id, $token) use ($app, $scc) {
    $app->response->headers->set('Content-Type', 'application/x-bittorrent');
    $app->response->headers->set('Content-Disposition', 'attachment; filename="'.$id.'.torrent"');

    $scc->setToken($token);

    echo $scc->downloadTorrentById($id, false);
});

$app->run();
