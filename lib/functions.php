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
	
	function createLink($type, $item, $caption = null) {
		if($caption === null) {
			$caption = $item;
		}
		
		switch($type) {
			case Type::ADDRESS:
				$link = "/blockchain/address";
				break;
			case Type::BLOCK:
				$link = "/blockchain/block";
				break;
			case Type::TRANSACTION:
				$link = "/blockchain/transaction";
				break;
			default:
				$link = "";
				break;
		}

		$link .= "/$item";
		$tag = "<a href='$link'>$caption</a>";
		return $tag;
	}