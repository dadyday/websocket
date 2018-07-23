<?php
require_once __DIR__.'/../vendor/autoload.php';

#$ composer req monolog/monolog
#$oLog = new Monolog\Logger(basename(__FILE__));
#$oLog->pushHandler(new \Monolog\Handler\BrowserConsoleHandler(Monolog\Logger::INFO));


$oServer = new Socket\Server('localhost', 8001);
#$oServer->setLogger($oLog);
$oServer->autoAccept = true;
$oServer->autoReceive = true;

$oServer->onReceive[] = function($oSrv, $oMsg) {
	if ($oMsg->content == 'stop') exit;
};

$oServer->run();
$oServer->waitClient(0.5);


$oServer->send('bin da');
sleep(5);

$oServer->send('bin immernoch da');
sleep(5);

$oServer->send('rate mal ...');
sleep(5);

$oServer->send('jetzt nicht mehr');
sleep(1);
//*/
