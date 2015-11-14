<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new \Slim\Slim();

$scc = new sccClient();

function checkCookie() {
	if (!file_exists(__DIR__ . "/cookies.json")) {
		die(
			json_encode(
				array(
					'error' => 'You must first use the [POST] /login/:username/:password endpoint to login.'
				)
			)
		);
	}
}

$app->post('/login/:username/:password', function ($username, $password) use($app, $scc) {
	$app->response->headers->set('Content-Type', 'application/json');

	if (true === $scc->login($username, $password)) {
		// todo: stuff
	}
});

$app->get('/search/:search', 'checkCookie', function ($search) use($app, $scc) {
	$app->response->headers->set('Content-Type', 'application/json');

	try {
		$result = $scc->search($search);
	} catch (Exception $e) {
		die(
			json_encode(
	    		array(
		    		'status' => 'fail', 
	    			'result' => $e->getMessage()
	    		)
	    	)
	    );
	}

    echo json_encode(
    	array(
	    	'status' => 'success', 
    		'result' => $result
    	)
    );
});

$app->get('/download/:id', 'checkCookie', function($id) use ($app, $scc) {	
	//$app->response->headers->set('Content-Type', 'application/x-bittorrent');

	$torrentData = $scc->downloadTorrentById($id, __DIR__);
	echo base64_decode($torrentData);
});

$app->run();