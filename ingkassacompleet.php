<?php
/**
 * Plugin Name: ING Kassa Compleet
 * Plugin URI: https://portal.kassacompleet.nl
 * Description: Accept payments for WooCommerce with ING Kassa Compleet.
 * Version: 1.0.7
 * Author: Ginger Payments
 * Author URI: https://www.gingerpayments.com
 * License: The MIT License (MIT)
 */

add_action( 'plugins_loaded', 'woocommerce_ingkassacompleet_init', 0 );

function woocommerce_ingkassacompleet_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once(WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__)) . '/lib/ing_lib.php');

	class woocommerce_ingkassacompleet_ideal extends WC_Payment_Gateway
	{
		public function __construct() {
			global $woocommerce;

			$this->id = 'ingkassacompleet_ideal';
			$this->icon = false;
			$this->has_fields = true;
			$this->method_title = 'iDEAL - ING Kassa Compleet';
			$this->method_description = 'iDEAL - ING Kassa Compleet';

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option( 'title' );
			$settings = get_option ( 'woocommerce_ingkassacompleet_settings' );
			$this->api_key = $settings['api_key'];
			$this->enabled = $this->get_option( 'enabled' );

			$this->ing_services_lib = new Ing_Services_Lib( $this->api_key, 'file', false );
			$this->issuers = $this->ing_services_lib->ingGetIssuers();

			/* 1.6.6 */
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

			/* 2.0.0 */
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			if ($this->enabled == "yes") {
				add_action( 'woocommerce_thankyou_'. $this->id, array( &$this, 'handle_thankyou' ) );

				add_action( 'woocommerce_email_order_meta', array( &$this, 'handle_email_order_meta' ) );
			}

			add_action( 'woocommerce_api_'.strtolower( get_class( $this ) ), array( &$this, 'handle_callback' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'ingkassacompleet' ),
					'type' => 'checkbox',
					'label' => __( 'Enable iDEAL Payment', 'ingkassacompleet' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'ingkassacompleet' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'ingkassacompleet' ),
					'default' => __( 'iDEAL', 'ingkassacompleet' ),
					'desc_tip'      => true,
				),
			);
		}

		function admin_options() {
?>
 <h2><?php _e( 'Kassa Compleet iDEAL', 'ingkassacompleet' ); ?></h2>
 <table class="form-table">
 <?php $this->generate_settings_html(); ?>
 </table> <?php
		}

		public function process_payment( $order_id ) {

			if ( empty( $_POST["ideal_issuer_id"] ) ) {
                wc_add_notice(__('Fout tijdens betalen: U dient een iDEAL bank te kiezen', 'ingkassacompleet'), 'error');
                return array('result' => 'failure');
			}

			$order = new WC_Order( $order_id );

			$return_url = add_query_arg( 'wc-api', 'woocommerce_ingkassacompleet', home_url( '/' ) );
			$description = $order_id;

			$ing_order = $this->ing_services_lib->ingCreateIdealOrder( $order_id, $order->order_total, empty( $_POST["ideal_issuer_id"] ) ? null : $_POST["ideal_issuer_id"], $return_url, $description );
			update_post_meta( $order_id, 'ing_order_id', $ing_order['id'] );

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $ing_order['transactions'][0]['payment_url'],
			);
		}

		public function handle_thankyou($order_id) {
			$ing_order_id_array = get_post_custom_values( 'ing_order_id', $order_id );

			$order_details = $this->ing_services_lib->getOrderDetails( $ing_order_id_array[0] );

			if ($order_details['transactions'][0]['payment_method'] != "ideal")
				return;

			if ($order_details['status'] == "processing") {
				echo __("De status van uw iDEAL betaling kon niet bepaald worden. Controleert u uw bankafschrift om te zien of het aankoopbedrag daadwerkelijk is afgeschreven.");
			}
			
		}

		public function handle_email_order_meta($WC_Order) {
			$this->handle_thankyou( $WC_Order->id);
		}		

		public function payment_fields() {
			if ( !$this->has_fields ) {
				return;
			}
			echo '<select name="ideal_issuer_id">';
			echo '<option value="">' . __( 'Kies uw bank:', 'ingkassacompleet' ) . '</option>';
			foreach ( $this->issuers as $issuer ) {
				echo '<option value="' . $issuer['id'] . '">' . htmlspecialchars( $issuer['name'] ) . '</option>';
			}
			echo '</select>';
		}
	}

	class woocommerce_ingkassacompleet_creditcard extends WC_Payment_Gateway
	{
		public function __construct() {
			global $woocommerce;

			$this->id = 'ingkassacompleet_creditcard';
			$this->icon = false;
			$this->has_fields = false;
			$this->method_title = 'Mastercard, VISA, Maestro of V PAY - ING Kassa Compleet';
			$this->method_description = 'Mastercard, VISA, Maestro of V PAY - ING Kassa Compleet';

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option( 'title' );
			$settings = get_option ( 'woocommerce_ingkassacompleet_settings' );
			$this->api_key = $settings['api_key'];
			$this->enabled = $this->get_option( 'enabled' );

			$this->ing_services_lib = new Ing_Services_Lib( $this->api_key, 'file', false );
			$this->issuers = $this->ing_services_lib->ingGetIssuers();

			/* 1.6.6 */
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

			/* 2.0.0 */
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			if ($this->enabled == "yes") {
				add_action( 'woocommerce_thankyou_'. $this->id, array( &$this, 'handle_thankyou' ) );

				add_action( 'woocommerce_email_order_meta', array( &$this, 'handle_email_order_meta' ) );
			}

			add_action( 'woocommerce_api_'.strtolower( get_class( $this ) ), array( &$this, 'handle_callback' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'ingkassacompleet' ),
					'type' => 'checkbox',
					'label' => __( 'Enable CreditCard Payment', 'ingkassacompleet' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'ingkassacompleet' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'ingkassacompleet' ),
					'default' => __( 'Mastercard, VISA, Maestro of V PAY', 'ingkassacompleet' ),
					'desc_tip'      => true,
				),
			);
		}

		function admin_options() {
?>
 <h2><?php _e( 'Kassa Compleet CreditCard', 'ingkassacompleet' ); ?></h2>
 <table class="form-table">
 <?php $this->generate_settings_html(); ?>
 </table> <?php
		}

		public function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );

			$return_url = add_query_arg( 'wc-api', 'woocommerce_ingkassacompleet', home_url( '/' ) );
			$description = $order_id;

			$ing_order = $this->ing_services_lib->ingCreateCreditCardOrder( $order_id, $order->order_total, $return_url, $description );
			update_post_meta( $order_id, 'ing_order_id', $ing_order['id'] );

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $ing_order['transactions'][0]['payment_url'],
			);
		}

		public function handle_thankyou($order_id) {
			$ing_order_id_array = get_post_custom_values( 'ing_order_id', $order_id );

			$order_details = $this->ing_services_lib->getOrderDetails( $ing_order_id_array[0] );

			if ($order_details['transactions'][0]['payment_method'] != "credit-card")
				return;

			if ($order_details['status'] == "processing") {
				echo __("De status van uw CreditCard betaling kon niet bepaald worden. Controleert u uw Creditcard afschrift om te zien of het aankoopbedrag daadwerkelijk is afgeschreven.");
			}
			
		}

		public function handle_email_order_meta($WC_Order) {
			$this->handle_thankyou( $WC_Order->id);
		}		
	}	

	class woocommerce_ingkassacompleet_banktransfer extends WC_Payment_Gateway
	{
		public function __construct() {
			global $woocommerce;

			$this->id = 'ingkassacompleet_banktransfer';
			$this->icon = false;
			$this->has_fields = false;
			$this->method_title = 'Banktransfer - ING Kassa Compleet';
			$this->method_description = 'Banktransfer - ING Kassa Compleet';

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option( 'title' );
			$settings = get_option ( 'woocommerce_ingkassacompleet_settings' );
			$this->api_key = $settings['api_key'];

			$this->ing_services_lib = new Ing_Services_Lib( $this->api_key, 'file', false );
			$this->issuers = $this->ing_services_lib->ingGetIssuers();

			/* 1.6.6 */
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

			/* 2.0.0 */
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_thankyou_'. $this->id, array( &$this, 'handle_thankyou' ) );

			add_action( 'woocommerce_email_order_meta', array( &$this, 'handle_email_order_meta' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'ingkassacompleet' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Banktransfer Payment', 'ingkassacompleet' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'ingkassacompleet' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'ingkassacompleet' ),
					'default' => __( 'Bankoverboeking', 'ingkassacompleet' ),
					'desc_tip'      => true,
				),
			);
		}

		function admin_options() {
?>
 <h2><?php _e( 'Kassa Compleet Banktransfer', 'ingkassacompleet' ); ?></h2>
 <table class="form-table">
 <?php $this->generate_settings_html(); ?>
 </table> <?php
		}

		public function process_payment( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );

			$description = $order_id;

			$user_id = get_post_meta( $order_id, '_customer_user', true );
			$customer = array(
				'address' => get_user_meta( $user_id, 'billing_address_1', true ),
				'country' => get_user_meta( $user_id, 'billing_country', true ),
				'email_address' => get_user_meta( $user_id, 'billing_email', true ),
				'first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
				'last_name' => get_user_meta( $user_id, 'billing_last_name', true ),
				'postal_code' => get_user_meta( $user_id, 'billing_postcode', true ),
				'phone_numbers' => get_user_meta( $user_id, 'billing_phone', true ),
				);

			$ing_order = $this->ing_services_lib->ingCreateBanktransferOrder( $order_id, $order->order_total, $description, $customer );
	        $bank_reference = $ing_order['transactions'][0]['payment_method_details']['reference'];


			update_post_meta( $order_id, 'bank_reference', $bank_reference );
			update_post_meta( $order_id, 'ing_order_id', $ing_order['id'] );

			// TODO; check if order was created succesfully

			// Mark as on-hold (we're awaiting the banktransfer)
			$order->update_status('on-hold', __( 'Awaiting banktransfer payment', 'ingkassacompleet' ));

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		public function handle_thankyou($order_id) {
			$ing_order_id_array = get_post_custom_values( 'ing_order_id', $order_id );

			$order_details = $this->ing_services_lib->getOrderDetails( $ing_order_id_array[0] );

			if ($order_details['transactions'][0]['payment_method'] != "bank-transfer")
				return;

			$kenmerk_array = get_post_custom_values( 'bank_reference', $order_id );
			
			echo __("Om te betalen gebruikt u de volgende gegevens:");
			echo "<br><br>";
			echo __("Kenmerk overboeking: " . $kenmerk_array[0]);
			echo "<br>";
			echo __("IBAN: NL13INGB0005300060");
			echo "<br>";
			echo __("BIC: INGBNL2A");
			echo "<br>";
			echo __("Naam rekeninghouder: ING Bank N.V. PSP");
			echo "<br>";
			echo __("Woonplaats: Amsterdam");
		}

		public function handle_email_order_meta($WC_Order) {
			$this->handle_thankyou( $WC_Order->id);
		}		
	}

	class woocommerce_ingkassacompleet_cashondelivery extends WC_Payment_Gateway
	{
		public function __construct() {
			global $woocommerce;

			$this->id = 'ingkassacompleet_cashondelivery';
			$this->icon = false;
			$this->has_fields = false;
			$this->method_title = 'Cash on Delivery - ING Kassa Compleet';
			$this->method_description = 'Cash on Delivery - ING Kassa Compleet';

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option( 'title' );
			$settings = get_option ( 'woocommerce_ingkassacompleet_settings' );
			$this->api_key = $settings['api_key'];

			$this->ing_services_lib = new Ing_Services_Lib( $this->api_key, 'file', false );
			$this->issuers = $this->ing_services_lib->ingGetIssuers();

			/* 1.6.6 */
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

			/* 2.0.0 */
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_thankyou_'. $this->id, array( &$this, 'handle_thankyou' ) );

			add_action( 'woocommerce_email_order_meta', array( &$this, 'handle_email_order_meta' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'ingkassacompleet' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Cash on Delivery Payment', 'ingkassacompleet' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'ingkassacompleet' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'ingkassacompleet' ),
					'default' => __( 'Rembours', 'ingkassacompleet' ),
					'desc_tip'      => true,
				),
			);
		}

		function admin_options() {
?>
 <h2><?php _e( 'Kassa Compleet Cash on Delivery', 'ingkassacompleet' ); ?></h2>
 <table class="form-table">
 <?php $this->generate_settings_html(); ?>
 </table> <?php
		}

		public function process_payment( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );

			$description = $order_id;

			$user_id = get_post_meta( $order_id, '_customer_user', true );
			$customer = array(
				'address' => get_user_meta( $user_id, 'billing_address_1', true ),
				'country' => get_user_meta( $user_id, 'billing_country', true ),
				'email_address' => get_user_meta( $user_id, 'billing_email', true ),
				'first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
				'last_name' => get_user_meta( $user_id, 'billing_last_name', true ),
				'postal_code' => get_user_meta( $user_id, 'billing_postcode', true ),
				'phone_numbers' => get_user_meta( $user_id, 'billing_phone', true ),
				);

			$ing_order = $this->ing_services_lib->ingCreateCashondeliveryOrder( $order_id, $order->order_total, $description, $customer );

			update_post_meta( $order_id, 'ing_order_id', $ing_order['id'] );

			// Mark as on-hold (we're awaiting the cashondelivery)
			$order->update_status('on-hold', __( 'Awaiting Cash On Delivery payment', 'ingkassacompleet' ));

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		public function handle_thankyou($order_id) {
			$ing_order_id_array = get_post_custom_values( 'ing_order_id', $order_id );

			$order_details = $this->ing_services_lib->getOrderDetails( $ing_order_id_array[0] );

			if ($order_details['transactions'][0]['payment_method'] != "cash-on-delivery")
				return;
		
			echo __("Dank voor uw bestelling. Uw bestelling wordt zo snel mogelijk verstuurd. U dient te betalen aan de postbode.");
		}

		public function handle_email_order_meta($WC_Order) {
			$this->handle_thankyou( $WC_Order->id);
		}		
	}

	class woocommerce_ingkassacompleet extends WC_Payment_Gateway
	{
		public function __construct() {

			$this->id = 'ingkassacompleet';
			$this->icon = false;
			$this->has_fields = false;
			$this->method_title = 'ING Kassa Compleet';
			$this->method_description = 'ING Kassa Compleet';

			$this->init_form_fields();
			$this->init_settings();

			$this->api_key = $this->get_option( 'api_key' );
			// we don't want it displayed in the checkout; we only use it to set the API key and for the webhook callback
			$this->enabled = false;

			$this->ing_services_lib = new Ing_Services_Lib( $this->api_key, 'file', false );

			/* 1.6.6 */
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

			/* 2.0.0 */
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_api_'.strtolower( get_class( $this ) ), array( &$this, 'handle_callback' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'api_key' => array(
					'title' => __( 'API key', 'ingkassacompleet' ),
					'type' => 'text',
					'description' => __( 'API key provided by Kassa Compleet', 'ingkassacompleet' ),
				)
			);
		}

		function admin_options() {
?>
 <h2><?php _e( 'Kassa Compleet', 'ingkassacompleet' ); ?></h2>
 <table class="form-table">
 <?php $this->generate_settings_html(); ?>
 </table> <?php
		}		
		
		public function handle_callback() {

			// callback will be used both in webhook as in return
			if ( !empty( $_GET['order_id'] ) ) {
				$type = "return";
				$ing_order_id = $_GET['order_id'];
			} else {
				$type = "webhook";
				$input = json_decode( file_get_contents( "php://input" ), true );
				if ( !in_array( $input['event'], array( "status_changed" ) ) )
					die( "Only work to do if the status changed" );
				$ing_order_id = $input['order_id'];
			}

			// check the order
			$order_details = $this->ing_services_lib->getOrderDetails( $ing_order_id );
			$order = new WC_Order( $order_details['merchant_order_id'] );

			// update the order if we are in "webhook mode"
			if ( $type == "webhook" ) {
				if ( $order_details['status'] == "completed" ) {
					if ( !$order->needs_payment() ) {
						// die( 'Already paid' );
					}
					$woo_version = get_option( 'woocommerce_version', 'Unknown' );
					if ( version_compare( $woo_version, '2.2.0', '>=' ) ) {
						$order->payment_complete( $ing_order_id );
					}
					else {
						$order->payment_complete();
					}
				}
				die();
			}

			if ( $order_details['status'] == "completed" || $order_details['status'] == "processing" ) {
				header( "Location: " . $this->get_return_url( $order ) );
				die();
			} else {
				header( "Location: " . str_replace("&amp;", "&", $order->get_cancel_order_url() ) );
				die();
			}
		}
	}

	function woocommerce_add_ingkassacompleet( $methods ) {
		$methods[] = 'woocommerce_ingkassacompleet';
		$methods[] = 'woocommerce_ingkassacompleet_ideal';
		$methods[] = 'woocommerce_ingkassacompleet_banktransfer';
		$methods[] = 'woocommerce_ingkassacompleet_creditcard';
		$methods[] = 'woocommerce_ingkassacompleet_cashondelivery';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_ingkassacompleet' );
}