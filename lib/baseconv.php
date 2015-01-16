<?php
	class baseconv {
		// Encode a number with an arbitrary base string/alphabet
		public static function encode($num, $basestr) {
			if( ! function_exists('bcadd') ) {
				Throw new Exception('You need the BCmath extension.');
			}

			$base = strlen($basestr);
			$rep = '';

			while( true ){
				if( strlen($num) < 2 ) {
					if( intval($num) <= 0 ) {
						break;
					}
				}
				$rem = bcmod($num, $base);
				$rep = $basestr[intval($rem)] . $rep;
				$num = bcdiv(bcsub($num, $rem), $base);
			}
			return $rep;
		}

		// Decode a number from an arbitrary base
		public static function decode($num, $basestr) {
			if( ! function_exists('bcadd') ) {
				Throw new Exception('You need the BCmath extension.');
			}

			$base = strlen($basestr);
			$dec = '0';

			$num_arr = str_split((string)$num);
			$cnt = strlen($num);
			for($i=0; $i < $cnt; $i++) {
				$pos = strpos($basestr, $num_arr[$i]);
				if( $pos === false ) {
					Throw new Exception(sprintf('Unknown character %s at offset %d', $num_arr[$i], $i));
				}
				$dec = bcadd(bcmul($dec, $base), $pos);
			}
			return $dec;
		}

		// Decimal/hexadecimal conversion
		public static function hex_dec($num) {
			return self::decode(strtoupper($num), '0123456789ABCDEF');
		}
		
		public static function dec_hex($num) {
			return self::encode($num, '0123456789ABCDEF');
		}
		
		// Base 58 to decimal
		public static function dec_base58($num) {   
			return self::encode($num, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
		}
		public static function base58_dec($num) {
			return self::decode($num, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
		}
		
		// Base 58 to hexadecimal
		public static function hex_base58($num){
			return self::dec_base58(self::hex_dec($num));
		}
		
		public static function base58_hex($num){
			return self::dec_hex(self::base58_dec($num));
		}
	}