<?php
namespace Socket;


class Client extends Socket {

	use EventTrait;

	static function getSecWebsocketAccept($key) {
		$salt = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
		$hash = sha1($key . $salt);
		$hash = pack('H*', $hash);
		return base64_encode($hash);
	}

	static function parseHeader($header) {
		$aTemp = explode("\r\n", $header);
		$aEntry = [array_shift($aTemp)];

		foreach ($aTemp as $entry) {
			if (!trim($entry)) continue;
			if (preg_match('~^([\w-]+):\s*(\S*)\s*$~', $entry, $aMatch)) {
				list(, $name, $value) = $aMatch;
				$aEntry[$name] = $value;
			}
		}
		return $aEntry;
	}

	static function buildHeader($aEntry) {
		$first = array_shift($aEntry);
		$ret = trim($first)."\r\n";
		foreach ($aEntry as $name => $value) {
			$ret .= "$name: ".trim($value)."\r\n";
		}
		return $ret."\r\n";
	}

	var
		$oServer,
		$id,
		$socket,
		$ip,
		$port,
		$path,
		$aHeader = [],
		$handshake = false,
		$onAccept = [],
		$onConnect = [],
		$onDisconnect = [],
		$onReceive = [],
		$onSend = [];

	function __construct(Server $oServer, $socket) {
		$this->oServer = $oServer;
		$this->id = uniqid("wsc");
		$this->socket = $socket;

		$this->onAccept = $oServer->onAccept;
		$this->onConnect = $oServer->onConnect;
		$this->onDisconnect = $oServer->onDisconnect;
		$this->onReceive = $oServer->onReceive;
		$this->onSend = $oServer->onSend;
	}

	function __destruct() {
		$this->close();
	}

	function connect() {
		$this->getpeername($this->ip, $this->port);

		$buffer = $this->read(2048);
		$this->aHeader = static::parseHeader($buffer);

		if (preg_match("~GET (.*) HTTP~i", $this->aHeader[0], $aMatch)) {
			$this->path = $aMatch[1];
		}

		if (!isset($this->aHeader['Sec-WebSocket-Key'])) return false;
		$key = $this->aHeader['Sec-WebSocket-Key'];
		$secAccept = static::getSecWebsocketAccept($key);

		$aHeader = [
			'HTTP/1.1 101 Web Socket Protocol Handshake',
			'Upgrade' => 'websocket',
			'Connection' => 'Upgrade',
			'Sec-WebSocket-Accept' => $secAccept,
		];

		if (!$this->event('accept', $this, $aHeader)) return false;

		$header = static::buildHeader($aHeader);

		#echo $header;
		$this->write($header);
		$this->handshake = true;

		if (!$this->event('connect', $this)) return false;

		return true;
	}

	function disconnect($reason = 1000) {
		if (!$this->event('disconnect', $this)) return false;

		$this->sendMessage(Message::close($reason));
		return !!$this->oServer->removeClient($this->id);
	}

	function receiveMessage() {
		$aRead = [$this->socket];
		$this->select($aRead, $aWrite, $aExcept, 0, 10);
		if (empty($aRead)) return false;

		$buffer = $this->read(2048);

		$oMessage = Message::fromBuffer($buffer);
		$oMessage->oClient = $this;

		$this->oLog->info('receive', [$oMessage->type, $oMessage->content, $oMessage->oClient->id]);
		if (!$this->event('receive', $oMessage)) return false;

		return $oMessage;
	}

	function sendMessage(Message $oMessage) {
		$oMessage->oClient = $this;
		$this->oLog->info('send', [$oMessage->type, $oMessage->content, $oMessage->oClient->id]);
		if (!$this->event('send', $oMessage)) return false;

		$buffer = $oMessage->toBuffer();
		return $this->write($buffer);
	}

}
