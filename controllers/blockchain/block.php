<?php
	if($viewdata['block'] = $abe->getBlock($rpath[3])) {
		$viewdata['block']['transactions'] = $abe->getTransactionsByBlock($viewdata['block']['id']);
		$viewdata['block']['transactions'][0]['coinbase'] = "(Coinbase)"; // First transaction is coinbase
		
		// Calculate block size
		$viewdata['block']['size'] = 0;
		foreach($viewdata['block']['transactions'] as $transaction) {
			$viewdata['block']['size'] += $transaction['size'];
		}
		
		foreach($viewdata['block']['transactions'] as $key => $transaction) {
			$viewdata['block']['transactions'][$key]['hash'] = Format::link(Type::TRANSACTION, $transaction['hash']);
			$viewdata['block']['transactions'][$key]['amount'] = Format::amount($transaction['amount']);
		}
		
		// Render
		$pagedata['view'] = $m->render('blockchain/block', $viewdata);
	} else {
		addMessage("error", "The value you entered is either not a valid block hash/height or the block specified does not exist.");
	}