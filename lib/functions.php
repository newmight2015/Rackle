<?php
	abstract class Type {
		const Address = 0;
		const Block = 1;
		const Transaction = 2;
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
			case Type::Address:
				$link = "/blockchain/address";
				break;
			case Type::Block:
				$link = "/blockchain/block";
				break;
			case Type::Transaction:
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