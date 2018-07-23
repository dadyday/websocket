<?php
namespace Socket;

class Server extends Socket {

	use EventTrait;

	var
		$host,
		$port,
		$socket,
		$aClient = [],
		$autoAccept = false,
		$autoReceive = false,
		$onAccept = [],
		$onConnect = [],
		$onDisconnect = [],
		$onReceive = [],
		$onSend = [];

	function __construct($host, $port) {
		parent::__construct();
		$this->host = $host;
		$this->port = $port;
	}

	function run() {
		$this->socket = $this->create(AF_INET, SOCK_STREAM, SOL_TCP);
		if (!$this->socket) return $this->error('socket create failed');

		$this->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
		$this->bind($this->host, $this->port);
		$this->listen(0);
		#$this->setNonblock();
	}

	function idle() {
		if ($this->autoAccept) {
			$this->autoAccept = false;
			$this->acceptClient();
			$this->autoAccept = true;
		}
		if ($this->autoReceive) {
			$this->autoReceive = false;
			$this->receiveMessage();
			$this->autoReceive = true;
		}
	}

	function waitClient($timeout = null) {
		$end = microtime(true) + $timeout;
		do {
			if ($oClient = $this->acceptClient()) return $oClient;
		}
		while (is_null($timeout) || microtime(true) < $end);
		return false;
	}

	function acceptClient() {
		$this->idle();

		$aRead = [$this->socket];
		$this->select($aRead, $aWrite, $aExcept, 0, 10);
		if (empty($aRead)) return false;

		$clSocket = $this->accept();
		$oClient = new Client($this, $clSocket);
		$oClient->setLogger($this->oLog);

		if (!$oClient->connect()) return false;
		$this->aClient[$oClient->id] = $oClient;

		return $oClient;
	}

	function removeClient($id) {
		if (!isset($this->aClient[$id])) return false;
		$oClient = $this->aClient[$id];
		unset($this->aClient[$id]);
		return $oClient;
	}

	function waitMessage($timeout = null) {
		$end = microtime(true) + $timeout;
		do {
			if ($oMessage = $this->receiveMessage()) return $oMessage;
		}
		while (is_null($timeout) || microtime(true) < $end);
		return false;
	}

	function receiveMessage() {
		$this->idle();

		foreach ($this->aClient as $oClient) {
			if ($oMessage = $oClient->receiveMessage()) return $oMessage;
		}
		return false;
	}

	function receive($pattern = null) {
		$oMessage = $this->receiveMessage();
		if (!$oMessage || $oMessage->type != 'text') {
			return false;
		}
		if (!is_null($pattern)) {
			if (!preg_match('~^([/\~#%]).*(\1)[imsxu]*$~', $pattern)) {
				$pattern = '~'.preg_quote($pattern).'~';
			}
			if (!preg_match($pattern, $oMessage->content)) {
				return false;
			};
		}
		return $oMessage->content;
	}

	function sendMessage(Message $oMessage) {
		$this->idle();

		$ok = false;
		foreach ($this->aClient as $oClient) {
			$ok |= $oClient->sendMessage($oMessage);
		}
		return $ok;
	}

	function send($message, $type = 'text') {
		$oMessage = new Message($message, $type);
		return $this->sendMessage($oMessage);
	}
}
