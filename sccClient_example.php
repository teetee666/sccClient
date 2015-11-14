<?php

require_once __DIR__.'/vendor/autoload.php';

$scc = new sccClient();
$scc->login($argv[1], $argv[2]);

$results = $scc->search('the blacklist');
foreach ($results as $result) {
    $scc->downloadTorrentById($result['sccId'], __DIR__);
}
