<?php
	if($abe->isTransaction($_GET['query'])){
		$viewdata['tx'] = $abe->getTransaction($_GET['query']);
		$viewdata['tx']['confirmations'] = $abe->getNumBlocks() - $viewdata['tx']['height'];
		
		// Prettify & Linkify
		$viewdata['tx']['time'] = date("Y-m-d H:i:s", $viewdata['tx']['time']);
		$viewdata['tx']['size'] = number_format($viewdata['tx']['size']);
		$viewdata['tx']['block'] = createLink(Type::Block, $viewdata['tx']['block']);
		$viewdata['tx']['confSVG'] = SVG::percentageCircle(min($viewdata['tx']['confirmations'], 4), 4, 54); // Calculate SVG path for confirmation arc/circle
		$viewdata['tx']['confHideZero'] = ($viewdata['tx']['confirmations'] == 0 ? "" : "display: none;");
		
		foreach($viewdata['tx']['inputs'] as $key => $input) {
			$viewdata['tx']['inputs'][$key]['address'] = createLink(Type::Address, $input['address']);
			$viewdata['tx']['inputs'][$key]['amount'] = number_format($input['amount'] / pow(10, 8), 8);
		}
		
		foreach($viewdata['tx']['outputs'] as $key => $output) {
			$viewdata['tx']['outputs'][$key]['address'] = createLink(Type::Address, $output['address']);
			$viewdata['tx']['outputs'][$key]['amount'] = number_format($output['amount'] / pow(10, 8), 8);
		}
			
		if(empty($viewdata['tx']['inputs'])) {
			$viewdata['tx']['inputs'] = array("address" => "Coinbase (Mining)", "amount" => $viewdata['tx']['outputs'][0]['amount']);
		}
		
		// Render
		$pagedata['view'] = $m->render('blockchain/transaction', $viewdata);
	} else {
		addMessage("error", "The value you entered is either not a valid transaction hash or the transaction specified does not exist.");
	}
