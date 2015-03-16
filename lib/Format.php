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
	}