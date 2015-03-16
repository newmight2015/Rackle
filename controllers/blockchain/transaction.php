<?php
	$transaction = $rpath[3];
	if($abe->isTransaction($transaction)){
		$tx = $abe->getTransaction($transaction);
		$tx['confirmations'] = $abe->getNumBlocks() - $tx['height'];
		
		// Prettify & Linkify
		$tx['time'] = date("Y-m-d H:i:s", $tx['time']);
		$tx['size'] = number_format($tx['size']);
		$tx['block'] = Format::link(Type::BLOCK, $tx['block']);
		$tx['confSVG'] = SVG::percentageCircle(min($tx['confirmations'], 4), 4, 54); // Calculate SVG path for confirmation arc/circle
		$tx['confHideZero'] = ($tx['confirmations'] == 0 ? "" : "display: none;");
		
		foreach($tx['inputs'] as $key => &$input) {
			$input['address'] = Format::link(Type::ADDRESS, $input['address']);
			$input['amount'] = Format::amount($input['amount']);
		}
		
		foreach($tx['outputs'] as $key => &$output) {
			$output['address'] = Format::link(Type::ADDRESS, $output['address']);
			$output['amount'] = Format::amount($output['amount']);
		}
			
		if(empty($tx['inputs'])) {
			$tx['inputs'] = array("address" => "Coinbase (Mining)", "amount" => $tx['outputs'][0]['amount']);
		}
		
		$viewdata['tx'] = $tx;
		
		// Render
		$pagedata['view'] = $m->render('blockchain/transaction', $viewdata);
	} else {
		addMessage("error", "The value you entered is either not a valid transaction hash or the transaction specified does not exist.");
	}
