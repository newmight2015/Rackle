<?php
	if($block = $abe->getBlock($rpath[3])) {
		// Fetch
		$block['transactions'] = $abe->getTransactionsByBlock($block['id']);
		$block['transactions'][0]['coinbase'] = "(Coinbase)"; // First transaction is coinbase
		
		// Calculate block size
		$block['size'] = 0;
		foreach($block['transactions'] as $transaction) {
			$block['size'] += $transaction['size'];
		}
		
		// Render
		$viewdata['block'] = Format::block($block);
		$pagedata['view'] = $m->render('blockchain/block', $viewdata);
	} else {
		addMessage("error", "The value you entered is either not a valid block hash/height or the block specified does not exist.");
	}