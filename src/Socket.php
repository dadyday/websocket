<?php
namespace Socket;

class Socket {

    var
        $socket;

    function __call($name, $args) {
        $func = preg_replace('~([A-Z])~', '_$1', $name);
        $func = 'socket_'.strtolower($func);
        if (!\function_exists($func)) throw new \BadMethodCallException("method $name not exists");

        $refArgs = [];
        if (!in_array($name, ['create', 'select'])) $refArgs[] = $this->socket;

        foreach ($args as &$arg) {
            $refArgs[] = &$arg;
        }
        $result = call_user_func_array($func, $refArgs);
        return $this->handleResult($name, $result);
    }

    function getpeername(&$ip = null, &$port = null) {
        $result = @socket_getpeername($this->socket, $ip, $port);
        return $this->handleResult('getpeername', $result);
    }

    function select(&$aRead, &$aWrite = null, &$aExcept = null, $tvSec = 0, $tvUsec = 10) {
        $result = @socket_select($aRead, $aWrite, $aExcept, $tvSec, $tvUsec);
        return $this->handleResult('select', $result);
    }

    function write($message, $length = null) {
        if (is_null($length)) $length = strlen($message);
        $bytes = @socket_write($this->socket, $message, $length);
        if ($bytes != $length) $this->error("write of $length bytes failed: $bytes");
        return $this->handleResult('write', $bytes);
    }

    protected function handleResult($name, $result) {
        if ($result === false) {
            $errorcode = socket_last_error($this->socket);
            $errormsg = socket_strerror($errorcode);
            return $this->error("socket $name failed: $errormsg", $errorcode);
        }
        return $result;
    }

    protected function error($message) {
        throw new \Exception($message);
    }
}
