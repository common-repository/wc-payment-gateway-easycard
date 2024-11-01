<?php

if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

class WC_EasyCard_API {
    public $sandbox;
    public $private_api_key;
    public $api_token_url;
    public $api_base_url;
  /**
   * Constructor for the API.
   *
   * @access public
   * @return void
   */
    public function __construct() {
        
        $this->sandbox          = SANDBOX;
        $this->private_api_key  = PRIVATEKEY;
        $this->api_token_url    = "https://identity.e-c.co.il/connect/token"; //This is for live mode
        $this->api_base_url     = "https://api.e-c.co.il";
        
        if( $this->sandbox == "yes"){
            $this->api_base_url     = "https://ecng-transactions.azurewebsites.net";
            $this->api_token_url    = "https://ecng-identity.azurewebsites.net/connect/token";
        }
    }

    //Access token
    function getToken(){
        $headers = array(
                            "Content-Type"  => "application/x-www-form-urlencoded",
                        );

        $body = array(
                        'client_id'         =>  'terminal',
                        'grant_type'        =>  'woocommerce',
                        'authorizationKey'  =>  $this->private_api_key
                     );

        $args = array(
                        'headers'       => $headers,
                        'timeout'       => 120,
                        'httpversion'   => '1.1',
                        'sslverify'     => true,
                        'body'          => $body
                    );

        $response       = wp_remote_post( $this->api_token_url, $args );
        $responseBody   = wp_remote_retrieve_body( $response );
        $responseBody   = json_decode( $responseBody, true );
        $response_code = wp_remote_retrieve_response_code( $response );
        if($response_code == 200){
            return $responseBody['access_token'];
        }

        return false;
    }

    public function refund($amount, $transactionID){
        
        $api_token = $this->getToken();

        $headers = array(
                            "Content-Type"  => "application/json",
                            "Authorization"  => "Bearer ".$api_token,
                        );

        $data = array(
                        'refundAmount'                  =>  $amount,
                        'existingPaymentTransactionID'  =>  $transactionID,
                    );

        $body = json_encode($data);

        $args = array(
                        'headers'       => $headers,
                        'timeout'       => 120,
                        'httpversion'   => '1.1',
                        'sslverify'     => true,
                        'body'          => $body
                    );

        $response       = wp_remote_post( $this->api_base_url."/api/transactions/chargeback", $args );
        $responseBody   = wp_remote_retrieve_body( $response );
        $responseBody   = json_decode( $responseBody, true );

        $response_code = wp_remote_retrieve_response_code( $response );

        if( $responseBody ){
            return $responseBody;
        }

        return false;
    }

    public function createPaymentIntent( $data ){
        ini_set("precision", 14); 
        ini_set("serialize_precision", -1);

        $api_token = $this->getToken();

        $headers = array(
                            "Content-Type"  => "application/json",
                            "Authorization"  => "Bearer ".$api_token,
                        );

        $body = json_encode($data,JSON_UNESCAPED_SLASHES);
        
        $args = array(
                        'headers'       => $headers,
                        'timeout'       => 120,
                        'httpversion'   => '1.1',
                        'sslverify'     => true,
                        'body'          => $body
                    );

        $response       = wp_remote_post( $this->api_base_url."/api/paymentIntent", $args );
        $responseBody   = wp_remote_retrieve_body( $response );
        $responseBody   = json_decode( $responseBody, true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if($response_code == 201){
            return $responseBody;
        }

        return false;
    }


    public function getTransactionById( $transactionID ){

        $api_token = $this->getToken();

        $headers = array(
                            "Authorization"  => "Bearer ".$api_token,
                        );

        $args = array(
                        'headers'       => $headers,
                        'timeout'       => 120,
                        'httpversion'   => '1.1',
                        'sslverify'     => true,
                    );

        $response       = wp_remote_get( $this->api_base_url."/api/transactions/".$transactionID, $args );
        $responseBody   = wp_remote_retrieve_body( $response );
        $responseBody   = json_decode( $responseBody, true );
        $response_code = wp_remote_retrieve_response_code( $response );
        if($response_code == 200){
            return $responseBody;
        }

        return false;
    }
}
?>