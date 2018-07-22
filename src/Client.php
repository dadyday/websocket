<?php
namespace Socket;


class Client extends Socket {

    static function getSecWebsocketAccept($key) {
        $salt = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $hash = sha1($key . $salt);
        $hash = pack('H*', $hash);
        return base64_encode($hash);
    }

    var
        $oServer,
        $id,
        $socket,
        $ip,
        $port,
        $handshake = false;

    function __construct(Server $oServer, $socket) {
        $this->oServer = $oServer;
        $this->id = uniqid("wsc");
        $this->socket = $socket;
        $this->getpeername($this->ip, $this->port);
    }

    function __destruct() {
        $this->close();
    }

    function connect() {
        $buffer = $this->read(2048);
        #echo $buffer;

        if (preg_match("~GET (.*) HTTP~i", $buffer, $aMatch)) {
            $this->path = $aMatch[1];
        }
        if (!preg_match("~^Sec-WebSocket-Key:\s*(\S*)\s*$~im", $buffer, $aMatch)) {
            return false;
        }
        $key = ($aMatch[1]);
        $secAccept = static::getSecWebsocketAccept($key);

        $header =
            "HTTP/1.1 101 Web Socket Protocol Handshake\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".
            "Sec-WebSocket-Accept: $secAccept\r\n\r\n";
        #echo $header;
        $this->write($header);
        $this->handshake = true;

        return true;
    }

    function disconnect($reason = 1000) {
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

        return $oMessage;
    }

    function sendMessage(Message $oMessage) {
        $buffer = $oMessage->toBuffer();
        return $this->write($buffer);
    }

}
