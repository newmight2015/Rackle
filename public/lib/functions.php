<?php
	abstract class Type {
		const ADDRESS = 0;
		const BLOCK = 1;
		const TRANSACTION = 2;
	}

	function addMessage($type, $msg) {
		global $data;
		array_push($data['messages'], array(
			"type" => $type,
			"message" => $msg
		));
	}