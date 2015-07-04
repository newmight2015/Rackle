<?php
    $call = new APICall(INPUT_GET, array(), true);
    $params = $call->getParams();

    // Connect to ABE
    $abe = ABE::getInstance();

    // Ensure that the given value is a hash or height
    if(!$abe->isBlock($params['id'])) {
        $call->endError(array(
            "status" => 404,
            "code" => "BLOCK_NOT_FOUND",
            "title" => "Block not found",
            "description" => "The given block hash or height ({$params['id']}) was not found in the blockchain."
        ));
    }

    // Get the block
    $block = $abe->getBlock($params['id']);
    $curheight = $abe->getNumBlocks();
    $block['transactions'] = $abe->getTransactionsByBlock($block['id']);

    $call->addData(array(
        "id" => $block['hash'],
        "type" => "block",
        "time" => $block['time'],
        "height" => intval($block['height']),
        "merkle" => $block['merkle'],
        "output" => $block['output'],
        "difficulty" => $block['difficulty'],
        "confirmations" => $curheight - $block['height'],
        "transactions" => $block['transactions']
    ));

    $call->end();
