<?php
namespace Socket;


class Server extends Socket {

	var
		$host,
		$port,
		$socket,
		$aClient = [];

	function __construct($host, $port) {
		$this->host = $host;
		$this->port = $port;
	}

	function run() {
		$this->socket = $this->create(AF_INET, SOCK_STREAM, SOL_TCP);
		if (!$this->socket) return $this->error('socket create failed');

		$this->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
		$this->bind($this->host, $this->port);
		$this->listen(0);
		$this->setNonblock();
	}

	function waitClient() {
		$n = 100000;
		while ($n > 0) {
			$this->acceptClient();
			$n--;
		}
	}

	function acceptClient() {
		$aRead = [$this->socket];
		$this->select($aRead, $aWrite, $aExcept, 0, 10);
		if (!empty($aRead)) {
			$clSocket = $this->accept();
			$oClient = new Client($clSocket);
			$oClient->connect();
			print_r($oClient);
			$oClient->write('da');

			$this->aClient[$oClient->id] = $oClient;
		}
	}

	function connectClient($buffer) {
		#sleep(10);
		echo 'da';
	}

}
