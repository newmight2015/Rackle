<?php
	require __DIR__ . "/baseconv.php"; // Base conversion class used for base58 encoding/decoding
	
	class ABE {
		private $chain; // The chain identifier given by ABE (Check 'chain' table in database)
		private $addrver; // Address version byte (Hexadecimal)
		private $db; // Database handle assigned by constructor
		
		// Assign properties and connect to database
		public function __construct($chain, $addrver, $dsn, $dbuser, $dbpass){
			$this->chain = $chain;
			$this->addrver = $addrver;
			$this->db = new PDO($dsn, $dbuser, $dbpass);
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
					$addrhex = baseconv::base58_hex($term);
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
			return ($this->isBlockHash($term) || $this->isBlockHeight($term));
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
			$st->execute(array($this->chain));
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
			$st->execute(array($this->chain, $hash));
			$block = $st->fetch();
			
			$block['time'] = date("Y-m-d H:i:s", $block['time']); // Convert timestamp to readable format
			$block['output'] = $block['output'] / pow(10, 8); // Convert OMC-satoshi to OMC
			$block['difficulty'] = round($this->calculateDifficulty($block['bits']),4); // Calculate difficulty from nBits
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
			$st->execute(array($this->chain, $fromHeight, $toHeight));
			$blocks = $st->fetchAll();
			foreach($blocks as $key => $block){
				$blocks[$key]['time'] = date("Y-m-d H:i:s", $block['time']); // Convert timestamp to readable format
				$blocks[$key]['output'] = $block['output'] / pow(10, 8); // Convert OMC-satoshi to OMC
				$blocks[$key]['difficulty'] = round($this->calculateDifficulty($block['bits']),4); // Calculate difficulty from nBits
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
			$st->execute(array($this->chain, $block));
			return $st->fetchAll();
		}
		
		// Get all transactions sent from or to a given address
		public function getTransactionsByAddress($address){
			$pubkeyhash = $this->addressToPubkeyHash($address);
			// Fetch all transactions
			$q = "SELECT 
				tx_hash AS hash,
				block_height AS height,
				block_hash AS block,
				SUM(amount) AS amount,
				confirmations,
				time
			FROM
				(SELECT 
					HEX(tx.tx_hash) AS tx_hash,
						block.block_height,
						HEX(block.block_hash) AS block_hash,
						SUM(txout.txout_value) AS amount,
						(SELECT 
								block.block_height
							FROM
								chain
							JOIN chain_candidate cc ON cc.block_id = chain_last_block_id
							JOIN block ON block.block_id = chain_last_block_id
							WHERE
								chain.chain_id = ?) - block.block_height + 1 AS confirmations,
						block.block_nTime AS time
				FROM
					tx
				JOIN `txout` ON txout.tx_id = tx.tx_id
				JOIN `pubkey` ON pubkey.pubkey_id = txout.pubkey_id
				JOIN `block_tx` ON block_tx.tx_id = txout.tx_id
				JOIN `block` ON block.block_id = block_tx.block_id
				JOIN `chain_candidate` cc ON cc.block_id = block_tx.block_id
				WHERE
					pubkey_hash = UNHEX(?)
						AND cc.chain_id = ?
						AND cc.in_longest = 1
				GROUP BY tx_hash UNION SELECT 
					HEX(tx_hash) AS tx_hash,
						block.block_height,
						HEX(block.block_hash) AS block_hash,
						SUM(txout_value * - 1) AS amount,
						(SELECT 
								block.block_height
							FROM
								chain
							JOIN chain_candidate cc ON cc.block_id = chain_last_block_id
							JOIN block ON block.block_id = chain_last_block_id
							WHERE
								chain.chain_id = ?) - block.block_height + 1 AS confirmations,
						block.block_nTime AS time
				FROM
					tx
				JOIN txin ON txin.tx_id = tx.tx_id
				JOIN txout ON txin.txout_id = txout.txout_id
				JOIN pubkey ON pubkey.pubkey_id = txout.pubkey_id
				JOIN block_tx ON block_tx.tx_id = tx.tx_id
				JOIN block ON block.block_id = block_tx.block_id
				JOIN chain_candidate cc ON cc.block_id = block.block_id
				WHERE
					pubkey_hash = UNHEX(?)
						AND cc.chain_id = ?
						AND cc.in_longest = 1
				GROUP BY tx_hash) transactions
			GROUP BY tx_hash
			ORDER BY time ASC";
			$st = $this->db->prepare($q);
			$st->execute(array($this->chain, $pubkeyhash, $this->chain, $this->chain, $pubkeyhash, $this->chain));
			$txs = $st->fetchAll();
			
			$runbalance = 0;
			foreach($txs as &$tx) {
				$runbalance += $tx['amount'];
				$tx['balance'] = $runbalance;
			}
			
			return $txs;
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
			$st->execute(array($this->chain, $hash));
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
			$st->execute(array($this->chain, $hash));
			$inputs = $st->fetchAll();
			foreach($inputs as $key => $input){
				$inputs[$key]['address'] = $this->pubkeyHashToAddress($input['pubkey_hash']);
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
			$st->execute(array($this->chain, $hash));
			$outputs = $st->fetchAll();
			foreach($outputs as $key => $output){
				$outputs[$key]['address'] = $this->pubkeyHashToAddress($output['pubkey_hash']);
			}
			return $outputs;
		}
		
		// Returns an address for the given public key
		public function pubkeyHashToAddress($pubkey){
			$pubkey = $this->addrver . $pubkey; // Prepend version byte
			$pubkeybs = pack("H*", $pubkey); // Pack to raw bytestring
			$checksum = hash("sha256", hash("sha256", $pubkeybs, true)); // Calculate checksum
			$checksum = substr($checksum, 0, 8); // Shorten to 4 bytes
			$pubkey .= $checksum; // Append checksum to pubkey hash
			return baseconv::hex_base58($pubkey); // Convert to base 58 and return
		}
		
		// Returns the public key hash extracted from the given address
		public function addressToPubkeyHash($address){
			$hexaddr = baseconv::base58_hex($address); // Convert to hexadecimal
			$unchecked = substr($hexaddr, 2, 40); // Strip version byte + checksum
			return $unchecked;
		}
		
		// Calculates difficulty using a block target value
		public function targetToDifficulty($target){
			return ((1 << 224) - 1) * 1000 / ($target + 1) / 1000;
		}
		
		// Calculate a target value using the number of bits required
		public function calculateTarget($nBits){
			$shift = 8 * ((($nBits >> 24) & 0xff) - 3);
			$bits = $nBits & 0x7fffff;
			$sign = ($nBits & 0x800000 ? -1 : 1);

			if($shift >= 0)
				return $sign * ($bits << $shift);

			return $sign * ($bits >> ($shift * -1));
		}
		
		// Calculate the block difficulty from the number of bits required
		public function calculateDifficulty($nBits){
			return $this->targetToDifficulty($this->calculateTarget($nBits));
		}
	}
	
	$config['db']['dsn'] = "mysql:host=" . $config['db']['host'] . ";dbname=" . $config['db']['name'];
	$abe = new ABE($config['coin']['chain_id'], $config['coin']['addr_version'], $config['db']['dsn'], $config['db']['user'], $config['db']['pass']);