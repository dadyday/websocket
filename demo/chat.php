<?php
require_once __DIR__.'/../vendor/autoload.php';

$oServer = new Socket\Server('localhost', 8001);
$oServer->run();
$oServer->waitClient();
