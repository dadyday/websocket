<?php
require_once __DIR__.'/../vendor/autoload.php';

$oServer = new Socket\Server('localhost', 8001);
$oServer->run();

$oServer->onAccept[] = function($oSrv, $oCl) {
	#return false;
};
$oServer->onConnect[] = function($oSrv, $oCl) {
	echo "$oCl->id connect: $oCl->ip:$oCl->port\n";
};
$oServer->onReceive[] = function($oSrv, $oMsg) {
	echo "{$oMsg->oClient->id} receive: $oMsg->type: $oMsg->content\n";
};
$oServer->onSend[] = function($oSrv, $oMsg) {
	echo "{$oMsg->oClient->id} send: $oMsg->type: $oMsg->content\n";
};

$oCl = $oServer->waitClient(2);
$oServer->autoAccept = true;

while (true) {
	$oMsg = $oServer->waitMessage(10);
	if (!$oMsg) continue;

	$id = $oMsg->oClient->id;
	switch ($oMsg->type) {
		case 'text':
			echo "$id: $oMsg->content\n";
			if ($oMsg->content == 'close') {
				$oMsg->oClient->disconnect();
			}
			else {
				$oMsg->content = 'echoing: '.$oMsg->content;
				$oMsg->oClient->sendMessage($oMsg);
			}
			break;
		case 'close':
			$oMsg->oClient->disconnect();
			echo "$id close: $oMsg->content\n";
			break 2;
	}
};
