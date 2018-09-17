<?php
namespace Socket;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;



class Socket {

	protected
		$oLog;

	function setLogger(LoggerInterface $oLogger) {
		$this->oLog = $oLogger;
	}

	function getLogger() {
		return $this->oLog;
	}

	var
		$socket;

	function __construct() {
		$this->oLog = new NullLogger();
	}

	function __call($name, $args) {
		$func = preg_replace('~([A-Z])~', '_$1', $name);
		$func = 'socket_'.strtolower($func);
		if (!\function_exists($func)) throw new \BadMethodCallException("method $name not exists");

		$refArgs = [];
		if (!in_array($name, ['create', 'select'])) $refArgs[] = $this->socket;

		foreach ($args as &$arg) {
			$refArgs[] = &$arg;
		}
		$this->oLog->debug("call $func", $refArgs);
		$result = call_user_func_array($func, $refArgs);
		return $this->handleResult($name, $result);
	}

	function getpeername(&$ip = null, &$port = null) {
		$this->oLog->debug("call socket_getpeername", []);
		$result = @socket_getpeername($this->socket, $ip, $port);
		return $this->handleResult('getpeername', $result);
	}

	function select(&$aRead, &$aWrite = null, &$aExcept = null, $tvSec = 0, $tvUsec = 10) {
		$this->oLog->debug("call socket_select", [$aRead, $aWrite, $aExcept]);
		$result = @socket_select($aRead, $aWrite, $aExcept, $tvSec, $tvUsec);
		return $this->handleResult('select', $result);
	}

	function write($message, $length = null) {
		if (is_null($length)) $length = strlen($message);
		$this->oLog->debug("call socket_write", [$message, $length]);
		$bytes = @socket_write($this->socket, $message, $length);
		if ($bytes != $length) $this->error("write of $length bytes failed: $bytes");
		return $this->handleResult('write', $bytes);
	}

	function close() {
		$arrOpt = array('l_onoff' => 1, 'l_linger' => 0);
    	socket_set_block($this->socket);
    	socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, $arrOpt);
    	socket_close($this->socket);
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
		$this->oLog->error("$message");
		throw new \Exception($message);
	}
}
