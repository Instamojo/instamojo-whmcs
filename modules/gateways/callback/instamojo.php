<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../instamojo/lib/Instamojo.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

# check if sufficent parameters pass at callback
if(!isset($_GET['payment_id']) or !isset($_GET['id'])) {
	instamojo_logger("Callback called with no Payment ID  or Payment Request ID redirecting to homepage");
	header("Location:$gatewayParams[systemurl]");
	die();
}

$payment_request_id = $_GET['id'];
$payment_id = $_GET['payment_id'];
instamojo_logger("Callback called with Payment ID: " . $payment_id . " and Payment Request ID $payment_request_id");

$stored_pri = "";
if(isset($_SESSION['instamojo_payment_request_id']))
	$stored_pri = $_SESSION['instamojo_payment_request_id'];

if($stored_pri != $payment_request_id)
{
	instamojo_logger("Stored Payment request id ($stored_pri) is not matched with passed payment request id ($payment_request_id)");
	header("Location:$gatewayParams[systemurl]");
	die();

}

try{
	include_once DIR_ROOT."/app/addons/instamojo/lib/Instamojo.php";
	$client_id = $gatewayParams['instamojo_client_id'];
    $client_secret = $gatewayParams['instamojo_client_secret'];
    $testMode = ($gatewayParams['testMode']=="on")?1:0;
	instamojo_logger("Instamojo Settings are CLinet id = $client_id | client secret = $client_secret  Testmode = $testMode");
	$api = new Instamojo($client_id,$client_secret,$testMode);
		
	$response = $api->getOrderById($payment_request_id);
	instamojo_logger("Response from server ".print_r($response,true));
	$payment_status = $response->payments[0]->status;
	if($payment_status == "successful" OR  $payment_status =="failed" )
	{
		$order_id = $response->transaction_id;
		$order_id = explode("-",$order_id);
		$order_id = $order_id[1];
		instamojo_logger("Extracted order id from trasaction_id: ".$order_id);
		
		if($payment_status == "successful")
		{
			instamojo_logger("Payment was credited with Payment ID :$payment_id");
			$invoiceId = checkCbInvoiceID($order_id, $gatewayParams['name']);
			$paymentAmount = $response->amount;
			instamojo_logger("$invoiceId | $paymentAmount | $gatewayModuleName");
			logTransaction($gatewayParams['name'], $array = json_decode(json_encode($response), true), $payment_status);
			addInvoicePayment(
				$invoiceId,
				$payment_id,
				$paymentAmount,
				0, //payment fees.
				$gatewayModuleName
			);
			if(isset($_SESSION['return_url']))
				$redirect_url =  $_SESSION['return_url'];
			else
				$redirect_url = $gatewayParams['systemurl'];
			header("Location:$redirect_url");
			
		}
		else if ($payment_status == "failed")
		{
			if(isset($_SESSION['return_url']))
				$redirect_url =  $_SESSION['return_url'];
			else
				$redirect_url = $gatewayParams['systemurl'];
			header("Location:$redirect_url");
			die();

		}					
	}
	else
	{
		instamojo_logger("Unimplemented Payment Status $payment_status");
		header("Location:$gatewayParams[systemurl]");
		die();
	}

}catch(Exception $e){
	instamojo_logger("Exception Occcured during Payment Confirmation with message ".$e->getMessage());
	exit;
	//header("Location:$gatewayParams[systemurl]");
}		



