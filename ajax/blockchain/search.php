<?php
	require __DIR__ . "/../../config.php";
	require __DIR__ . "/../../lib/abe.php";
	$result = $abe->search($_GET['term']);
	if($result === false){
		$status = "Failure";
		$result = "404";
	}else{
		$status = "Success";
	}
	
	echo json_encode(array("status" => $status, "result" => $result));
