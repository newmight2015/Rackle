<?php
	$data['title'] = "Blockchain Explorer";
	$data['stylesheet'] = "/css/blockchain.css";
	$data['javascript'] = "/js/blockchain.js";

	// Instantiate ABE
	$abe = ABE::getInstance();
	
	// Avoid "undefined index" notice if no view is specified
	if(!isset($rpath[2])) {
		$rpath[2] = "index";
	}
	
	// Load the proper view
	switch($rpath[2]){
		case "index":
		case "address":
		case "transaction":
		case "block":
			require "controllers/blockchain/" . $rpath[2] . ".php";
			break;
		default:
			addMessage("error", "The specified term does not look like a transaction, block or address.");
			require "controllers/blockchain/index.php";
			break;
	}