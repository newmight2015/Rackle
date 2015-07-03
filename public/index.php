<?php
	define('CONFIG_PATH', __DIR__ . "/../rackle.ini");

	// Register project and composer autoloaders
	require 'autoload.php';
	require 'vendor/autoload.php';

	// Initialize basic site variables
	session_start();
	$config = Configuration::get();
	$data = array("messages" => array());
	$pagedata = array();
	$viewdata = array();
	$defaultPage = "/blockchain";

	// Set up Propel ORM
	$svcContainer = \Propel\Runtime\Propel::getServiceContainer();
	$conManager = new \Propel\Runtime\Connection\ConnectionManagerSingle();

	$svcContainer->setAdapterClass($config['db']['db'], 'mysql');
	$conManager->setConfiguration(array(
		'dsn'	=> 'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['db'],
		'user'	=> $config['db']['user'],
		'password'	=> $config['db']['pass']
	));

	$svcContainer->setConnectionManager($config['db']['db'], $conManager);

	// Set up logging for Propel
	use \Monolog\Logger;
	$propelLogger = new Logger('propel');
	$propelLogger->pushHandler(new \Monolog\Handler\StreamHandler($config['log']['propel'], Logger::WARNING));
	$svcContainer->setLogger('defaultLogger', $propelLogger);

	// Useful functions I hope to refactor out some time
	require 'lib/functions.php';

	// Get current user's object
	$loggedIn = isset($_SESSION['uid']);
	if($loggedIn) {
		$currentuser = UserQuery::create()->findPk($_SESSION['uid']);
	}

	// Get requested path
	if(filter_has_var(INPUT_GET, 'path')) {
		$path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_STRING);
	} else {
		$path = $defaultPage;
	}

	if($path === '/') {
		$path = $defaultPage;
	}

	// Return an error message if the page doesn't exist
	$rpath = explode('/', $path);
	$validpages = array("api", "blockchain", "faucet", "user");
	if(!in_array($rpath[1], $validpages)) {
		header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found");
		addMessage('error', 'The page you requested could not be found.');
		$path = $defaultPage;
		$rpath = explode('/', $path);
	}

	// Don't load Mustache and controllers for API
	if($rpath[1] === 'api') {
		// Set API content type
		//header('Content-type: application/vnd.api+json');
		$spath = "/" . $rpath[1] . "/" . $rpath[2] . "/" . $rpath[3];
		$apicalls = array(
			'/api/blockchain/search',
			'/api/blockchain/transaction',
			'/api/user/register',
			'/api/user/login',
			'/api/user/sandbox'
		);

		if(in_array($spath, $apicalls)) { // Include requested API page
			require __DIR__ . $spath . '.php';
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

		// Include the file with info about the page and all the data needed by the template
		require "controllers/index.php";
		require "controllers/" . $rpath[1] . ".php";

		// Replace javascript/css links with tags (Avoids inserting tags if variable isn't set)
		$data['javascript'] = (isset($data['javascript']) ? "<script src='" . $data['javascript'] . "'></script>" : "");
		$data['stylesheet'] = (isset($data['stylesheet']) ? "<link rel='stylesheet' href='" . $data['stylesheet'] . "' />" : "");

		// Render the specific page content
		$data['content'] = $m->render($rpath[1], $pagedata);

		// Render the main template and print it
		echo $m->render('index', $data);
	}
