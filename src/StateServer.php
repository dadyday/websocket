<?php
namespace Socket;

use \HemiFrame\Lib\WebSocket\Client;

class StateServer extends WebSocket {

	protected
		$sending = [],
		$received = [];

	function __construct($host, $port) {
		parent::__construct($host, $port);

		$this->create();
		$this->setOption($this->getSocket(), SOL_SOCKET, SO_REUSEADDR, 1);
		$this->bind($host, $port);
		$this->listen();

		$this->on("receive", function (Client $client, $data) {
			$this->log("received $data");
			foreach ($this->getClientsByPath($client->getPath()) as $item) {
				array_push($this->received, $data);
			}
		});

		$this->handle();
	}

	function log($message, Client $client = null) {
		if ($this->enableLogging) {
			$date = \DateTime::createFromFormat('U.u', microtime(true));
			$clientId = !empty($client) ? $client->getId().' => ' : null;
			echo $date->format("Y-m-d H:i:s.u") . ": " . $clientId . $message . "\n";
		}
	}

	function send($message) {
		$this->log("send $message");
		array_push($this->sending, $message);
		$this->handle();
	}

	function receive($await = null) {
		$this->handle();
		if ($await) {
			$p = array_search($await, $this->received);
			$message = null;
			if ($p !== false) {
				array_splice($this->received, $p, 1);
				$message = $await;
			}
		}
		else {
			$message = empty($this->received) ? null : array_shift($this->received);
		}

		$this->log("receive $message");
		return $message;
	}

	public function loop() {
		$socket = $this->getSocket();
		while (is_resource($socket)) {
			$this->handle();
		}
	}

	protected function getReadableClients() {
		$aRead = [$this->socket];
		$aWrite = [];
		$aExcept = [];
		foreach ($this->clients as $client) {
			$aRead[] = $client->getSocket();
		}
		if ($this->select($aRead, $aWrite, $aExcept, 0, 10) === false) {
			$this->onError($this->socket);
		}
		return $aRead;
	}

	protected function getWritableClients() {
		$aRead = [];
		$aWrite = [];
		$aExcept = [];
		foreach ($this->clients as $client) {
			$aWrite[] = $client->getSocket();
		}
		if ($this->select($aRead, $aWrite, $aExcept, 0, 10) === false) {
			$this->onError($this->socket);
		}
		return $aWrite;
	}

	protected function readClients(array $aRead) {
		$aRet = [];
		foreach ($aRead as $socket) {
			$buffer = $this->read($socket);
			if ($this->socket == $socket) {
				$client = $this->acceptNewClient();
			}
			else {
				$client = $this->getClientBySocket($socket);

				if (empty($buffer)) {
					$this->log("Can't read data", $client);
					$this->disconnectClient($client, self::STATUS_CLOSE_PROTOCOL_ERROR);
					continue;
				}

				$data = $this->hybi10Decode($client, $buffer);

				if ($data['payload'] === false) {
					$this->log("Can't decode data", $client);
					$this->disconnectClient($client, self::STATUS_CLOSE_PROTOCOL_ERROR);
					continue;
				}

				$i = $client->getId();
				$aRet[$i] = [$client, $data];
			}
		}
		return $aRet;
	}

	protected function acceptNewClient() {
		$client = $this->createClient($this->accept());

		if (count($this->clients) >= $this->maxClients) {
			$this->log("Max clients limit reached", $client);
			$this->log("Client is disconnected", $client);
			$this->disconnectClient($client, self::STATUS_CLOSE_PROTOCOL_ERROR, "Max clients limit reached");
		}

		$this->handshake($client);
		return $client;
	}

	protected function writeClients(array $aWrite, $data) {
		foreach ($aWrite as $socket) {
			$this->writeClient($socket, $data);
		}
	}

	protected function writeClient($socket, $data) {
		$client = $this->getClientBySocket($socket);
		if (!$client) return;
		$buffer = $this->hybi10Encode($client, $data, "text");
		$this->write($socket, $buffer);
	}

	public function handle() {

		$aRead = $this->getReadableClients();
		#$this->acceptNewClients($aRead);
		$aData = $this->readClients($aRead);

		$aWrite = $this->getWritableClients();
		$this->writeClients($aWrite, 'da');

		foreach ($this->getClientsByPath('/') as $client) {
			while ($data = array_shift($this->sending)) {
				$this->log("sending $data");
				#$this->writeClient($socket, $data);
				#if ($this->checkClientExistBySocket($client->getSocket())) {
		        #    $response = $this->hybi10Encode($client, $data, "text");
		        #    $this->write($client->getSocket(), $response);
		        #}
				#$this->writeClient($client->getSocket(), $data);
			}
			break;
		}

		foreach ($aData as [$client, $data]) {

			switch ($data['type']) {
				case 'text':
					$this->log("Receive data: " . $data['payload'], $client);
					$this->trigger("receive", [
						$client,
						$data['payload'],
					]);
					break;

				case 'ping':
					$this->log("ping", $client);
					$this->write($changedSocket, $this->hybi10Encode($client, "", "pong"));
					$this->trigger("ping", [
						$client,
						$data['payload'],
					]);
					break;

				case 'pong':
					$this->log("pong", $client);
					$this->trigger("pong", [
						$client,
						$data['payload'],
					]);
					break;

				case 'close':
					if (strlen($data['payload']) >= 2) {
						$statusCode = unpack("n", substr($data['payload'], 0, 2));
						$reason = substr($data['payload'], 2);
						$this->disconnectClient($client, $statusCode[1], $reason);
					} else {
						$this->disconnectClient($client);
					}
					break;
			}
		}
	}

	function handshake($client) {
		$buf = $this->read($client->getSocket());
		if ($buf === false) {
			$this->onError($this->socket);
		}

		if (!$client->getHandshake()) {
			if ($this->processClientHandshake($client, $buf)) {
				if ($this->checkOrigin($client->getHeaders())) {
					$this->clients[] = $client;
					$this->log("connect", $client);
					$this->trigger("connect", [
						$client
					]);
				} else {
					$this->log("Invalid origin", $client);
					$this->log("Client is disconnected", $client);
					$this->disconnectClient($client, self::STATUS_CLOSE_PROTOCOL_ERROR, "Invalid origin");
				}
			} else {
				$this->log("Failed process handchake", $client);
				$this->disconnectClient($client, self::STATUS_CLOSE_PROTOCOL_ERROR, "Failed process handchake");
			}
		}
	}
}
