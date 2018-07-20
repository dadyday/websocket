<?php
namespace Socket;

class Socket {

	var
		$socket;

	function __call($name, $args) {
		$func = preg_replace('~([A-Z])~', '_$1', $name);
		$func = 'socket_'.strtolower($func);
		if (!\function_exists($func)) throw new \BadMethodCallException("method $name not exists");

		if (!in_array($name, ['create', 'select'])) array_unshift($args, $this->socket);

		$result = call_user_func_array($func, $args);
		return $this->handleResult($name, $result);
	}

	protected function handleResult($name, $result) {
		if ($result === false) {
			$errorcode = socket_last_error($this->socket);
    		$errormsg = socket_strerror($errorcode);
			return $this->error("socket $name failed: $errormsg", $errorcode);
		}
		return $result;
	}

	function select(&$aRead, &$aWrite = null, &$aExcept = null, $tvSec = 0, $tvUsec = 10) {
		$result = @socket_select($aRead, $aWrite, $aExcept, $tvSec, $tvUsec);
		return $this->handleResult('select', $result);
	}

	function write($message, $length = null) {
		if (is_null($length)) $length = strlen($message);
        $bytes = @socket_write($this->socket, $message, $length);
		return $this->handleResult('select', $bytes);
    }

	function error($message) {
		throw new \Exception($message);
	}
}
