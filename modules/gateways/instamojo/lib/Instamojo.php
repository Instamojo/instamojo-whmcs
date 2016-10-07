<?php
/**
 * Instamojo
 * used to manage Instamojo API calls
 * 
 */
include __DIR__. DIRECTORY_SEPARATOR . "curl.php";
include __DIR__. DIRECTORY_SEPARATOR . "ValidationException.php";
Class Instamojo
{
	private $api_endpoint;
	private $auth_endpoint;
	private $auth_headers;
	private $access_token;
	private $client_id;
	private $client_secret;
	
	 function __construct($client_id,$client_secret,$test_mode)
	{
		$this->curl = new Curl();
		$this->curl->setCacert(__DIR__."/cacert.pem");
		$this->client_id 		= $client_id;
		$this->client_secret	= $client_secret;

		if($test_mode)
			$this->api_endpoint  = "https://test.instamojo.com/v2/";
		else
			$this->api_endpoint  = "https://www.instamojo.com/v2/";
		if($test_mode)
			$this->auth_endpoint = "https://test.instamojo.com/oauth2/token/";
		else
			$this->auth_endpoint = "https://www.instamojo.com/oauth2/token/"; 
		
		$this->getAccessToken();
	}
	public function getAccessToken()
	{
		$data = array();
		$data['client_id']		= $this->client_id;
		$data['client_secret'] 	= $this->client_secret;
		$data['scopes'] 		= "all";
		$data['grant_type'] 	= "client_credentials";

		$result = $this->curl->post($this->auth_endpoint,$data);
		if($result)
		{
			$result = json_decode($result);
			if(isset($result->error))
			{
				throw new ValidationException("The Authorization request failed with message '$result->error'",array("Authorization Failed."),$result);
			}else
				$this->access_token = 	$result->access_token;
		}
		
		$this->auth_headers[] = "Authorization:Bearer $this->access_token";
		
	}
	
	public function createOrderPayment($data)
	{
		$endpoint = $this->api_endpoint ."gateway/orders/";
		$result = $this->curl->post($endpoint,$data,array("headers"=>$this->auth_headers));
			$result =json_decode($result);
		if(isset($result->order))
		{
			return $result;
		}else{
			$errors = array();  
			if(isset($result->message))
				throw new ValidationException("Validation Error with message: $result->message",array($result->message),$result);
			
			foreach($result as $k=>$v)
			{
				if(is_array($v))
					$errors[] =$v[0];
			}
			if($errors)
				throw new ValidationException("Validation Error Occured with following Errors : ",$errors,$result);
		}
	}
	
	
	public function getOrderById($id)
	{
		$endpoint = $this->api_endpoint."gateway/orders/id:$id/";
		$result = $this->curl->get($endpoint,array("headers"=>$this->auth_headers));
		
		$result = json_decode($result);
		if(isset($result->id) and $result->id)
			return $result;
		else
			throw new Exception("Unable to Fetch Payment Request id:'$id' Server Responds ".print_R($result,true));
	}

	public function getPaymentStatus($payment_id, $payments){
		foreach($payments as $payment){
		    if($payment->id == $payment_id){
			    return $payment->status;
		    }
		}
	}
	
}


function instamojo_logger($msg, $add_newline=TRUE){

    $base_dir = dirname(dirname(__FILE__))."/logs/";
    $LOG_FILE = $base_dir  .  DIRECTORY_SEPARATOR . 'imojo.log';
    date_default_timezone_set('Asia/Kolkata');
    $date = date('m/d/Y h:i:s a', time());

    $msg = $date . " | " . $msg;

    if($add_newline){
        $msg .= "\n";
    }
    error_log($msg, 3, $LOG_FILE);
}


function currency_converter($amount, $from_code, $to_code){
	if ($from_code != $to_code){
    	$from = mysql_fetch_array(select_query("tblcurrencies", "id", array("code"=>$from_code)));
    	$to = mysql_fetch_array(select_query("tblcurrencies", "id", array("code"=>$to_code)));
    	if(!(empty($from) or empty($to))){
    		$amount = convertCurrency($amount, $from['id'], $to['id']);
    	}
	}
	return $amount;
}
