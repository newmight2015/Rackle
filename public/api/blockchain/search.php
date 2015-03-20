<?php
	$abe = ABE::getInstance();
	if(filter_has_var(INPUT_GET, 'term')) {
		$term = filter_input(INPUT_GET, 'term', FILTER_SANITIZE_STRING);
		$result = $abe->search($_GET['term']);
	} else {
		$result = false;
	}
	
	if($result === false){
		$status = "Failure";
		$result = "404";
	}else{
		$status = "Success";
	}
	
	echo json_encode(array("status" => $status, "result" => $result));
