<?php
	class Configuration {
		private static $config = null;
		
		public static function get() {
			if(self::$config === null) {
				self::$config = parse_ini_file(CONFIG_PATH, true);
			}
			
			return self::$config;
		}
	}