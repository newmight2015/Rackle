<?php
	class Database {
		private static $instance = null;
		protected $handle = null;
		
		protected function __construct() {
			$config = Configuration::get();
			$dsn = "mysql:host=" . $config['db']['host'] . ";dbname=" . $config['db']['abe'];
			$this->handle = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
		}
		
		public static function getInstance() {
			global $config;
			if($instance == null) {
				self::$instance = new Database();
			}
			
			return self::$instance;
		}
		
		public static function getHandle() {
			$instance = self::getInstance();
			return $instance->handle;
		}
	}