<?php
if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
* WooCommerce EasyCard.
*
* @class   WC_Gateway_Payment_Gateway_EasyCard
* @extends WC_Payment_Gateway
* @version 1.0.0
* @package WooCommerce Payment Gateway EasyCard/Includes
* @author  BrightnessGroup
*/
class WC_Gateway_Payment_Gateway_EasyCard extends WC_Payment_Gateway {
    public $credit_fields  = false;
    public $notify_url;
    public $api_endpoint = 'https://api.payment-gateway.com/';
    public $instructions;
    public $payment_mode;
    public $deal_type;
    public $language;
    public $sandbox;
    public $private_key;
    public $debug;
    public $log;
    /**
    * Constructor for the gateway.
    *
    * @access public
    * @return void
    */
    public function __construct() {
        $this->id                 = 'easycard';
        $this->icon               = apply_filters( 'woocommerce_payment_gateway_easycard_icon', (!empty($this->get_option( 'icon' ))) ? $this->get_option( 'icon' ) :plugins_url( '/assets/images/cards.png', dirname( __FILE__ ) ) );
        $this->has_fields         = false;
        $this->credit_fields      = false;

        $this->order_button_text  = __( 'Pay with EasyCard', 'wc-payment-gateway-easycard' );

        $this->method_title       = __( 'EasyCard', 'wc-payment-gateway-easycard' );
        $this->method_description = __( 'Take payments via EasyCard.', 'wc-payment-gateway-easycard' );

        // TODO: Rename 'WC_Gateway_Payment_Gateway_EasyCard' to match the name of this class.
        $this->notify_url         = WC()->api_request_url( 'WC_Gateway_Payment_Gateway_EasyCard' );

        // TODO: 
        $this->api_endpoint       = 'https://api.payment-gateway.com/';

        // TODO: Use only what the payment gateway supports.
        $this->supports           = array(
            'products',
            'refunds'
        );

        // TODO: Replace the transaction url here or use the function 'get_transaction_url' at the bottom.
        $this->view_transaction_url = 'https://merchant.e-c.co.il/transactions/view/'; // Transaction URL for live mode
        
        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.
        $this->enabled        = $this->get_option( 'enabled' );

        $this->title          = $this->get_option( 'title' );
        $this->description    = $this->get_option( 'description' );
        $this->instructions   = $this->get_option( 'instructions' );
        $this->payment_mode   = $this->get_option( 'payment_mode' );
        $this->deal_type      = $this->get_option( 'deal_type' );
        $this->language       = $this->get_option( 'language' );
        $this->sandbox        = $this->get_option( 'sandbox' );
        $this->private_key    = $this->sandbox == 'no' ? $this->get_option( 'private_key' ) : $this->get_option( 'sandbox_private_key' );
        
        define( 'SANDBOX', $this->sandbox );
        define( 'PRIVATEKEY', $this->private_key );

        $this->debug          = $this->get_option( 'debug' );

        // Logs.
        if( $this->debug == 'yes' ) {
            if( class_exists( 'WC_Logger' ) ) {
                $this->log = new WC_Logger();
            }
            else {
                $this->log = $woocommerce->logger();
            }
        }

        $this->init_gateway_sdk();

        // Hooks.
        if( is_admin() ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            add_action( 'admin_notices', array( $this, 'checks' ) );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

        // Customer Emails.
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    /**
    * Init Payment Gateway SDK.
    *
    * @access protected
    * @return void
    */
    protected function init_gateway_sdk() {
    // TODO: Insert your gateway sdk script here and call it.
    }

    /**
    * Admin Panel Options
    * - Options for bits like 'title' and availability on a country-by-country basis
    *
    * @access public
    * @return void
    */
    public function admin_options() {
        include_once( WC_EasyCard()->plugin_path() . '/includes/admin/views/admin-options.php' );
    }


    /**
    * Check if SSL is enabled and notify the user.
    *
    * @TODO:  Use only what you need.
    * @access public
    */
    public function checks() {
        if( $this->enabled == 'no' ) {
            return;
        }

        // PHP Version.
        if( version_compare( phpversion(), '5.3', '<' ) ) {
            echo '<div class="error"><p>' . sprintf( __( 'EasyCard Error: EasyCard requires PHP 5.3 and above. You are using version %s.', 'wc-payment-gateway-easycard' ), phpversion() ) . '</p></div>';
        }

        // Check required fields.
        //else if( !$this->public_key || !$this->private_key ) {
        else if( !$this->private_key ) {
            echo '<div class="error"><p>' . __( 'EasyCard Error: Please enter your public and private keys', 'wc-payment-gateway-easycard' ) . '</p></div>';
        }

        // Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
        else if( 'no' == get_option( 'woocommerce_force_ssl_checkout' ) && !class_exists( 'WordPressHTTPS' ) ) {
            echo '<div class="error"><p>' . sprintf( __( 'EasyCard is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - EasyCard will only work in sandbox mode.', 'wc-payment-gateway-easycard'), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
        }
    }


    /**
    * Check if this gateway is enabled.
    *
    * @access public
    */
    public function is_available() {
        if( $this->enabled == 'no' ) {
            return false;
        }

        if( !is_ssl() && 'yes' != $this->sandbox ) {
            return false;
        }

        //if( !$this->public_key || !$this->private_key ) {
        if( !$this->private_key ) {
            return false;
        }

        return true;
    }


    /**
    * Initialise Gateway Settings Form Fields
    *
    * The standard gateway options have already been applied. 
    * Change the fields to match what the payment gateway your building requires.
    *
    * @access public
    */
    public function init_form_fields() {
        $site_url = get_site_url(); // Get the site URL dynamically

        $this->form_fields = array(
            'webhook_url' => array(
                'title'       => __( 'Webhook URL', 'wc-payment-gateway-easycard' ),
                'type'        => 'text',
                'description' => __( 'Copy this URL to configure your webhook in the EasyCard dashboard.', 'wc-payment-gateway-easycard' ),
                'default'     => $site_url . '/wc-api/ecng_webhook', // Set the default as the webhook URL
                'custom_attributes' => array(
                    'readonly' => 'readonly', // Make the field read-only
                ),
                'desc_tip'    => true
            ),
            
            'webhook_docs' => array(
                'title'       => __( 'Webhook Documentation', 'wc-payment-gateway-easycard' ),
                'type'        => 'title', // This creates a text section without an input field
                'description' => __( 'For more details, refer to the <a href="https://github.com/EasycardNG/API/blob/main/Webhooks.md" target="_blank">GitHub Webhook Documentation</a>.', 'wc-payment-gateway-easycard' ),
            ),

            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'wc-payment-gateway-easycard' ),
                'label'       => __( 'Enable EasyCard', 'wc-payment-gateway-easycard' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),

            'title' => array(
                'title'       => __( 'Title', 'wc-payment-gateway-easycard' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wc-payment-gateway-easycard' ),
                'default'     => __( 'EasyCard', 'wc-payment-gateway-easycard' ),
                'desc_tip'    => true
            ),

            'description' => array(
                'title'       => __( 'Description', 'wc-payment-gateway-easycard' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wc-payment-gateway-easycard' ),
                'default'     => 'Pay with EasyCard.',
                'desc_tip'    => true
            ),

            'icon' => array(
                'title'       => __( 'Logo', 'wc-payment-gateway-easycard' ),
                'type'        => 'url',
                'description' => __( 'Put image url that need to be used as a logo' ),
                'desc_tip'    => true
            ),

            'instructions' => array(
                'title'       => __( 'Instructions', 'wc-payment-gateway-easycard' ),
                'type'        => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-payment-gateway-easycard' ),
                'default'     => '',
                'desc_tip'    => true,
            ),

          'payment_mode' => array(
                'title'       => __( 'Payment Mode', 'wc-payment-gateway-easycard' ),
                'type'        => 'select',
                'description' => __( 'Type of mode (Redirect, Iframe) to use to collect payment.', 'wc-payment-gateway-easycard' ),
                'default'     => 'redirect',
                'options' => array(
                                  'redirect' => 'Redirect',
                                  'iframe' => 'IFrame'
                             ),
                'desc_tip'    => true,
            ),
            'deal_type' => array(
                'title'       => __( 'Deal Type', 'wc-payment-gateway-easycard' ),
                'type'        => 'select',
                'description' => __( 'Type of Deal (J4, J5)', 'wc-payment-gateway-easycard' ),
                'default'     => 'J4',
                'options' => array(
                                  'J4' => 'J4',
                                  'J5' => 'J5'
                             ),
                'desc_tip'    => true,
            ),

          'language' => array(
                'title'       => __( 'Checkout page language', 'wc-payment-gateway-easycard' ),
                'type'        => 'select',
                'description' => __( 'Default language to display checkout page', 'wc-payment-gateway-easycard' ),
                'default'     => 'en',
                'options' => array(
                                  'en' => 'English',
                                  'he' => 'Hebrew'
                             ),
                'desc_tip'    => true,
            ),

            'debug' => array(
                'title'       => __( 'Debug Log', 'wc-payment-gateway-easycard' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'wc-payment-gateway-easycard' ),
                'default'     => 'no',
                'description' => sprintf( __( 'Log Gateway name events inside <code>%s</code>', 'wc-payment-gateway-easycard' ), wc_get_log_file_path( $this->id ) )
            ),

            'sandbox' => array(
                'title'       => __( 'Sandbox', 'wc-payment-gateway-easycard' ),
                'label'       => __( 'Enable Sandbox Mode', 'wc-payment-gateway-easycard' ),
                'type'        => 'checkbox',
                'description' => __( 'Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'wc-payment-gateway-easycard' ),
                'default'     => 'yes'
            ),

            'sandbox_private_key' => array(
                'title'       => __( 'Sandbox Private Key', 'wc-payment-gateway-easycard' ),
                'type'        => 'text',
                'description' => __( 'Get your API keys from your EasyCard account.', 'wc-payment-gateway-easycard' ),
                'default'     => '',
                'desc_tip'    => true
            ),

            'private_key' => array(
                'title'       => __( 'Private Key', 'wc-payment-gateway-easycard' ),
                'type'        => 'text',
                'description' => __( 'Get your API keys from your EasyCard account.', 'wc-payment-gateway-easycard' ),
                'default'     => '',
                'desc_tip'    => true
            ),
        );
    }


    /**
    * Output for the order received page.
    *
    * @access public
    * @return void
    */
    public function receipt_page( $order_id ) {

        ini_set("precision", 14); 
        ini_set("serialize_precision", -1);    

        $order = new WC_Order( $order_id );
        $dealDescription = $line_items = array();

        foreach ($order->get_items() as $item_id => $item ) {
            // Get an instance of corresponding the WC_Product object
            $dealDescription[] = $item->get_name();
			$product_id = $item->get_product_id();
            $product_code = get_post_meta($product_id, '_product_code', true);

            array_push($line_items,array(
                "externalReference"     =>  $product_code,
                "woocommerceID"         =>  $item->get_product_id(),
                "itemName"              =>  $item->get_name(),
                "sku"                   =>  $item->get_product()->get_sku(),
                "netDiscount"           =>  number_format( floatval($item->get_subtotal() - $item->get_total()), 2 ),
                "netPrice"              =>  number_format( floatval($item->get_subtotal())/$item->get_quantity(), 2 ),
                "quantity"              =>  $item->get_quantity(),
                "netAmount"             =>  number_format( floatval( $item->get_total() ), 2 ),
                "amount"                =>  number_format( floatval( $item->get_total() + $item->get_total_tax() ), 2 ),
                "vat"                   =>  number_format( $item->get_total_tax(), 2 ),
            ));
        }

        if( $order->has_shipping_address() ) {
            array_push($line_items,array(
                "itemName"              =>  $order->get_shipping_method(),
                "netPrice"              =>  number_format( floatval( $order->calculate_shipping() ), 2 ),
                "quantity"              =>  1,
                "amount"                =>  number_format( floatval( $order->calculate_shipping() + $order->get_shipping_tax() ), 2 ),
                "vat"                   =>  number_format( $order->get_shipping_tax(), 2 ),
            ));
        }

        $data = array(
            'currency'    =>$order->get_currency(), //(USD, ILS, EURO)
            'dueDate'     =>NULL,
            'dealDetails' => array(
                'dealReference'         => 'easycard-'.$order_id,
                'dealDescription'       => implode(', ', $dealDescription),
                'consumerEmail'         => $order->get_billing_email(),
                'consumerName'          => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
                'consumerPhone'         => $order->get_billing_phone(),
                'consumerAddress'       => array(
                                            'countryCode'   => $order->get_billing_country(),
                                            'city'          => $order->get_billing_city(),
                                            'zip'           => $order->get_billing_postcode(),
                                            'street'        => $order->get_billing_address_1() . " " .$order->get_billing_address_2(),
                                        ),
                
            ),
            'language'                  =>  $this->language,
            //'redirectUrl'               =>  stripslashes(str_replace('http://', 'https://', $this->get_return_url( $order ))),
            'redirectUrl'               => str_replace('www.','',$this->get_return_url( $order )),
            'paymentRequestAmount'      =>  number_format( floatval( $order->get_total() ), 2 ),
            'netDiscountTotal'          =>  number_format( floatval( $order->get_discount_total() ) , 2),
            'userAmount'                =>  false, //when set to true allows user to change amount on checkout page
            'consumerWoocommerceID'     =>  $order->get_user_id(), //User ID in WordPress/WooCommerce
        );

        if('iframe' == $this->payment_mode ){
            $data['redirectUrl']    =   stripslashes(str_replace('http://', 'https://', site_url()."/wc-api/easycard"));
        }

        $data['dealDetails']['items'] = $line_items;

        $data['vatRate']    = 0;
        $data['vatTotal']   = 0;

        $data['vatTotal']   = $order->get_total_tax();
        $get_taxes = $order->get_taxes();
        foreach ($get_taxes as $id => $WC_Order_Item_Tax ) {
            $rate_percent       = get_metadata( 'order_item', $id, 'rate_percent', true );
            $data['vatRate']    = $rate_percent / 100; 
        }

        $data['couponCodes'] = $order->get_coupon_codes();

        if(empty($this->deal_type)) {
            $data['jDealType'] = 'J4';
        }else{
            $data['jDealType'] = $this->deal_type;
        }

        $response = (new WC_EasyCard_API)->createPaymentIntent( $data );

        if( $this->debug == 'yes' ) {
            $this->log->add( $this->id, 'Json Payload: ' . print_r( json_encode($data,JSON_UNESCAPED_SLASHES), true ) );
            $this->log->add( $this->id, 'EasyCard paymentIntent argument: ' . print_r( $data, true ) . ')' );
            $this->log->add( $this->id, 'EasyCard paymentIntent response: ' . print_r( $response, true ) . ')' );
        }

        if( isset ($response['additionalData']['url']) ){

            $order->add_order_note( __( 'EasyCard payment Redirection', 'wc-payment-gateway-easycard' ) );

            $order->add_order_note( sprintf( __( 'EasyCard payment URL: %s', 'wc-payment-gateway-easycard' ), $response['additionalData']['url'] ) );

            if( $this->debug == 'yes' ) {

                $this->log->add( $this->id, 'EasyCard payment URL : ' . $response['additionalData']['url'] );

            }

            if('redirect' == $this->payment_mode ){

                wp_redirect( $response['additionalData']['url'] );
                exit;    

            }else if('iframe' == $this->payment_mode ){

                echo '<iframe sandbox="allow-same-origin allow-scripts allow-popups allow-forms" src="'.esc_url($response['additionalData']['url']).'" height="1000px" width="100%" style="border:none;"></iframe>';    

            }else{

                echo '<p>' . esc_attr__( 'Something wrong with the API Response. Please contact admin.', 'wc-payment-gateway-easycard' ) . '</p>';    

            }
        }else{

            echo '<p>' . esc_attr__( 'Something wrong with the API. Please contact admin.', 'wc-payment-gateway-easycard' ) . '</p>';

        }

    }


    /**
    * Payment form on checkout page.
    *
    * @TODO:  Use this function to add credit card 
    *         and custom fields on the checkout page.
    * @access public
    */
    public function payment_fields() {
        $description = $this->get_description();

        if( $this->sandbox == 'yes' ) {
            $description .= ' ' . esc_attr__( 'TEST MODE ENABLED.' );
        }

        if( !empty( $description ) ) {
            echo wp_kses_data( $description );
        }

        // If credit fields are enabled, then the credit card fields are provided automatically.
        if( $this->credit_fields ) {
            $this->credit_card_form(
                array( 
                'fields_have_names' => false
                )
            );
        }

        // This includes your custom payment fields.
        include_once( WC_EasyCard()->plugin_path() . '/includes/views/html-payment-fields.php' );

    }


    /**
    * Outputs scripts used for the payment gateway.
    *
    * @access public
    */
    public function payment_scripts() {
        if( !is_checkout() || !$this->is_available() ) {
            return;
        }

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        // TODO: Enqueue your wp_enqueue_script's here.

    }



    /**
    * Output for the order received page.
    *
    * @access public
    */
    public function thankyou_page( $order_id ) {

        $payment_id = get_post_meta( $order_id, '_transaction_id', true );

        if( isset($_GET['transactionID']) && !empty( $_GET['transactionID'] ) && empty( $payment_id ) ){

            $transaction_response = (new WC_EasyCard_API)->getTransactionById( $_GET['transactionID'] );

            if( isset( $transaction_response ) && !empty( $transaction_response['dealDetails'] ) ){
                if( 
                    !empty($transaction_response['dealDetails']['dealReference']) && 
                    'easycard-'.$order_id === $transaction_response['dealDetails']['dealReference'] 
                ){
                    $this->manage_transaction_response( $order_id, $transaction_response );
                }
            }
        }
    }


    /*
    *Add/Update order data after redirect from tr 
    *
    *
    */
    public function manage_transaction_response( $order_id = '', $transaction_response = array() ){
        $order = new WC_Order( $order_id );
        
        // Payment complete.
        $order->payment_complete();

        // Store the transaction ID for WC 2.2 or later.
        add_post_meta( $order_id, '_transaction_id', $transaction_response['paymentTransactionID'], true );
        $order->update_meta_data('_allowrefund', $transaction_response['allowRefund']);
        

        // Add order note.
        $order->add_order_note( sprintf( __( 'EasyCard payment approved (ID: %s)', 'wc-payment-gateway-easycard' ), $transaction_response['paymentTransactionID'] ) );

        if( $this->debug == 'yes' ) {
            $this->log->add( $this->id, 'EasyCard payment approved (ID: ' . $transaction_response['paymentTransactionID'] . ')' );
        }

        // Reduce stock levels.
        $result = wc_reduce_stock_levels($order_id); 

        if( $this->debug == 'yes' ) {
            $this->log->add( $this->id, 'Stocked reduced.' );
        }

        // Remove items from cart.
        WC()->cart->empty_cart();

        if( $this->debug == 'yes' ) {
            $this->log->add( $this->id, 'Cart emptied.' );
        }

        $order->save();
    }



    /**
    * Add content to the WC emails.
    *
    * @access public
    * @param  WC_Order $order
    * @param  bool $sent_to_admin
    * @param  bool $plain_text
    */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if( !$sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
            if( !empty( $this->instructions ) ) {
                echo wp_kses_data( $this->instructions );
            }
        }
    }


    /**
    * Process the payment and return the result.
    *
    * @TODO   You will need to add payment code inside.
    * @access public
    * @param  int $order_id
    * @return array
    */
    public function process_payment( $order_id ) {
        $order = new WC_Order( $order_id );

        // This array is used just for demo testing a successful transaction.
        // $payment = array( 
        //     'id'     => 123,
        //     'status' => 'FAIL'
        // );

        if( $this->debug == 'yes' ) {
            $this->log->add( $this->id, 'EasyCard payment response: ' . print_r( $payment, true ) . ')' );
        }

        /**
         * TODO: Call your gateway api and return it's results.
         * e.g. return the the payment status and if 'APPROVED' 
         * then WooCommerce will complete the order.
         */
        if( 'APPROVED' == $payment['status'] ) {
            // Payment complete.
            $order->payment_complete();

            // Store the transaction ID for WC 2.2 or later.
            add_post_meta( $order->id, '_transaction_id', $payment['id'], true );

            // Add order note.
            $order->add_order_note( sprintf( __( 'EasyCard payment approved (ID: %s)', 'wc-payment-gateway-easycard' ), $payment['id'] ) );

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'EasyCard payment approved (ID: ' . $payment['id'] . ')' );
            }

            // Reduce stock levels.
            $order->reduce_order_stock();

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'Stocked reduced.' );
            }

            // Remove items from cart.
            WC()->cart->empty_cart();

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'Cart emptied.' );
            }

            // Return thank you page redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }
        else {
            // Add order note.
            $order->add_order_note( __( 'EasyCard payment pending', 'wc-payment-gateway-easycard' ) );

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'EasyCard payment pending' );
            }

          // Return message to customer.
          return array(
                'result'   => 'success',
                'message'  => '',
                'redirect' => add_query_arg(
                        'easycard',
                        $order->get_id(),
                        add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true))
                    )
            );
        }
    }

    
    /**
        * Process refunds.
        * WooCommerce 2.2 or later
        *
        * @access public
        * @param  int $order_id
        * @param  float $amount
        * @param  string $reason
        * @return bool|WP_Error
    */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $order      = new WC_Order( $order_id );
        $payment_id = get_post_meta( $order_id, '_transaction_id', true );
        $response   = ( new WC_EasyCard_API)->refund( $amount, $payment_id );

        if( 'success' == $response['status'] ) {

            // Mark order as refunded
            $order->update_status( 'refunded', __( 'Payment refunded via EasyCard.', 'wc-payment-gateway-easycard' ) );

            $order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'wc-payment-gateway-easycard' ), $amount, $response['entityReference'] ) );

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'EasyCard order #' . $order_id . ' refunded successfully!' );
            }
            return true;
        } else {

            $order->add_order_note( __( $response['message'], 'wc-payment-gateway-easycard' ) );

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'Error in refunding the order #' . $order_id . '. EasyCard response: ' . print_r( $response, true ) );
            }

            return false;
        }
    }


    /**
        * Get the transaction URL.
        *
        * @TODO   Replace both 'view_transaction_url'\'s. 
        *         One for sandbox/testmode and one for live.
        * @param  WC_Order $order
        * @return string
    */
    public function get_transaction_url( $order ) {
        if( $this->sandbox == 'yes' ) {
            $this->view_transaction_url = 'https://ecng-profile.azurewebsites.net/transactions/view/%s';
        }
        else {
            /*
            Transaction URL for live mode
            */
            $this->view_transaction_url = 'https://merchant.e-c.co.il/transactions/view/%s';
        }

        return parent::get_transaction_url( $order );
    }

} // end class.
?>