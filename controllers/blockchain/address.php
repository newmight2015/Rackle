<?php
	// Seriously-huge-entries blacklist
	$blacklist = array("");
	$address = $rpath[3];
	
	// Get transactions to and from address
	$transactions = array();
	if(in_array($address, $blacklist)) {
		addMessage("error", "This address has too many records to display.");
	} else {
		$transactions = $abe->getTransactionsByAddress($address);
	}

	// Paginate results
	$paginator = new Paginator(50, count($transactions), true);
	$limits = $paginator->getAll();
	
	$viewdata['paginator'] = $limits;
	
	// Generate balance and format output
	foreach($transactions as &$tx){		
		// Set amount colour
		$amtcolor = $tx['amount'] > 0 ? "green" : "red";
		
		// Prettify numbers (Thousand separator and +/- colors)
		$tx['amount'] = "<span style='color:$amtcolor'>" . number_format($tx['amount'] / pow(10,8), 8) . "</span>";
		$tx['balance'] = number_format($tx['balance'] / pow(10, 8), 8);

		// Format timestamp
		$tx['time'] = date("Y-m-d H:i:s", $tx['time']);
		
		// Linkify linkables
		$tx['hash'] = createLink(Type::Transaction, $tx['hash'], substr($tx['hash'], 0, 32));
		$tx['height'] = createLink(Type::Block, $tx['height'], number_format($tx['height']));
	}

	$viewdata['transactions'] = array_reverse(array_slice($transactions, $limits['current']['start'], $limits['amount']));
	$viewdata['address'] = $address;
	$viewdata['pubkeyhash'] = $abe::addressToPubkeyHash($address);
	$viewdata['balance'] = isset($transactions[0]) ? end($transactions)['balance'] : "0.00000000";
	$pagedata['view'] = $m->render('blockchain/address', $viewdata);
