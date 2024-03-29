<?php
	class Format {
		// Takes am amount from the database and formats it for viewing
		// by dividing by 10^8 and formatting the number
		public static function amount($amt) {
			return number_format($amt / pow(10,8), 8);
		}
		
		// Used to turn a hash, height or address into a link
		public static function link($type, $item, $caption = null) {
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
		
		public static function inputs($inputs) {
			foreach($inputs as &$input) {
				$input['address'] = Format::link(Type::ADDRESS, $input['address']);
				$input['amount'] = Format::amount($input['amount']);
			}
			return $inputs;
		}
		
		public static function transaction($tx) {
			$tx['time'] = date("Y-m-d H:i:s", $tx['time']);
			$tx['size'] = number_format($tx['size']);
			$tx['block'] = Format::link(Type::BLOCK, $tx['block']);
			// $tx['confSVG'] = SVG::percentageCircle(min($tx['confirmations'], 4), 4, 54); // Calculate SVG path for confirmation arc/circle
			// $tx['confHideZero'] = ($tx['confirmations'] == 0 ? "" : "display: none;");
			
			$tx['inputs'] = Format::inputs($tx['inputs']);
			$tx['outputs'] = Format::inputs($tx['outputs']);
				
			if(empty($tx['inputs'])) {
				$tx['inputs'] = array("address" => "Coinbase (Mining)", "amount" => $tx['outputs'][0]['amount']);
			}
			
			return $tx;
		}
		
		public static function block($block) {
			foreach($block['transactions'] as &$transaction) {
				$transaction['hash'] = Format::link(Type::TRANSACTION, $transaction['hash']);
				$transaction['amount'] = Format::amount($transaction['amount']);
			}
			return $block;
		}
	}