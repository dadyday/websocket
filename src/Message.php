<?php
namespace Socket;

class Message {

	static function close($statusCode) {
		$code = pack('n', $statusCode);
		return new static($code, 'close');
	}

	static function text($content) {
		return new static($content, 'text');
	}

	static function fromBuffer($buffer) {
		$data = Hybi10::decode($buffer);
		return new static($data['payload'], $data['type']);
	}

	var
		$type,
		$content,
		$oClient;

	function __construct($content, $type = 'text') {
		$this->type = $type;
		$this->content = $content;
	}

	function toBuffer() {
		return Hybi10::encode($this->content, $this->type);
	}
}
