<?php
	// A page is referenced using a starting index (inclusive) and the amount to display
	// Can output the starting and stopping indices (Inclusive for MySQL LIMIT clause)
	class Paginator {
		private $start;
		private $amount;
		private $max;
		private $desc;
		
		// Gets the starting and stopping value directly from HTTP GET, defaults to given params if unset
		// If desc is set to true, the indices are in descending order and the given default is a stop value
		public function __construct ($defaultAmount, $max, $desc = false) {
			// Start on last page if order is descending
			if($desc){
				$defaultStart = $max - $defaultAmount + 1;
			} else {
				$defaultStart = 0;
			}
			
			// Sanitize input
			$this->start = @filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT);
			$this->start = $this->start === "" ? $defaultStart : $this->start;

			$this->amount = @filter_var($_GET['amt'], FILTER_SANITIZE_NUMBER_INT);
			$this->amount = $this->amount === "" ? $defaultAmount : $this->amount;

			// Set other stuff
			$this->max = $max;
			$this->desc = $desc;
		}
		
		public function getAll() {
			return array(
				"first" => $this->getFirstPage(),
				"previous" => $this->getPreviousPage(),
				"current" => $this->getCurrentPage(),
				"next" => $this->getNextPage(),
				"last" => $this->getLastPage(),
				"amount" => $this->amount
			);
		}
		
		public function getLastPage() {
			$start = $this->max - $this->amount + 1;
			$stop = $this->max;
			return $this->getPage($start, $stop);
		}
		
		public function getNextPage() {
			$start = min($this->max - $this->amount + 1, $this->start + $this->amount);
			$stop = $this->calculateStop($start); // Prevent exceeding the highest indice
			return $this->getPage($start, $stop);
		}
		
		public function getCurrentPage() {
			$start = $this->start;
			$stop = $this->calculateStop($start);
			return $this->getPage($start, $stop);
		}
		
		public function getPreviousPage() {
			$start = max(0, $this->start - $this->amount);
			$stop = $this->calculateStop($start); // Prevent going to negative indices
			return $this->getPage($start, $stop);
		}
		
		public function getFirstPage() {
			$start = 0;
			$stop = $this->calculateStop($start);
			return $this->getPage($start, $stop);
		}
		
		private function getPage($start, $stop) {
			return array(
				"qs" => "start=$start&amt=" . $this->amount,
				"start" => $start,
				"stop" => $stop
			);
		}
		
		private function calculateStop($start) {
			return $start + $this->amount - 1;
		}
	}