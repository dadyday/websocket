<?php
namespace Socket;

trait EventTrait {

	function event($type, ...$aArg) {
		$prop = 'on'.ucfirst($type);
		array_unshift($aArg, $this);
		foreach ($this->$prop as $listener) {
			if (!is_callable($listener)) throw new \InvalidArgumentException("$prop must contain callables");
			$ret = call_user_func_array($listener, $aArg);
			if ($ret === false) return false;
		}
		return true;
	}
}
