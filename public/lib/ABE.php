<?php
	//require __DIR__ . "/../vendor/doctorblue/baseconvert/src/BaseConvert.php";
	use \DoctorBlue\BaseConvert;
	
	class ABE {
		private static $instance = null; // Instance for singleton
		private static $chain; // The chain identifier given by ABE (Check 'chain' table in database)
		private static $addrver; // Address version byte (Hexadecimal)
		private $db; // Database handle assigned by constructor
		
		// Assign properties and connect to database
		protected function __construct(){
			$config = Configuration::get();
			
			self::$chain = $config['coin']['chain_id'];
			self::$addrver = $config['coin']['addr_version'];
			$dsn = "mysql:host=" . $config['db']['host'] . ";dbname=" . $config['db']['abe'];
			$this->db = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
		}
	
		public static function getInstance() {
			if(self::$instance == null) {
				self::$instance = new ABE();
			}
			
			return self::$instance;
		}
	
		// General function for searching when the term type is unknown
		// Returns the partial URL for the search result, e.g. "address/oYMpwbDq9QLi2W55tUhzWFwQEKaeHaW55e"
		// or false if no result was found
		public function search($term){
			// Lightest function, doesn't require any lookups as opposed to the others
			if($this->isAddress($term))
				return "address/$term";
			
			if($this->isBlock($term))
				return "block/$term";

			if($this->isTransaction($term))
				return "transaction/$term";
			
			return false;
		}
		
		// Check whether a term is a base58 address by base58 decoding the term and checking its length
		public function isAddress($term){
			// Omnicoin addresses are always 34 characters long in base58
			if(strlen($term) === 34){
				// Catch exception thrown if input is not base58
				try{
					$addrhex = BaseConvert::base58_hex($term);
				}catch(Exception $e){
					return false;
				}
				
				// Addresses are always 25 bytes (50 hex characters)
				if(strlen($addrhex) === 50){
					return true;
				}
			}
			
			return false;
		}
		
		// Check whether a term is a block hash or height (performance warning: requires lookup)
		// NB: Does not check whether the block exists, use getBlock()
		public function isBlock($term){
			return ($this->isBlockHeight($term) || $this->isBlockHash($term));
		}
		
		// Determine whether the given term is a block hash (light I/O)
		public function isBlockHash($term) {
			if(strlen($term) === 64){
				// Query for a block with the given hash (Only needed because the hash could also be a transaction)
				$q = "SELECT block_hash FROM block WHERE block_hash = UNHEX(?) LIMIT 1";
				$st = $this->db->prepare($q);
				$st->execute(array($term));
				if($st->fetchColumn() !== false)
					return true;
			}
		}
		
		// Determine whether the given term is a block height (no I/O)
		public function isBlockHeight($term) {
			if(is_numeric($term)){
				return true;
			}
			
			return false;
		}
		
		// Check if a term is a transaction hash (light I/O)
		public function isTransaction($term){
			// All tx hashes are 64 characters
			if(strlen($term) === 64){
				$q = "SELECT tx_hash FROM tx WHERE tx_hash = UNHEX(?)";
				$st = $this->db->prepare($q);
				$st->execute(array($term));
				if($st->fetchColumn() !== false)
					return true;
			}
			
			return false;
		}
		
		// Returns an integer, indicating the height of the highest block (light I/O)
		public function getNumBlocks(){
			$q = "SELECT block.block_height 
				    FROM chain
				    JOIN chain_candidate cc ON cc.block_id = chain_last_block_id 
					JOIN block ON block.block_id = chain_last_block_id 
				   WHERE chain.chain_id = ?
				     AND cc.in_longest = 1";
			$st = $this->db->prepare($q);
			$st->execute(array(self::$chain));
			return $st->fetchColumn();
		}
		
		// Checks if the given term is a height or hash and uses the corresponding method to fetch the block
		public function getBlock($term) {
			if($this->isBlockHash($term)) {
				return $this->getBlockByHash($term);
			} elseif ($this->isBlockHeight($term)) {
				return $this->getBlockByHeight($term);
			} else {
				return null;
			}
		}
		
		// Takes a block hash and returns a block (array) containing the following:
		//     height, hash, time, output, difficulty, total amount mined, average age,
		//     chain age, %CoinDD, satoshi-seconds and total satoshi-seconds
		public function getBlockByHash($hash) {
			$q = "SELECT
				b.block_id AS id,
				HEX(b.block_hash) AS hash,
				HEX(b.block_hashMerkleRoot) AS merkle,
				b.block_height AS height,
				b.block_nTime AS time,
				b.block_num_tx AS num_tx,
                b.block_nBits AS bits,
				b.block_value_out AS output,
                b.block_total_seconds AS total_secs,
				b.block_satoshi_seconds AS satoshi_secs,
                b.block_total_satoshis AS total_satoshis,
				b.block_ss_destroyed AS satoshi_secs_destroyed,
                b.block_total_ss AS total_satoshi_secs
				FROM block b
				JOIN chain_candidate cc ON cc.block_id = b.block_id
				WHERE cc.chain_id = ?
				  AND cc.in_longest = 1
				  AND b.block_hash = UNHEX(?)
				LIMIT 1";
			
			$st = $this->db->prepare($q);
			$st->execute(array(self::$chain, $hash));
			$block = $st->fetch();
			
			$block['time'] = date("Y-m-d H:i:s", $block['time']); // Convert timestamp to readable format
			$block['output'] = $block['output'] / pow(10, 8); // Convert OMC-satoshi to OMC
			$block['difficulty'] = round(self::calculateDifficulty($block['bits']),4); // Calculate difficulty from nBits
			$block['total_coins'] = $block['total_satoshis'] / pow(10, 8); // Convert satoshi to OMC again
			$block['avg_age'] = round($block['satoshi_secs'] / $block['total_satoshis'] / 86400, 4); // Calculate average
			$block['chain_age'] = round($block['total_secs'] / 86400, 4); // Convert seconds to days
			$block['pct_days_destroyed'] = round(($block['total_secs'] == 0 ? 0 : (100 - (100 * $block['satoshi_secs'] / $block['total_satoshi_secs']))),4); // Calculate amount of days destroyed
			
			return $block;
		}
		
		// Takes a block height and returns a block (array) containing the following:
		//     height, hash, time, output, difficulty, total amount mined, average age,
		//     chain age, %CoinDD, satoshi-seconds and total satoshi-seconds
		public function getBlockByHeight($height) {
			return $this->getBlocksByHeight($height, $height)[0];
		}
		
		// Returns an array with the following information about blocks in the specified range:
		//     height, hash, time, output, difficulty, total amount mined, average age,
		//     chain age, %CoinDD, satoshi-seconds and total satoshi-seconds
		public function getBlocksByHeight($fromHeight, $toHeight){
			$q = "SELECT
				b.block_id AS id,
				HEX(b.block_hash) AS hash,
				HEX(b.block_hashMerkleRoot) AS merkle,
				b.block_height AS height,
				b.block_nTime AS time,
				b.block_num_tx AS num_tx,
                b.block_nBits AS bits,
				b.block_value_out AS output,
                b.block_total_seconds AS total_secs,
				b.block_satoshi_seconds AS satoshi_secs,
                b.block_total_satoshis AS total_satoshis,
				b.block_ss_destroyed AS satoshi_secs_destroyed,
                b.block_total_ss AS total_satoshi_secs
              FROM block b
              JOIN chain_candidate cc ON (b.block_id = cc.block_id)
             WHERE cc.chain_id = ?
               AND cc.block_height BETWEEN ? AND ?
               AND cc.in_longest = 1
             ORDER BY cc.block_height DESC";
			 
			$st = $this->db->prepare($q);
			$st->execute(array(self::$chain, $fromHeight, $toHeight));
			$blocks = $st->fetchAll();
			foreach($blocks as $key => $block){
				$blocks[$key]['time'] = date("Y-m-d H:i:s", $block['time']); // Convert timestamp to readable format
				$blocks[$key]['output'] = $block['output'] / pow(10, 8); // Convert OMC-satoshi to OMC
				$blocks[$key]['difficulty'] = round(self::calculateDifficulty($block['bits']),4); // Calculate difficulty from nBits
				$blocks[$key]['total_coins'] = $block['total_satoshis'] / pow(10, 8); // Convert satoshi to OMC again
				$blocks[$key]['avg_age'] = round($block['satoshi_secs'] / $block['total_satoshis'] / 86400, 4); // Calculate average
				$blocks[$key]['chain_age'] = round($block['total_secs'] / 86400, 4); // Convert seconds to days
				$blocks[$key]['pct_days_destroyed'] = round(($block['total_secs'] == 0 ? 0 : (100 - (100 * $block['satoshi_secs'] / $block['total_satoshi_secs']))),4); // Calculate amount of days destroyed
			}
			
			return $blocks;
		}
		
		// Fetches transactions based on the given block id
		public function getTransactionsByBlock($block) {
			$q = "SELECT
					tx.tx_id AS id,
					HEX(tx.tx_hash) AS hash,
					tx.tx_size AS size,
					SUM(txout.txout_value) AS amount,
					block.block_height AS height,
					block.block_nTime AS time
				FROM block
				JOIN block_tx ON block_tx.block_id = block.block_id
				JOIN tx ON tx.tx_id = block_tx.tx_id
				JOIN txout ON txout.tx_id = tx.tx_id
				JOIN chain_candidate cc ON cc.block_id = block_tx.block_id
			   WHERE cc.chain_id = ?
			     AND cc.in_longest = 1
				 AND block.block_id = ?
			GROUP BY tx.tx_id
			ORDER BY tx.tx_id DESC";
			$st = $this->db->prepare($q);
			$st->execute(array(self::$chain, $block));
			return $st->fetchAll();
		}
		
		// Get all transactions sent from or to a given address
		public function getTransactionsByAddress($address){
			$pubkeyhash = $this->addressToPubkeyHash($address);
			$curheight = $this->getNumBlocks();
			
			// Fetch all transactions
			$q = "SELECT
					HEX(tx.tx_hash) AS hash,
					b.block_height AS height,
					HEX(b.block_hash) AS block,
					-SUM(tx.txin_value) AS amount,
					:height - b.block_height AS confirmations,
					b.block_nTime as time
				FROM txin_detail tx
				JOIN block b ON (b.block_id = tx.block_id)
				WHERE pubkey_hash = UNHEX(:pubkey)
				AND in_longest = 1 
				GROUP BY tx_hash
			UNION
				SELECT
					HEX(tx.tx_hash) AS hash,
					b.block_height AS height,
					HEX(b.block_hash) AS block,
					SUM(tx.txout_value) AS amount,
					:height - b.block_height AS confirmations,
					b.block_nTime AS time
				FROM txout_detail tx
				JOIN block b ON b.block_id = tx.block_id
			   WHERE pubkey_hash = UNHEX(:pubkey)
				 AND in_longest = 1
			GROUP BY tx_hash
			ORDER BY height";
			
			$st = $this->db->prepare($q);
			$st->bindParam("height", $curheight, PDO::PARAM_INT);
			$st->bindParam("pubkey", $pubkeyhash, PDO::PARAM_STR);
			$st->execute();
			return $st->fetchAll();
		}
		
		// Get the total amount sent from an address
		public function getTotalSentByAddress($address) {
			$pubkeyhash = $this->addressToPubkeyHash($address);
			$q = "SELECT SUM(txin_value) FROM txin_detail WHERE pubkey_hash = UNHEX(:pubkey) AND in_longest = 1";
			$st = $this->db->prepare($q);
			$st->bindParam("pubkey", $pubkeyhash, PDO::PARAM_STR);
			$st->execute();
			return $st->fetchColumn();
		}
		
		// Get the total amount received by an address
		public function getTotalReceivedByAddress($address) {
			$pubkeyhash = $this->addressToPubkeyHash($address);
			$q = "SELECT SUM(txout_value) FROM txout_detail WHERE pubkey_hash = UNHEX(:pubkey) AND in_longest = 1";
			$st = $this->db->prepare($q);
			$st->bindParam("pubkey", $pubkeyhash, PDO::PARAM_STR);
			$st->execute();
			return $st->fetchColumn();
		}
		
		// Get a transaction by its hash
		public function getTransaction($hash){
			$q = "SELECT
				HEX(tx.tx_hash) AS hash,
				tx.tx_size AS size,
				HEX(block.block_hash) AS block, 
				block.block_height AS height,
				block.block_nTime AS time
			FROM
				tx
			JOIN block_tx ON block_tx.tx_id = tx.tx_id
			JOIN block ON block.block_id = block_tx.block_id
			JOIN chain_candidate ON block.block_id = chain_candidate.block_id
			WHERE 
				chain_candidate.chain_id = ?
			AND chain_candidate.in_longest = 1
			AND tx_hash = UNHEX(?);";
			
			$st = $this->db->prepare($q);
			$st->execute(array(self::$chain, $hash));
			$transaction = $st->fetch();
			
			$transaction['inputs'] = $this->getTransactionInputs($hash);
			$transaction['outputs'] = $this->getTransactionOutputs($hash);
			return $transaction;
		}
						
		// Get all inputs for a transaction
		public function getTransactionInputs($hash){
			$q = "SELECT
				txout_value AS amount,
				HEX(pubkey_hash) AS pubkey_hash
			FROM
				tx
			JOIN txin ON txin.tx_id = tx.tx_id
			JOIN txout ON txin.txout_id = txout.txout_id
			JOIN pubkey ON pubkey.pubkey_id = txout.pubkey_id
			JOIN block_tx ON block_tx.tx_id = tx.tx_id
			JOIN block ON block.block_id = block_tx.block_id
			JOIN chain_candidate cc ON block.block_id = cc.block_id
			WHERE
				cc.chain_id = ?
				AND cc.in_longest = 1
				AND tx_hash = UNHEX(?)
			ORDER BY txin.txin_pos ASC";
			$st = $this->db->prepare($q);
			$st->execute(array(self::$chain, $hash));
			$inputs = $st->fetchAll();
			foreach($inputs as $key => $input){
				$inputs[$key]['address'] = self::pubkeyHashToAddress($input['pubkey_hash']);
			}
			return $inputs;
		}
		
		// Get all outputs for a transaction
		public function getTransactionOutputs($hash){
			$q = "SELECT
				txout_value AS amount,
				HEX(pubkey_hash) AS pubkey_hash
			FROM
				tx
			JOIN txout ON tx.tx_id = txout.tx_id
			JOIN pubkey ON pubkey.pubkey_id = txout.pubkey_id
			JOIN block_tx ON block_tx.tx_id = tx.tx_id
			JOIN block ON block.block_id = block_tx.block_id
			JOIN chain_candidate cc ON block.block_id = cc.block_id
			WHERE
				cc.chain_id = ?
				AND cc.in_longest = 1
				AND tx_hash = UNHEX(?)
			ORDER BY txout.txout_pos ASC";
			$st = $this->db->prepare($q);
			$st->execute(array(self::$chain, $hash));
			$outputs = $st->fetchAll();
			foreach($outputs as $key => $output){
				$outputs[$key]['address'] = self::pubkeyHashToAddress($output['pubkey_hash']);
			}
			return $outputs;
		}
		
		// Returns an address for the given public key
		public static function pubkeyHashToAddress($pubkey){
			$pubkey = self::$addrver . $pubkey; // Prepend version byte
			$pubkeybs = pack("H*", $pubkey); // Pack to raw bytestring
			$checksum = hash("sha256", hash("sha256", $pubkeybs, true)); // Calculate checksum
			$checksum = substr($checksum, 0, 8); // Shorten to 4 bytes
			$pubkey .= $checksum; // Append checksum to pubkey hash
			return BaseConvert::hex_base58($pubkey); // Convert to base 58 and return
		}
		
		// Returns the public key hash extracted from the given address
		public static function addressToPubkeyHash($address){
			$hexaddr = BaseConvert::base58_hex($address); // Convert to hexadecimal
			$unchecked = substr($hexaddr, 2, 40); // Strip version byte + checksum
			return $unchecked;
		}
		
		// Calculates difficulty using a block target value
		public static function targetToDifficulty($target){
			return ((1 << 224) - 1) * 1000 / ($target + 1) / 1000;
		}
		
		// Calculate a target value using the number of bits required
		public static function calculateTarget($nBits){
			$shift = 8 * ((($nBits >> 24) & 0xff) - 3);
			$bits = $nBits & 0x7fffff;
			$sign = ($nBits & 0x800000 ? -1 : 1);

			if($shift >= 0)
				return $sign * ($bits << $shift);

			return $sign * ($bits >> ($shift * -1));
		}
		
		// Calculate the block difficulty from the number of bits required
		public static function calculateDifficulty($nBits){
			return self::targetToDifficulty(self::calculateTarget($nBits));
		}
	}