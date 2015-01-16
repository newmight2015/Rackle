<?php
	$config = array(
		'db' => array( // Settings for the database ABE runs on
			'host' => 'localhost',
			'port' => 3306,
			'name' => 'abe',
			'user' => 'changeme',
			'pass' => 'changeme'
		),
		'coin' => array( // Settings for your coin
			'chain_id' => 0, // ABEs internal ID for your coin's chain (Run "SELECT * FROM chain" to find it)
			'addr_version' => "00" // Hexadecimal representation of the coins version byte
		)
	);