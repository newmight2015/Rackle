<?php
	$transaction = $rpath[3];
	if($abe->isTransaction($transaction)){
		// Fetch
		$tx = $abe->getTransaction($transaction);
		$tx['confirmations'] = $abe->getNumBlocks() - $tx['height'];
		
		// Prettify & Linkify		
		$viewdata['tx'] = Format::transaction($tx);
		
		// Render
		$pagedata['view'] = $m->render('blockchain/transaction', $viewdata);
	} else {
		addMessage("error", "The value you entered is either not a valid transaction hash or the transaction specified does not exist.");
	}
