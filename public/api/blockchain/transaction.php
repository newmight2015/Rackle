<?php
    $call = new APICall(INPUT_GET, array(), true);
    $params = $call->getParams();

    // Connect to ABE
    $abe = ABE::getInstance();

    // Ensure that the given value is a hash
    if(!$abe->isTransaction($params['id'])) {
        $call->endError(array(
            "status" => 404,
            "code" => "TX_NOT_FOUND",
            "title" => "Transaction not found",
            "description" => "The given transaction hash ({$params['id']}) was not found in the blockchain. It may be invalid or the transaction hasn't been broadcast yet."
        ));
    }

    // Get the transaction
    $tx = $abe->getTransaction($params['id']);
    $curheight = $abe->getNumBlocks();

    $call->addData(array(
        "id" => $params['id'],
        "type" => "transaction",
        "time" => intval($tx['time']),
        "block" => $tx['block'],
        "height" => intval($tx['height']),
        "confirmations" => $curheight - $tx['height'],
        "inputs" => $tx['inputs'],
        "outputs" => $tx['outputs']
    ));

    $call->end();
