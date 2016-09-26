<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
# instamojo_logger() is defined in instamojo/lib/Instamojo.php
require_once __DIR__  ."/instamojo/lib/Instamojo.php";

function instamojo_MetaData()
{
    return array(
        'DisplayName' => 'Instamojo Payment Gateway',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}


function instamojo_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Instamojo',
        ),
        'instamojo_client_id' => array(
            'FriendlyName' => 'Client ID',
            'Type' => 'text',
            'Size' => '250',
            'Default' => '',
            'Description' => 'Client ID',
        ),
        'instamojo_client_secret' => array(
            'FriendlyName' => 'Client Secret',
            'Type' => 'text',
            'Size' => '250',
            'Default' => '',
            'Description' => '',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
       
    );
}


function instamojo_link($params)
{
    // Gateway Configuration Parameters
    $client_id = $params['instamojo_client_id'];
    $client_secret = $params['instamojo_client_secret'];
    $testMode = ($params['testMode']=="on")?1:0;
   

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    if ($currency['code'] !== 'INR') {
        $result = mysql_fetch_array(select_query("tblcurrencies", "id", array("code"=>'INR')));
        $inr_id = $result['id'];
        $converted_amount = convertCurrency($amount, $currency['id'], $inr_id);
    } else {
        $converted_amount = $amount;
    }

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $postfields = array();
    $postfields['name'] = substr($firstname." ".$lastname,0,75);
    $postfields['email'] = $email;
    $postfields['phone'] = $phone;
    $postfields['transaction_id'] = time()."-".$invoiceId;
    $postfields['amount'] = $converted_amount;
    $postfields['currency'] = "INR";
    $postfields['redirect_url'] = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';
    $_SESSION['return_url'] = $returnUrl;
	
	try{
		instamojo_logger("Creating Instamojo order for $invoiceId");
		instamojo_logger("Instamojo Settings are CLinet id = $client_id | client secret = $client_secret } Testmode = $testMode");
		$api = new Instamojo($client_id, $client_secret, $testMode);
		instamojo_logger("Data sending to instamojo for creating order ".print_r($postfields,true));
		$response = $api->createOrderPayment($postfields);
		instamojo_logger("Response from server ".print_r($response,true));
		if(isset($response->order ))
		{
			$redirectUrl = $response->payment_options->payment_url;
			$_SESSION['instamojo_payment_request_id'] = $response->order->id;
			$htmlOutput = '<form method="get" action="' . $redirectUrl . '">';
			$htmlOutput .= '<input type="hidden" name="embed" value="form" />';
			$htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
			$htmlOutput .= '</form>';

			return $htmlOutput;
		}
	}catch(CurlException $e){
		// handle exception releted to connection to the sever
		instamojo_logger((string)$e);
		$htmlOutput = "<p style='color:red'>Sorry for inconvinince<br/>Problem while connecting Instamojo please contact administrator for support</p>";
		$htmlOutput .= "<a href='$redirectUrl'>Back</a>";
		return $htmlOutput;
	}catch(ValidationException $e){
		// handle exceptions releted to response from the server.
		instamojo_logger($e->getMessage()." with ");
		instamojo_logger(print_r($e->getResponse(),true)."");
		$htmlOutput = "";
		foreach($e->getErrors() as $error )
		{
			
			if(stristr($error,"Authorization"))
				$htmlOutput .= "<p style='color:red'>Merchant Authorization Failed </p>";
			elseif(stristr($error,"phone"))
				$htmlOutput .="<p style='color:red'>Your Phone number should be a valid Indian Mobile number</p>";
			else
				$htmlOutput .="<p style='color:red'>$error</p>";
		}	
			
			return $htmlOutput;
	}catch(Exception $e)
	{ // handled common exception messages which will not caught above.
		
		instamojo_logger('Error While Creating Order : ' . $e->getMessage());
		$htmlOutput = "<p style='color:red'>Sorry for inconvinince<br/>Problem while connecting Instamojo please contact administrator for support</p>";
		$htmlOutput .= "<a href='$redirectUrl'>Back</a>";
		return $htmlOutput;

	}
   return "";
}




