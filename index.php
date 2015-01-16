<?php
	require 'config.php';
	require 'vendor/autoload.php';

	// Load basic site variables and functions
	$data = array("messages" => array());
	$pagedata = array();
	$viewdata = array();
	
	$defaultPage = "blockchain";
	
	$curpath = $defaultPage;
	if(isset($_GET['page'])) $curpath = $_GET['page'];
	if(isset($_GET['view'])) $curpath .= "/" . $_GET['view'];
	if(isset($_GET['query'])) $curpath .= "/" . $_GET['query'];
	
	$global = array(
		"curpath" => $curpath
	);
	
	require 'lib/functions.php';
	
	if(isset($_GET['start'])){
		$paginate['start'] = filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT);
	}
	
	// Initialize templating engine
	$m = new Mustache_Engine(array(
		'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . "/views"),
		'partials_loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . "/views/partials"),
		'helpers' => array('global' => $global)
	));
	
	// Valid pages and information about them - might eventually be SQL-based
	$validpages = array("blockchain", "faucet", "pool");
	$data['menuitems'] = array(
		array(
			"title" => "Blockchain Explorer",
			"link" => "/blockchain", 
			"icon" => "boxbilling"
		),
		array(
			"title" => "Faucet", 
			"link" => "/faucet",
			"icon" => "watertap-plumbing"
		),
		array(
			"title" => "Pool", 
			"link" => "http://pool.omnicoin.cc", 
			"icon" => "cpu-processor"
		)
	);

	// Prepare array for error messages
	//add_message("error", "Somehow a very mysterious test error has occurred. It is rather lengthy too.");
	
	// Set default value if page is not defined
	if(empty($_GET['page'])){
		$_GET['page'] = $defaultPage;
	}
	
	// Set the page value to load 404 page on invalid page name
	if(!in_array($_GET['page'], $validpages)){
		$_GET['page'] = "404";
	}
	
	// Include the file with info about the page and all the data needed by the template
	require "controllers/" . $_GET['page'] . ".php";
	
	// Replace javascript/css links with tags (Avoids inserting tags if variable isn't set)
	$data['javascript'] = (isset($data['javascript']) ? "<script src='" . $data['javascript'] . "'></script>" : "");
	$data['stylesheet'] = (isset($data['stylesheet']) ? "<link rel='stylesheet' href='" . $data['stylesheet'] . "' />" : "");
	
	// Render the specific page content
	$data['content'] = $m->render($_GET['page'], $pagedata);
	
	// Render the main template and print it
	echo $m->render('index', $data);