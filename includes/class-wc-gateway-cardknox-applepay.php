<?php
/*
Copyright © 2018 Cardknox Development Inc. All rights reserved.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WC_Gateway_Cardknox class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Cardknox_ApplePay extends WC_Payment_Gateway_CC
{

	/**
	 * Should we capture Credit cards
	 *
	 * @var bool
	 */
	public $capture;
	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;
	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $transaction_key;
	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $token_key;
	/**
	 * Logging enabled?
	 *
	 * @var bool
	 */
	public $logging;
	public $authonly_status;

	public function __construct()
	{
		$this->id                   = 'cardknox-applepay';
		$this->method_title         = __('Cardknox', 'woocommerce-gateway-cardknox');
		$this->title 				= __('Cardknox', 'woocommerce-other-payment-gateway');
		$this->method_description   = sprintf(__('<strong class="important-label" style="color: #e22626;">Important: </strong>Please complete the Apple Pay Domain Registration <a target="_blank" href="https://portal.cardknox.com/account-settings/payment-methods">here</a> prior to enabling Cardknox Apple Pay.', 'woocommerce-gateway-cardknox'), 'https://www.cardknox.com');
		$this->has_fields           = true;
		$this->view_transaction_url = 'https://portal.cardknox.com/transactions?referenceNumber=%s';
		$this->supports             = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change', // Subs 1.n compatibility.
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
			//'pre-orders',
			//'tokenization',
			//'add_payment_method'
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->enabled 							= $this->get_option('applepay_enabled');
		$this->title        					= $this->get_option('applepay_title');
		$this->description             			= __('Pay with your apple card.', 'woocommerce-gateway-cardknox');
		$this->applepay_merchant_identifier     = $this->get_option('applepay_merchant_identifier');
		$this->applepay_environment        		= $this->get_option('applepay_environment');
		$this->applepay_button_style        	= $this->get_option('applepay_button_style');
		$this->applepay_button_type        		= $this->get_option('applepay_button_type');
		$this->capture        					= $this->get_option('applepay_capture');
		$this->authonly_status					= $this->get_option('applepay_auth_only_order_status');
		$this->applepay_applicable_countries    = $this->get_option('applepay_applicable_countries');
		$this->applepay_specific_countries      = $this->get_option('applepay_specific_countries');
		
		// Hooks.
		add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
		//add_action('admin_notices', array($this, 'admin_notices'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_action(	'woocommerce_review_order_after_submit', array($this, 'cardknox_review_order_after_submit') );
		add_filter(	'woocommerce_available_payment_gateways', array($this, 'cardknox_allow_payment_method_by_country') );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields()
	{
		if ($this->description) {
			echo apply_filters('wc_cardknox_description', wpautop(wp_kses_post($this->description)));
		}
	?>
		<input type="hidden" name="xCardNumToken" value="" id="applePaytoken">
	<?php
	}
	/**
	 * Get Cardknox amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public function get_cardknox_amount($total, $currency = '')
	{
		if (!$currency) {
			$currency = get_woocommerce_currency();
		}
		switch (strtoupper($currency)) {
			// Zero decimal currencies.
			case 'BIF':
			case 'CLP':
			case 'DJF':
			case 'GNF':
			case 'JPY':
			case 'KMF':
			case 'KRW':
			case 'MGA':
			case 'PYG':
			case 'RWF':
			case 'VND':
			case 'VUV':
			case 'XAF':
			case 'XOF':
			case 'XPF':
				$total = absint($total);
				break;
			default:
				// In cents.
				break;
		}
		return $total;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields()
	{
		$this->form_fields = include_once('settings-cardknox-applepay.php');
	}

	/**
	 * Localize Cardknox messages based on code
	 *
	 * @since 3.0.6
	 * @version 3.0.6
	 * @return array
	 */
	public function get_localized_messages()
	{
		return apply_filters('wc_cardknox_localized_messages', array());
	}
	
	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for cardknox payment
	 *
	 * @access public
	 */
	public function payment_scripts()
	{
		if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page()) {
			return;
		}

		wp_enqueue_script('woocommerce_cardknox_apple_pay', plugins_url('assets/js/cardknox-apple-pay.min.js', WC_CARDKNOX_MAIN_FILE), array('jquery-payment'), filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/cardknox-apple-pay.min.js'), true);

		$cardknox_applepay_settings = array(
			'enabled'     			=> $this->enabled,
			'title'           		=> $this->title,
			'merchant_identifier' 	=> $this->applepay_merchant_identifier,
			'environment'			=> $this->applepay_environment,
			'button_style'			=> $this->applepay_button_style,
			'button_type'			=> $this->applepay_button_type,
			'payment_action'		=> $this->applepay_payment_action,
			'applicable_countries'	=> $this->applepay_applicable_countries,
			'specific_countries'	=> $this->applepay_specific_countries,
			'total'					=> WC()->cart->total
		);

		$cardknox_applepay_settings = array_merge($cardknox_applepay_settings, $this->get_localized_messages());
		wp_localize_script('woocommerce_cardknox_apple_pay', 'applePaysettings', $cardknox_applepay_settings);
	}

	/**
	 * Generate the request for the payment.
	 * @param  WC_Order $order
	 * @param  object $source
	 * @return array()
	 */
	protected function generate_payment_request($order)
	{
		$post_data                = array();
		$post_data['xCommand']    = $this->capture ? 'cc:sale' : 'cc:authonly';

		$post_data = self::get_order_data($post_data, $order);
		$post_data = self::get_billing_shiping_info($post_data, $order);
		$post_data = self::get_payment_data($post_data);

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 3.1.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters('wc_cardknox_generate_payment_request', $post_data, $order);
	}

	public function get_order_data($post_data, $order)
	{
		$billing_email    = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_email : $order->get_billing_email();
		$post_data['xCurrency']    = strtolower(version_compare(WC_VERSION, '3.0.0', '<') ? $order->get_order_currency() : $order->get_currency());
		$post_data['xAmount']      = $this->get_cardknox_amount($order->get_total());
		$post_data['xEmail'] = $billing_email;
		$post_data['xInvoice'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();
		$post_data['xIP'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->customer_ip_address : $order->get_customer_ip_address();

		if (!empty($billing_email) && apply_filters('wc_cardknox_send_cardknox_receipt', false)) {
			$post_data['xCustReceipt'] = '1';
		}
		return $post_data;
	}

	public function get_payment_data($post_data)
	{
		if (isset($_POST['xCardNumToken'])) {
			
			$post_data['xCardNum'] 				= wc_clean($_POST['xCardNumToken']);
			$post_data['xAmount'] 				= WC()->cart->total;
			$post_data['xDigitalWalletType'] 	= 'applepay';

		}
		return $post_data;
	}

	public function get_billing_shiping_info($post_data, $order)
	{
		$post_data['xBillCompany'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_company : $order->get_billing_company();
		$post_data['xBillFirstName'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_first_name : $order->get_billing_first_name();
		$post_data['xBillLastName']  = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_last_name : $order->get_billing_last_name();
		$post_data['xBillStreet'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_address_1 : $order->get_billing_address_1();
		$post_data['xBillStreet2'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_address_2 : $order->get_billing_address_2();
		$post_data['xBillCity'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_city : $order->get_billing_city();
		$post_data['xBillState'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_state : $order->get_billing_state();
		$post_data['xBillZip'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_postcode : $order->get_billing_postcode();
		$post_data['xBillCountry'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_country : $order->get_billing_country();
		$post_data['xBillPhone'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_phone : $order->get_billing_phone();

		$post_data['xShipCompany'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_company : $order->get_shipping_company();
		$post_data['xShipFirstName'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_first_name : $order->get_shipping_first_name();
		$post_data['xShipLastName'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_last_name : $order->get_shipping_last_name();
		$post_data['xShipStreet'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_address_1 : $order->get_shipping_address_1();
		$post_data['xShipStreet2'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_address_2 : $order->get_shipping_address_2();
		$post_data['xShipCity'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_city : $order->get_shipping_city();
		$post_data['xShipState'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_state : $order->get_shipping_state();
		$post_data['xShipZip'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_postcode : $order->get_shipping_postcode();
		$post_data['xShipCountry'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_country : $order->get_shipping_country();
		return $post_data;
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_customer Force user creation.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			$order  = wc_get_order( $order_id );

			// Result from Cardknox API request.
			$response = null;

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() < WC_Cardknox::get_minimum_amount() / 100) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-cardknox' ), wc_price( WC_Cardknox::get_minimum_amount() / 100 ) ) );
				}

				$this->log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Make the request.
				$response = WC_Cardknox_API::request( $this->generate_payment_request( $order ) );

				if ( is_wp_error( $response ) ) {
					$order->add_order_note($response->get_error_message());
					throw new Exception( "The transaction was declined please try again" );
				}

				$this->log( "Info: set_transaction_id");
				$order->set_transaction_id($response['xRefNum']);

				// Process valid response.
				$this->log( "Info: process_response");
				$this->process_response( $response, $order );
			} else {			
				$order->payment_complete();
			}

			$this->log( "Info: empty_cart");

			// Remove cart.
			WC()->cart->empty_cart();

			$this->log( "Info: wc_gateway_cardknox_process_payment");
			do_action( 'wc_gateway_cardknox_process_payment', $response, $order );

			$this->log( "Info: thank you page redirect");
			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			$this->log( sprintf( __( 'Error: %s', 'woocommerce-gateway-cardknox' ), $e->getMessage() ) );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );

				$order_status = $order->get_status();    
				if ('pending' == $order_status) {    
					$order->update_status( 'failed' );
				} 
			}

			do_action( 'wc_gateway_cardknox_process_payment_error', $e, $order );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Store extra meta data for an order from a Cardknox Response.
	 */
	public function process_response($response, $order)
	{
		$order_id = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();

		// Store charge data
		update_post_meta($order_id, '_cardknox_xrefnum', $response['xRefNum']);
		update_post_meta($order_id, '_cardknox_transaction_captured', $this->capture ? 'yes' : 'no');

		if ($this->capture) {
			update_post_meta($order_id, '_transaction_id', $response['xRefNum'], true);
			update_post_meta($order_id, '_cardknox_masked_card', $response['xMaskedCardNumber']);
			$order->payment_complete($response['xRefNum']);

			$message = sprintf(__('Cardknox transaction captured (capture RefNum: %s)', 'woocommerce-gateway-cardknox'), $response['xRefNum']);
			$order->add_order_note($message);
			$this->log('Success: ' . $message);
		} else {
			update_post_meta($order_id, '_transaction_id', $response['xRefNum'], true);

			if ($order->has_status(array('pending', 'failed'))) {
				version_compare(WC_VERSION, '3.0.0', '<') ? $order->reduce_order_stock() : wc_reduce_stock_levels($order_id);
			}
			$xRefNum =  $response['xRefNum'];

			if ($this->authonly_status == "on-hold") {
				$order->update_status('on-hold', sprintf(__('Cardknox charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-cardknox'), $response['xRefNum']));
			} else {
				$order->update_status('processing', sprintf(__('Cardknox charge authorized (Charge ID: %s). Complete order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-cardknox'), $response['xRefNum']));
			}

			$this->log("Successful auth: $xRefNum");
		}

		do_action('wc_gateway_cardknox_process_response', $response, $order);

		return $response;
	}

	/**
	 * Refund a charge
	 * @param  int $order_id
	 * @param  float $amount
	 * @return bool
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{
		$order = wc_get_order($order_id);

		if (!$order || !get_post_meta($order_id, '_cardknox_xrefnum', true)) {
			return false;
		}

		$captured = get_post_meta($order_id, '_cardknox_transaction_captured', true);
		$body = array();

		if (!is_null($amount)) {
			//check if amount is set to 0
			if ($amount < .01) {
				$this->log('Error: Amount Required ' . $amount);
				return new WP_Error('Error', 'Refund Amount Required ' . $amount);
			}
			$body['xAmount']	= $this->get_cardknox_amount($amount);
		}

		$command = 'cc:voidrefund';
		$total = $order->get_total();

		if ($total !=  $amount) {
			$command = 'cc:refund';
			if ($captured === "no") {
				return new WP_Error('Error', 'Partial Refund Not Allowed On Authorize Only Transactions');
			}
		}

		$body['xCommand'] = $command;
		$body['xRefNum'] = get_post_meta($order_id, '_cardknox_xrefnum', true);
		$this->log("Info: Beginning refund for order $order_id for the amount of {$amount}");

		$response = WC_Cardknox_API::request($body);

		if (is_wp_error($response)) {
			$this->log('Error: ' . $response->get_error_message());
			return $response;
		} elseif (!empty($response['xRefNum'])) {
			$refund_message = sprintf(__('Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-cardknox'), wc_price($response['xAuthAmount']), $response['xRefNum'], $reason);
			$order->add_order_note($refund_message);
			$this->log('Success: ' . html_entity_decode(strip_tags((string) $refund_message)));
			return true;
		} else {
			return new WP_Error("refund failed", 'woocommerce-gateway-cardknox');
		}
	}

	/**
	 * Sends the failed order email to admin
	 *
	 * @version 3.1.0
	 * @since 3.1.0
	 * @param int $order_id
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Logs
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 *
	 * @param string $message
	 */
	public function log($message)
	{
		if ($this->logging) {
			WC_Cardknox::log($message);
		}
	}

	/**
	 * Apple Pay Button
	 *
	 * @return void
	 */
	public function cardknox_review_order_after_submit()
	{
		if ($this->enabled == 'yes') {
			echo '<div id="ap-container" class="ap hidden" style="height:auto;min-height:55px;"><br/></div><div class="messages"><div class="message message-error error applepay-error" style="display: none;"></div>';
		}
	}

	/**
	 * Apple Pay available based on specific countries.
	 *
	 * @param [type] $available_gateways
	 * @return void
	 */
	public function cardknox_allow_payment_method_by_country($available_gateways){

		if ( is_admin() ) return $available_gateways;
		
		$applicable_countries 	= $this->applepay_applicable_countries;
		$specific_countries 	= $this->applepay_specific_countries;

		if(isset($applicable_countries) && $applicable_countries == 1){
			// Get the customer's billing and shipping addresses
			$billing_country = WC()->customer->get_billing_country();
		
			// Define the country codes for which you want to allow the payment method
			$enabled_countries = $specific_countries; // Add the country codes to this array
		
			// Check if the billing or shipping address country is in the allow countries array
			if ( !in_array($billing_country, $enabled_countries) ) {
				// allow the payment method by unsetting it from the available gateways
				unset($available_gateways['cardknox-applepay']);
			}
		} 	
		return $available_gateways;
	}
}
