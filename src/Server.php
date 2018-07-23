<?php
namespace Socket;

use Nette;

class Server extends Socket {

    use EventTrait;

    var
        $host,
        $port,
        $socket,
        $aClient = [],
        $autoAccept = false,
        $onAccept = [],
        $onConnect = [];

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

    function waitClient($timeout = null) {
        $end = microtime(true) + $timeout;
        do {
            if ($oClient = $this->acceptClient()) return $oClient;
        }
        while (is_null($timeout) || microtime(true) < $end);
        return false;
    }

    function acceptClient() {
        $aRead = [$this->socket];
        $this->select($aRead, $aWrite, $aExcept, 0, 10);
        if (empty($aRead)) return false;

        $clSocket = $this->accept();
        $oClient = new Client($this, $clSocket);

        if (!$this->event('accept', $oClient)) return false;

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
        if ($this->autoAccept) $this->acceptClient();
        foreach ($this->aClient as $oClient) {
            if ($oMessage = $oClient->receiveMessage()) return $oMessage;
        }
        return false;
    }
}
