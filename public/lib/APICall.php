<?php
	use \Monolog\Logger;
	use \Monolog\Handler\StreamHandler;

	class APICall {
		private $params;
		private $takesId;
		private $method;
		private $retval = array();
		public $logger;

		public function __construct($method, $paramlist, $takesId = false) {
			$config = Configuration::get();

			$this->logger = new Logger('APICall');
			$this->logger->pushHandler(new StreamHandler($config['log']['api'], Logger::DEBUG));

			$this->params = $paramlist;
			$this->method = $method;
			$this->takesId = $takesId;

			$tmethod = ($method === 0 ? "POST" : "GET");
			$this->logger->addDebug("Checking for missing $tmethod parameters");
			$this->checkMissingParams();
		}

		public function checkMissingParams() {
			// If the resource requires an ID, check that as well
			if($this->takesId) {
				$this->logger->addDebug("This call requires a resource ID");
				$path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_STRING);
				$rpath = explode('/', $path);
				if(!isset($rpath[4])) {
					$this->logger->addDebug("Missing identifier", array($this->method, $attr));
					$this->addError(array(
						"status" => 400,
						"code" => "ID_MISSING",
						"title" => "Missing resource identifier",
						"description" => "Please specify a resource identifier."
					));
				}
			}

			// Ensure that all parameters are specified
			foreach($this->params as $attr => $filter) {
				if(!filter_has_var($this->method, $attr)) {
					$this->logger->addDebug("Missing parameter", array($this->method, $attr));
					$this->addError(array(
						"status" => 400,
						"code" => "PARAM_MISSING",
						"title" => "Missing parameter",
						"description" => "The parameter $attr is required but none is given."
					));
				}
			}

			$this->checkErrors();
		}

		// Checks if all parameters fulfill the given validation filters
		public function validateParams($params) {
			$this->logger->addDebug("Checking for invalid parameters");
			foreach($params as $attr => $filter) {
				if(!filter_input($this->method, $attr, $filter)) {
					$this->logger->addDebug("Invalid parameter", array($this->method, $attr, $filter));
					$this->addError(array(
						"status" => 400,
						"code" => "PARAM_INVALID",
						"title" => "Invalid parameter",
						"description" => "The specified value for parameter $attr does not fulfill the requirements." // Clarify this to the user (e.g. which requirement)
					));
				}
			}

			$this->checkErrors();
		}

		// Sends parameters through sanitization filters and returns them
		public function getParams() {
			$this->logger->addDebug("Sanitizing parameters");
			$return = filter_input_array($this->method, $this->params);

			// Get the ID parameter from URL
			$this->logger->addDebug("Taking ID parameter from URL");
			$path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_STRING);
			$rpath = explode('/', $path);
			if(isset($rpath[4])) {
				$return['id'] = $rpath[4];
				$this->logger->addDebug("API resource id retrieved from path", array($return['id']));
			} else {
				$this->logger->addDebug("No ID specified");
			}

			return $return;
		}

		// Add data to the returned JSON object
		public function addData($data) {
			if(!isset($this->retval['data'])) {
				$this->retval['data'] = array();
			}

			array_push($this->retval['data'], $data);
		}

		// Adds an error to the returned JSON object
		public function addError($error) {
			if(!isset($this->retval['errors'])) {
				$this->retval['errors'] = array();
			}

			array_push($this->retval['errors'], $error);
		}

		// Adds an error to the returned JSON object and ends the call
		public function endError($error) {
			$this->addError($error);
			$this->end();
		}

		// Ends the API call if any errors have occurred
		public function checkErrors() {
			$this->logger->addDebug("Checking if errors occurred");
			if(isset($this->retval['errors'])) {
				$this->end();
			}
		}

		// Set a HTTP status code
		public function setStatus($code) {
			switch($code) {
				case 204:
					$status = "204 No Content";
					break;
				case 400:
					$status = "400 Bad Request";
					break;
				case 403:
					$status = "403 Forbidden";
					break;
				case 404:
					$status = "404 File Not Found";
					break;
			}

			$status = $_SERVER['SERVER_PROTOCOL'] . " " . $status;
			$this->logger->addDebug("Setting header", array($status));
			header($status);
		}

		// End the API call, outputting the return value and halting execution
		public function end() {
			$this->logger->addDebug("Ending API call");

			// Don't output anything if we return no data (send 204 No Content)
			if(empty($this->retval)) {
				$this->setStatus(204);
				exit();
			} else {
				exit(json_encode($this->retval));
			}
		}
	}
