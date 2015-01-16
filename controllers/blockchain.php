<?php
	$data['title'] = "Blockchain Explorer";
	$data['stylesheet'] = "/css/blockchain.css";
	$data['javascript'] = "/js/blockchain.js";
	
	// Include and initialize bitcoin-abe and the SVG helper
	require_once "lib/abe.php";
	require_once "lib/svg.php";
	
	// Avoid "undefined index" notice if no view is specified
	if(!isset($_GET['view'])) {
		$_GET['view'] = "index";
	}
	
	// Load the proper view
	switch($_GET['view']){
		case "address":
		case "transaction":
		case "block":
			require "controllers/blockchain/" . $_GET['view'] . ".php";
			break;
		default:
			require "controllers/blockchain/index.php";
			break;
	}