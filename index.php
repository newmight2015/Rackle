<?php
	define('CONFIG_PATH', __DIR__ . "/../rackle.ini");
	
	// Register project and composer autoloaders
	require 'autoload.php';
	require 'vendor/autoload.php';
	
	// Useful functions I hope to refactor out some time
	require 'lib/functions.php';
	
	// Initialize basic site variables
	$data = array("messages" => array());
	$pagedata = array();
	$viewdata = array();
	
	$defaultPage = "/blockchain";
	
	// Get requested path
	if(filter_has_var(INPUT_GET, 'path')) {
		$path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_STRING);
	}
	
	if($path === '/') {
		$path = $defaultPage;
	}
	
	// Return an error message if the page doesn't exist
	$rpath = explode('/', $path);
	$validpages = array("api", "blockchain", "faucet");
	if(!in_array($rpath[1], $validpages)) {
		error_log("Invalid pagename: " . $rpath[1]);
		addMessage('error', 'The page you requested could not be found.');
		$path = $defaultPage;
		$rpath = explode('/', $path);
	}
	
	// Don't load Mustache and controllers for API
	if($rpath[1] === 'api') {
		$apicalls = array('/api/blockchain/search');
		if(in_array($path, $apicalls)) { // Include requested API page
			require __DIR__ . $path . '.php';
		} else { // Include JSON 404 page
			require __DIR__ . '/api/404.php';
		}
	} else {
		// Helper variable for Mustache
		$global = array(
			"curpath" => $path
		);
		
		// Initialize templating engine
		$m = new Mustache_Engine(array(
			'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . "/views"),
			'partials_loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . "/views/partials"),
			'helpers' => array('global' => $global)
		));
		
		// Populate the menu
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
			
		// Include the file with info about the page and all the data needed by the template
		require "controllers/" . $rpath[1] . ".php";
		
		// Replace javascript/css links with tags (Avoids inserting tags if variable isn't set)
		$data['javascript'] = (isset($data['javascript']) ? "<script src='" . $data['javascript'] . "'></script>" : "");
		$data['stylesheet'] = (isset($data['stylesheet']) ? "<link rel='stylesheet' href='" . $data['stylesheet'] . "' />" : "");
		
		// Render the specific page content
		$data['content'] = $m->render($rpath[1], $pagedata);
		
		// Render the main template and print it
		echo $m->render('index', $data);
	}