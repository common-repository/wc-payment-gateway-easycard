<?php
/*
 * Plugin Name:       Payment Gateway for EasyCard on WooCommerce
 * Plugin URI:        https://www.e-c.co.il
 * Description:       EasyCard is one of Israel's leading payment gateway.Our plugin enables you to accept all credit card types, as well as Bit payments.Built in support for multiple currencies and invoicing.
 * Version:           2.1
 * Author:            B.A Edan Technologies Ltd.
 * Author URI:        https://www.e-c.co.il
 * Requires at least: 5.0
 * Tested up to:      6.6.1
 * Text Domain:       wc-payment-gateway-easycard
 * Domain Path:       languages
 * Network:           false
 */

if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * Required functions
 */
require_once('woo-includes/woo-functions.php');

if( !class_exists( 'WC_EasyCard' ) ) {

  /**
   * WooCommerce {%EasyCard%} main class.
   *
   * @TODO    Replace 'EasyCard' with the name of your payment gateway class.
   * @class   EasyCard
   * @version 1.0.0
   */
  final class WC_EasyCard {

    public $woocommerce_currency = null;
    /**
     * Instance of this class.
     *
     * @access protected
     * @access static
     * @var object
     */
    protected static $instance = null;

    /**
     * Slug
     *
     * @TODO   Rename the $gateway_slug to match the name of the payment gateway your building.
     * @access public
     * @var    string
     */
     public $gateway_slug = 'payment_gateway_easycard';

    /**
     * Text Domain
     *
     * @TODO   Rename the $text_domain to match the name of the payment gateway your building.
     * @access public
     * @var    string
     */
    public $text_domain = 'wc-payment-gateway-easycard';

    /**
     * The EasyCard.
     *
     * @TODO   Rename the payment gateway name to the gateway your building.
     * @NOTE   Do not put WooCommerce in front of the name. It is already applied.
     * @access public
     * @var    string
     */
     public $name = "Payment Gateway EasyCard";

    /**
     * Gateway version.
     *
     * @access public
     * @var    string
     */
    public $version = '1.0.0';

    /**
     * The Gateway documentation URL.
     *
     * @TODO   Replace the url
     * @access public
     * @var    string
     */
     public $doc_url = "https://merchant.e-c.co.il";

     
    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance() {
      // If the single instance hasn't been set, set it now.
      if( null == self::$instance ) {
        self::$instance = new self;
      }

      return self::$instance;
    }

    /**
     * Throw error on object clone
     *
     * The whole idea of the singleton design pattern is that there is a single
     * object therefore, we don't want the object to be cloned.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
     public function __clone() {
       // Cloning instances of the class is forbidden
       _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wc-payment-gateway-easycard' ), $this->version );
     }

    /**
     * Disable unserializing of the class
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
     public function __wakeup() {
       // Unserializing instances of the class is forbidden
       _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wc-payment-gateway-easycard' ), $this->version );
     }

    /**
     * Initialize the plugin public actions.
     *
     * @access private
     */
    private function __construct() {
      
      $this->woocommerce_currency = get_woocommerce_currency();
      
      // Hooks.
      add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
      add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
      add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

      // Is WooCommerce activated?
      if( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        add_action('admin_notices', array( $this, 'woocommerce_missing_notice' ) );
        return false;
      }
      else{
        // Check we have the minimum version of WooCommerce required before loading the gateway.
        if( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
          if( class_exists( 'WC_Payment_Gateway' ) ) {

            $this->includes();

            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
          }
        }
        else {
          add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
          return false;
        }
      }
      add_action( 'woocommerce_api_easycard', array( $this, 'callback_handler' ) );
    }

    /**
     * Plugin action links.
     *
     * @access public
     * @param  mixed $links
     * @return void
     */
     public function action_links( $links ) {
       if( current_user_can( 'manage_woocommerce' ) ) {
         $plugin_links = array(
           '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_' . $this->gateway_slug ) . '">' . __( 'Payment Settings', 'wc-payment-gateway-easycard' ) . '</a>',
           '<a href="https://ecng-transactions.azurewebsites.net/api-docs/index.html">' . __( 'Docs', 'wc-payment-gateway-easycard' ) . '</a>',
           '<a href="https://www.e-c.co.il/contact/">' . __( 'Support', 'wc-payment-gateway-easycard' ) . '</a>',
         );
         return array_merge( $plugin_links, $links );
       }

       return $links;
     }

    /**
     * Plugin row meta links
     *
     * @access public
     * @param  array $input already defined meta links
     * @param  string $file plugin file path and name being processed
     * @return array $input
     */
     public function plugin_row_meta( $input, $file ) {
       if( plugin_basename( __FILE__ ) !== $file ) {
         return $input;
       }

       $links = array(
         '<a href="' . esc_url( $this->doc_url ) . '">' . __( 'Documentation', 'wc-payment-gateway-easycard' ) . '</a>',
       );

       $input = array_merge( $input, $links );

       return $input;
     }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any 
     * following ones if the same translation is present.
     *
     * @access public
     * @return void
     */
    public function load_plugin_textdomain() {
      // Set filter for plugin's languages directory
      $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
      $lang_dir = apply_filters( 'woocommerce_' . $this->gateway_slug . '_languages_directory', $lang_dir );

      // Traditional WordPress plugin locale filter
      $locale = apply_filters( 'plugin_locale',  get_locale(), $this->text_domain );
      $mofile = sprintf( '%1$s-%2$s.mo', $this->text_domain, $locale );

      // Setup paths to current locale file
      $mofile_local  = $lang_dir . $mofile;
      $mofile_global = WP_LANG_DIR . '/' . $this->text_domain . '/' . $mofile;

      if( file_exists( $mofile_global ) ) {
        // Look in global /wp-content/languages/plugin-name/ folder
        load_textdomain( $this->text_domain, $mofile_global );
      }
      else if( file_exists( $mofile_local ) ) {
        // Look in local /wp-content/plugins/plugin-name/languages/ folder
        load_textdomain( $this->text_domain, $mofile_local );
      }
      else {
        // Load the default language files
        load_plugin_textdomain( $this->text_domain, false, $lang_dir );
      }
    }

    /**
     * Include files.
     *
     * @access private
     * @return void
     */
    private function includes() {
      include_once( 'includes/class-wc-gateway-easycard-api.php' );
      include_once( 'includes/class-wc-gateway-easycard.php' );
    }


    /**
     * This filters the gateway to only supported currency.
     *
     * @access public
     */
    public function gateway_currency_base() {
      return apply_filters( 'woocommerce_gateway_currency_base', array( 'USD', 'ILS', 'EUR' ) );
    }


    /**
     * Add the gateway.
     *
     * @access public
     * @param  array $methods WooCommerce payment methods.
     * @return array WooCommerce {%EasyCard%} gateway.
     */
    public function add_gateway( $methods ) {
      // This checks if the gateway is supported for your country.

      if( in_array($this->woocommerce_currency, $this->gateway_currency_base())) {
          $methods[] = 'WC_Gateway_' . str_replace( ' ', '_', $this->name );
      }
      return $methods;
    }


    /**
     * WooCommerce Fallback Notice.
     *
     * @access public
     * @return string
     */
    public function woocommerce_missing_notice() {
      echo '<div class="error woocommerce-message wc-connect"><p>' . sprintf( __( 'Sorry, <strong>WooCommerce %s</strong> requires WooCommerce to be installed and activated first. Please install <a href="%s">WooCommerce</a> first.', $this->text_domain), $this->name, admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce' ) ) . '</p></div>';
    }

    /**
     * WooCommerce Payment Gateway Upgrade Notice.
     *
     * @access public
     * @return string
     */
    public function upgrade_notice() {
      echo '<div class="updated woocommerce-message wc-connect"><p>' . sprintf( __( 'WooCommerce %s depends on version 2.2 and up of WooCommerce for this gateway to work! Please upgrade before activating.', 'easycard' ), $this->name ) . '</p></div>';
    }

    /** Helper functions ******************************************************/

    /**
     * Get the plugin url.
     *
     * @access public
     * @return string
     */
    public function plugin_url() {
      return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Get the plugin path.
     *
     * @access public
     * @return string
     */
    public function plugin_path() {
      return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

   /**
     * Check the payment response.
     */
    public function callback_handler( ) {
        $raw_post = file_get_contents( 'php://input' );
        $decoded  = json_decode( $raw_post );
        
        if($_REQUEST['transactionID']){
          $transaction_response = (new WC_EasyCard_API)->getTransactionById( $_REQUEST['transactionID'] );
          if( !empty( $transaction_response['dealDetails']) ){
            $dealDetails    = $transaction_response['dealDetails'];
            $dealReference  = $dealDetails['dealReference'];
            $dealReference  = explode('-', $dealReference);
            $order_id       = $dealReference[1];

            $WC_Gateway_Payment_Gateway_EasyCard = new WC_Gateway_Payment_Gateway_EasyCard();
            $WC_Gateway_Payment_Gateway_EasyCard->thankyou_page($order_id);
          }

          $iframeHtml = '<p>Thank you for placing order using EasyCard</p>';
          echo apply_filters('easycard_iframe_response_html', $iframeHtml, $order_id );
        }
      die();
    }
  } // end if class

  // TODO: Rename 'WC_EasyCard' to the name of the gateway your building. e.g. 'WC_Gateway_PayPal'
  add_action( 'plugins_loaded', array( 'WC_EasyCard', 'get_instance' ), 0 );
  //add_action( 'woocommerce_admin_order_should_render_refunds', 'show_hide_refund_button_based_on_transaction_details_callback', 10, 3 );
} // end if class exists.

/**
 * Returns the main instance of WC_EasyCard to prevent the need to use globals.
 *
 * @return WooCommerce EasyCard
 */
function WC_EasyCard() {
  return WC_EasyCard::get_instance();
}

function show_hide_refund_button_based_on_transaction_details_callback( $boolean, $order_id, $order){
    if($order->get_payment_method() == 'easycard'){
        $ecngAllowRefund = get_post_meta( $order_id, '_ecngallowrefund', true );
        if( $ecngAllowRefund ){
            return true;
        }
        
        return false;  
    }

    return $boolean;
}
add_action('woocommerce_api_ecng_webhook', 'handle_ecng_webhook');

function handle_ecng_webhook() {
    // Get the request body
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    // Log the incoming webhook data
    error_log('ECNG Webhook Received: ' . print_r($data, true));
    if (isset($data['entityExternalReference']) || isset($data['eventName'])) {
      $order_string=$data['entityExternalReference'];
      preg_match('/easycard-(\d+)/', $order_string, $matches);
      // The order ID will be in $matches[1] and cast to an integer
      $order_id = (int) $matches[1];
      $order = wc_get_order( $order_id );
      $current_status = $order->get_status();
      if($data['entityType']=="PaymentTransaction"){
          if($data['eventName']=="TransactionCreated"){
            $new_status="processing";
          }elseif($data['eventName']=="TransactionRejected"){
            $new_status="failed";
          }else{
            $order->add_order_note($data['errorMesage']);
          }
        if ( $current_status !== $new_status ) {
            // $order->add_order_note( 'Order status changed from '.$current_status.' payment to ' . $new_status . ' by  webhook.' );
            // Change the order status
            $order->update_status( $new_status );
        }
      }
    }
      // Validate the data and process the order
    exit;
}


?>