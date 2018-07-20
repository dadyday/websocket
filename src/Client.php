<?php
namespace Socket;


class Client extends Socket {

	var
		$id,
		$socket,
		$ip,
		$port,
		$handshake = false;

	function __construct($socket) {
		$this->id = uniqid("wsc");
		$this->socket = $socket;
		socket_getpeername($socket, $this->ip, $this->port);
	}

	function connect() {
		$buffer = $this->read(2048);
		echo $buffer;

		if (preg_match("~GET (.*) HTTP~i", $buffer, $aMatch)) {
			$this->path = $aMatch[1];
		}
		if (!preg_match("~^Sec-WebSocket-Key: (.*)$~im", $buffer, $aMatch)) {
			return false;
		}

		$key = $aMatch[1];
		$secAccept = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

		$header =
			"HTTP/1.1 101 Web Socket Protocol Handshake\r\n".
			"Upgrade: websocket\r\n".
			"Connection: Upgrade\r\n".
			"Sec-WebSocket-Accept: $secAccept\r\n\r\n";
		echo $header;
		echo $this->write($header);
		$this->handshake = true;

		return true;
	}

}
