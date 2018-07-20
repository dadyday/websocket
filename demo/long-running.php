<?php
require_once __DIR__.'/../vendor/autoload.php';

header('Content-type: text/plain; charset=utf-8');

$oStateServer = new Socket\StateServer('localhost', 8001);
$oStateServer->setEnableLogging(true);
$oStateServer->send('bin da');
sleep(5);

if ($oStateServer->receive('stop')) exit;
$oStateServer->send('bin immernoch da');
sleep(5);

if ($oStateServer->receive('stop')) exit;
$oStateServer->send('rate mal ...');
sleep(5);

$oStateServer->send('jetzt nicht mehr');
