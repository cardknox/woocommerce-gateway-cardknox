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
include_once 'settings-cardknox.php';
/**
 * WC_Gateway_Cardknox class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Cardknox extends WC_Payment_Gateway_CC
{

	/* Define props to avoid php 8.x dynamic prop deprecation warnings. */
	public $bgcolor;

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

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id                   = 'cardknox';
        $this->method_title         = __('Cardknox', 'woocommerce-gateway-cardknox');
        $this->method_description   = sprintf(__('Cardknox works by adding credit card fields on the checkout and then sending the details to Cardknox for verification. <a href="%1$s" target="_blank">Sign up</a> for a Cardknox account.', 'woocommerce-gateway-cardknox'), 'https://www.cardknox.com');
        $this->has_fields           = true;
        $this->view_transaction_url = 'https://portal.cardknox.com/transactions?disabled=true&expandedRow=%s&referenceNumber=%s';
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
            //			'pre-orders',
            'tokenization',
            'add_payment_method'
        );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.
        $this->title                   = $this->get_option('title');
        $this->description             = $this->get_option('description');
        $this->enabled                 = $this->get_option('enabled');
        $this->capture                 = 'yes' === $this->get_option('capture', 'yes');
        $this->saved_cards             = 'yes' === $this->get_option('saved_cards');
        $this->transaction_key         =  $this->get_option('transaction_key');
        $this->token_key               =  $this->get_option('token_key');
        $this->logging                 = 'yes' === $this->get_option('logging');
        $this->authonly_status         = $this->get_option('auth_only_order_status');
        $this->bgcolor                 = $this->get_option('bgcolor');
        $this->enable_3ds              = $this->get_option('enable-3ds');
        $this->threeds_env             = $this->get_option('3ds-env');

        WC_Cardknox_API::set_transaction_key($this->transaction_key);

        // Initialize the child gateways
        $this->apple_pay_gateway = new WCCardknoxApplepay();
        $this->google_pay_gateway = new WCCardknoxGooglepay();

        // Hooks.
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'cardknox_order_meta_general'));
        add_filter('woocommerce_gateway_icon', array($this, 'cardknox_gateway_icon'), 10, 2);
    }


    /**
     * Outputs fields for entering credit card information.
     * @since 2.6.0
     */
    public function form()
    {
        wp_enqueue_script('wc-credit-card-form', '', array('jquery-payment'));
        $timestamp  = filemtime(get_stylesheet_directory());
        $fields = array();
        $cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr($this->id) . '-card-cvc">' . esc_html__('Card Code', 'woocommerce') . ' <span class="required">*</span></label>
			<iframe data-ifields-id="cvv" data-ifields-placeholder="CVV"
                        src="https://cdn.cardknox.com/ifields/2.15.2405.1601/ifield.htm?" + "' . esc_attr($timestamp) . '" frameBorder="0" width="100%"
                        height="55" id="cvv-frame"></iframe>
            <label data-ifields-id="card-data-error" style="color: red;"></label>
		</p><input data-ifields-id="cvv-token" name="xCVV" id="cardknox-card-cvc" type="hidden"/>';

        $default_fields = array(
            'card-number-field' => '<p style="margin:0px; padding:0px;"><label style="margin:0px !important; data-ifields-id="card-data-error" id="ifieldsError" style="display:none; margin-bottom:0px;""></label></p>
			<p class="form-row-wide" style="padding-bottom: 0; margin: 0;">
				<label style="margin:0px !important; line-height: inherit;" for="' . esc_attr($this->id) . '-card-number">' . esc_html__('Card Number', 'woocommerce') . ' <span class="required">*</span></label>

				<iframe data-ifields-id="card-number" data-ifields-placeholder="Card Number"
                        src="https://cdn.cardknox.com/ifields/2.15.2405.1601/ifield.htm?" + "' . esc_attr($timestamp) . '" frameBorder="0" width="100%"
                        height="55"></iframe>
			</p> <input data-ifields-id="card-number-token" name="xCardNum" id="cardknox-card-number" type="hidden"/>',
            'card-expiry-field' => '<p class="form-row form-row-first" style=" margin: 0 !important;">
				<label style="margin:0px !important; line-height: inherit;" for="' . esc_attr($this->id) . '-card-expiry">' . esc_html__('Expiry (MM/YY)', 'woocommerce') . ' <span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' style="outline: none;border: 1px solid rgb(195, 195, 195);border-radius: 4px;padding: 0.618047em;width: 85%;height: 48px;background-color: rgb(255, 255, 255);font-weight: inherit;box-shadow: 0 0 0 0;font-size: 16px;" />
                <input type="hidden" id="x3dsReferenceId" name="x3dsReferenceId" value="">
                <input type="hidden" id="x3dsInitializeStatus" name="x3dsInitializeStatus" value="">
                </p>',
        );

        if (!$this->supports('credit_card_form_cvc_on_saved_method')) {
            $default_fields['card-cvc-field'] = $cvc_field;
        }

        $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
?>

        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
            <?php
            foreach ($fields as $field) {
                echo $field;
            }
            ?>
            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
        <?php

        if ($this->supports('credit_card_form_cvc_on_saved_method')) {
            echo '<fieldset>' . $cvc_field . '</fieldset>';
        }
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
     * Check if SSL is enabled and notify the user
     */
    public function admin_notices()
    {
        if ('no' === $this->enabled) {
            return;
        }

        // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
        if ((function_exists('wc_site_is_https') && !wc_site_is_https()) && ('no' === get_option('woocommerce_force_ssl_checkout') && !class_exists('WordPressHTTPS'))) {
            echo '<div class="error cardknox-ssl-message"><p>' . sprintf(__('Cardknox is enabled, but the <a href="%1$s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid <a href="%2$s" target="_blank">SSL certificate</a> - Cardknox will only work in test mode.', 'woocommerce-gateway-cardknox'), admin_url('admin.php?page=wc-settings&tab=checkout'), 'https://en.wikipedia.org/wiki/Transport_Layer_Security') . '</p></div>';
        }
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available()
    {
        if ('yes' === $this->enabled) {
            if (!$this->transaction_key || !$this->token_key) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = $GLOBALS['wc_cardknox_settings'];
    }

    /**
     * Checks if the current page is the pay for order page and the current user is allowed to pay for the order.
     *
     * @return bool
     */
    public function is_valid_pay_for_order_endpoint()
    {
        $isValid = false;

        // If on the pay for order page and a valid key is set.
        if (is_wc_endpoint_url('order-pay') && isset($_GET['key'])) {
            $orderid = wc_get_order_id_by_order_key(wc_clean(wp_unslash($_GET['key'])));

            // If the order ID is found and matches the order ID in the URL.
            if ($orderid && absint(get_query_var('order-pay')) === absint($orderid)) {
                $order = wc_get_order($orderid);

                // If the order needs payment.
                if ($order->needs_payment()) {
                    $orderCustomer = !empty($order->get_customer_id());

                    // If it's a guest order or the current user can manage WooCommerce.
                    if (!$orderCustomer || current_user_can('manage_woocommerce')) {
                        $isValid = true;
                    } elseif ($order->get_customer_id() === get_current_user_id()) {
                        $isValid = true;
                    }
                }
            }
        }
        return $isValid;
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        $user                 = wp_get_current_user();
        $display_tokenization = $this->supports('tokenization') && is_checkout() && $this->saved_cards;
        $total                = WC()->cart->total;

        if (!$this->is_valid_pay_for_order_endpoint() && !empty($_GET['key'])) {
            $order = wc_get_order(wc_get_order_id_by_order_key(wc_clean($_GET['key'])));
            $total = $order->get_total();
        }

        if ($user->ID) {
            $user_email = get_user_meta($user->ID, 'billing_email', true);
            $user_email = $user_email ? $user_email : $user->user_email;
        } else {
            $user_email = '';
        }

        if (is_add_payment_method_page()) {
            $pay_button_text = __('Add Card', 'woocommerce-gateway-cardknox');
            $total        = '';
        } else {
            $pay_button_text = '';
        }

        echo '<div
			id="cardknox-payment-data"
			data-panel-label="' . esc_attr($pay_button_text) . '"
			data-description=""
			data-email="' . esc_attr($user_email) . '"
			data-amount="' . esc_attr($this->get_cardknox_amount($total)) . '"
			data-currency="' . esc_attr(strtolower(get_woocommerce_currency())) . '"
			data-allow-remember-me="' . esc_attr($this->saved_cards ? 'true' : 'false') . '">';

        if ($this->description) {
            echo apply_filters('wc_cardknox_description', wpautop(wp_kses_post($this->description)));
        }

        if ($display_tokenization) {
            $this->tokenization_script();
            $this->saved_payment_methods();
        }
        $this->form();

        if (apply_filters('wc_cardknox_display_save_payment_method_checkbox', $display_tokenization)) {
            $this->save_payment_method_checkbox();
        }
        echo '</div>';
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
     * Load admin scripts.
     *
     * @since 3.1.0
     * @version 3.1.0
     */
    public function admin_scripts()
    {
        if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_script('woocommerce_cardknox_admin', plugins_url('assets/js/cardknox-admin' . $suffix . '.js', WC_CARDKNOX_MAIN_FILE), array(), WC_CARDKNOX_VERSION, true);

        $cardknox_admin_params = array(
            'localized_messages' => array(
                'missing_transaction_key' => __('Missing Trasnaction Key. Please set the trasnaction key field above and re-try.', 'woocommerce-gateway-cardknox'),
            ),
            'ajaxurl' => admin_url('admin-ajax.php')
        );

        wp_localize_script('woocommerce_cardknox_admin', 'wc_cardknox_admin_params', apply_filters('wc_cardknox_admin_params', $cardknox_admin_params));
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
        global $woocommerce;

        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page()) {
            return;
        }

        wp_enqueue_style(
            'woocommerce_cardknox_cc_form',
            plugins_url(
                '/assets/css/cc-form.css',
                WC_CARDKNOX_MAIN_FILE
            ),
            false,
            '1.0',
            'all'
        );

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_script('cardknox', 'https://cdn.cardknox.com/ifields/2.15.2401.3101/ifields.min.js', '', '1.0.0', false);
        wp_enqueue_script('woocommerce_cardknox', plugins_url('assets/js/cardknox' . $suffix . '.js', WC_CARDKNOX_MAIN_FILE), array('jquery-payment'), filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/cardknox' . $suffix . '.js'), true);
        

        $token_key = $this->token_key;
        $cardknox_params = array(
            'key'                  => $token_key,
            'i18n_terms'           => __('Please accept the terms and conditions first', 'woocommerce-gateway-cardknox'),
            'i18n_required_fields' => __('Please fill in required checkout fields first', 'woocommerce-gateway-cardknox'),
            'bgcolor'              => $this->bgcolor,
            'enable_3ds'           => $this->enable_3ds,
            'threeds_env'          => $this->threeds_env,
            'xVersion'             => '5.0.0',
            'xSoftwareVersion'     => WC()->version,
            'xSoftwareName'        => 'Wordpress_WooCommerce',
            'xCommand'             => $this->capture ? 'cc:sale' : 'cc:authonly',
            'xAmount'              => $woocommerce->cart->get_cart_contents_total(),
            'threeds_object'       => array('ajax_url' => admin_url('admin-ajax.php'))
        );

        // merge localized messages to be use in JS
        $cardknox_params = array_merge($cardknox_params, $this->get_localized_messages());

        wp_localize_script('woocommerce_cardknox', 'wc_cardknox_params', apply_filters('wc_cardknox_params', $cardknox_params));
    }

    /**
     * Generate the request for the payment.
     * @param  WC_Order $order
     * @param  object $source
     * @return array()
     */
    protected function generate_payment_request($order)
    {
        $postData                = array();
        $postData['xCommand']    = $this->capture ? 'cc:sale' : 'cc:authonly';

        $postData = self::get_order_data($postData, $order);
        $postData = self::get_billing_shiping_info($postData, $order);
        $postData = self::get_payment_data($postData);

        /**
         * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
         *
         * @since 3.1.0
         * @param array $postData
         * @param WC_Order $order
         * @param object $source
         */
        return apply_filters('wc_cardknox_generate_payment_request', $postData, $order);
    }

    public function get_order_data($postData, $order)
    {
        $billing_email            = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_email : $order->get_billing_email();
        $postData['xCurrency']    = strtolower(version_compare(WC_VERSION, '3.0.0', '<') ? $order->get_order_currency() : $order->get_currency());
        $postData['xAmount']      = $this->get_cardknox_amount($order->get_total());
        $postData['xEmail']       = $billing_email;
        $postData['xInvoice']     = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();
        $postData['xIP']          = version_compare(WC_VERSION, '3.0.0', '<') ? $order->customer_ip_address : $order->get_customer_ip_address();
        $postData['xTax']         = $order->get_total_tax() > 0 ? $order->get_total_tax() : 0;

        if (!empty($billing_email) && apply_filters('wc_cardknox_send_cardknox_receipt', false)) {
            $postData['xCustReceipt'] = '1';
        }
        return $postData;
    }
    public function get_payment_data($postData)
    {
        if (isset($_POST['wc-cardknox-payment-token']) && 'new' !== $_POST['wc-cardknox-payment-token']) {
            $token_id = wc_clean($_POST['wc-cardknox-payment-token']);
            $token = WC_Payment_Tokens::get($token_id);
            $postData['xToken'] = $token->get_token();
        } elseif (isset($_POST['xToken'])) {
            //token came in (recurring charge)
        } else {
            $postData['xCardNum'] = wc_clean($_POST['xCardNum']);
            $postData['xCVV'] = wc_clean($_POST['xCVV']);
            $postData['xExp'] = str_replace(' ', '', wc_clean($_POST['xExp']));
        }

        $this->validate_payment_data($postData);
        return $postData;
    }

    public function validate_payment_data($postData)
    {
        if (isset($postData['xToken'])) {
            return true;
        } else {
            if ($this->is_unset_or_empty($postData['xCardNum'])) {
                throw new WC_Data_Exception("wc_gateway_cardknox_process_payment_error", "Required: card number", 400);
            }
            if ($this->is_unset_or_empty($postData['xCVV'])) {
                throw new WC_Data_Exception("wc_gateway_cardknox_process_payment_error", "Required: cvv", 400);
            }
        }
    }
    private function is_unset_or_empty($s)
    {
        return !isset($s) || $s === '';
    }
    public function get_billing_shiping_info($postData, $order)
    {
        $postData['xBillCompany'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_company : $order->get_billing_company();
        $postData['xBillFirstName'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_first_name : $order->get_billing_first_name();
        $postData['xBillLastName']  = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_last_name : $order->get_billing_last_name();
        $postData['xBillStreet'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_address_1 : $order->get_billing_address_1();
        $postData['xBillStreet2'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_address_2 : $order->get_billing_address_2();
        $postData['xBillCity'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_city : $order->get_billing_city();
        $postData['xBillState'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_state : $order->get_billing_state();
        $postData['xBillZip'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_postcode : $order->get_billing_postcode();
        $postData['xBillCountry'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_country : $order->get_billing_country();
        $postData['xBillPhone'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_phone : $order->get_billing_phone();

        $postData['xShipCompany'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_company : $order->get_shipping_company();
        $postData['xShipFirstName'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_first_name : $order->get_shipping_first_name();
        $postData['xShipLastName'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_last_name : $order->get_shipping_last_name();
        $postData['xShipStreet'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_address_1 : $order->get_shipping_address_1();
        $postData['xShipStreet2'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_address_2 : $order->get_shipping_address_2();
        $postData['xShipCity'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_city : $order->get_shipping_city();
        $postData['xShipState'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_state : $order->get_shipping_state();
        $postData['xShipZip'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_postcode : $order->get_shipping_postcode();
        $postData['xShipCountry'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_country : $order->get_shipping_country();
        return $postData;
    }

    /**
     * Process the payment
     *
     * @param int  $orderId Reference.
     * @param bool $retry Should we retry on fail.
     * @param bool $forceCustomer Force user creation.
     *
     * @throws Exception If payment will not be accepted.
     *
     * @return array|void
     */
    public function process_payment($orderId, $retry = true, $forceCustomer = false)
    {
        try {
            $order  = wc_get_order($orderId);

            // Result from Cardknox API request.
            $response = null;

            // Handle payment.
            if ($order->get_total() > 0) {

                if ($order->get_total() < WC_Cardknox::get_minimum_amount() / 100) {
                    throw new Exception(sprintf(__('Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-cardknox'), wc_price(WC_Cardknox::get_minimum_amount() / 100)));
                }

                $this->log("Info: Begin processing payment for order $orderId for the amount of {$order->get_total()}");

                // Make the request.
                $response = WC_Cardknox_API::request($this->generate_payment_request($order));
                $paymentName = get_post_meta($orderId, '_payment_method', true);

                if ($this->enable_3ds === 'yes') {

                    if (is_wp_error($response)) {
                        $order->add_order_note($response->get_error_message());
                        throw new Exception($response->get_error_message());
                    } else {
                        if ($response['xResult'] === 'V' && $paymentName === 'cardknox') {
                            return array(
                                'result'   => 'success',
                                'response' => $response
                            );
                        }
                    }
                } else {

                    if (is_wp_error($response)) {
                        //					$localized_messages = $this->get_localized_messages();
                        //
                        //					$message = isset( $localized_messages[ $response->get_error_code() ] ) ? $localized_messages[ $response->get_error_code() ] : $response->get_error_message();
                        //
                        //					$order->add_order_note( $message );
                        $order->add_order_note($response->get_error_message());
                        throw new Exception("The transaction was declined please try again");
                    }

                    $this->log("Info: set_transaction_id");
                    $order->set_transaction_id($response['xRefNum']);

                    $this->log("Info: save_payment");
                    $this->save_payment($forceCustomer, $response);

                    //the below get sets when a subscription charge gets fired
                    if ($forceCustomer) {
                        $this->save_payment_for_subscription($orderId, $response);
                    }
                    // Process valid response.
                    $this->log("Info: process_response");
                    $this->process_response($response, $order);
                }
            } else {
                // the below get sets when a subscription charge gets fired
                if ($forceCustomer && wcs_is_subscription($orderId)) {
                    $postData                = array();
                    $postData['xCommand']     = 'cc:save';
                    $postData = self::get_order_data($postData, $order);
                    $postData = self::get_billing_shiping_info($postData, $order);
                    $postData = self::get_payment_data($postData);
                    $response = WC_Cardknox_API::request($postData);
                    $this->save_payment($forceCustomer, $response);
                    update_post_meta($orderId, '_cardknox_token', $response['xToken']);
                    update_post_meta($orderId, '_cardknox_masked_card', $response['xMaskedCardNumber']);
                    update_post_meta($orderId, '_cardknox_cardtype', $response['xCardType']);
                }
                $order->payment_complete();
            }

            $this->log("Info: empty_cart");

            // Remove cart.
            WC()->cart->empty_cart();

            $this->log("Info: wc_gateway_cardknox_process_payment");
            do_action('wc_gateway_cardknox_process_payment', $response, $order);

            $this->log("Info: thank you page redirect");
            // Return thank you page redirect.
            return array(
                'result'   => 'success',
                'response' => $response,
                'redirect' => $this->get_return_url($order),
            );
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            $this->log(sprintf(__('Error: %s', 'woocommerce-gateway-cardknox'), $e->getMessage()));

            if ($order->has_status(array('pending', 'failed'))) {
                $this->send_failed_order_email($orderId);

                $order_status = $order->get_status();
                if ('pending' == $order_status) {
                    $order->update_status('failed');
                }
            }

            do_action('wc_gateway_cardknox_process_payment_error', $e, $order);

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    public function save_payment($forceCustomer, $response)
    {
        $my_force_customer  = apply_filters('wc_cardknox_force_customer_creation', $forceCustomer, get_current_user_id());
        $maybe_saved_card = isset($_POST['wc-cardknox-new-payment-method']) && !empty($_POST['wc-cardknox-new-payment-method']);
        // This is true if the user wants to store the card to their account.
        if ((get_current_user_id() && $this->saved_cards && $maybe_saved_card) || $my_force_customer) {
            try {
                $this->add_card($response);
            } catch (\Throwable $th) {
                $this->log('Error: ' . $th->getMessage());
            }
        }
    }

    public function save_payment_for_subscription($orderId, $response)
    {
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($orderId)) {
            $subscriptions = wcs_get_subscriptions_for_order($orderId);
        } elseif (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($orderId)) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($orderId);
        } else {
            $subscriptions = array();
        }
        foreach ($subscriptions as $subscription) {
            $subscription_id = $this->wc_pre_30 ? $subscription->id : $subscription->get_id();
            update_post_meta($subscription_id, '_cardknox_token', $response['xToken']);
            update_post_meta($subscription_id, '_cardknox_masked_card', $response['xMaskedCardNumber']);
            update_post_meta($subscription_id, '_cardknox_cardtype', $response['xCardType']);
        }
    }
    /**
     * Store extra meta data for an order from a Cardknox Response.
     */
    public function process_response($response, $order)
    {
        // $this->log( 'Processing response: ' . print_r( $response, true ) );


        $orderId = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();

        // Store charge data
        update_post_meta($orderId, '_cardknox_xrefnum', $response['xRefNum']);
        update_post_meta($orderId, '_cardknox_transaction_captured', $this->capture ? 'yes' : 'no');

        if ($this->capture) {

            update_post_meta($orderId, '_transaction_id', $response['xRefNum'], true);
            update_post_meta($orderId, '_cardknox_masked_card', $response['xMaskedCardNumber']);
            $order->payment_complete($response['xRefNum']);

            $message = sprintf(__('Cardknox transaction captured (capture RefNum: %s)', 'woocommerce-gateway-cardknox'), $response['xRefNum']);
            $order->add_order_note($message);
            $this->log('Success: ' . $message);
        } else {
            update_post_meta($orderId, '_transaction_id', $response['xRefNum'], true);

            if ($order->has_status(array('pending', 'failed'))) {
                version_compare(WC_VERSION, '3.0.0', '<') ? $order->reduce_order_stock() : wc_reduce_stock_levels($orderId);
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
     * Add payment method via account screen.
     * @since 3.0.0
     */
    public function add_payment_method()
    {
        // Check if required POST data is present and the user is logged in
        if (empty($_POST['xCardNum']) || !is_user_logged_in()) {
            wc_add_notice(__('There was a problem adding the card.', 'woocommerce-gateway-cardknox'), 'error');
            return array(
                'result'   => 'failure',
                'redirect' => wc_get_endpoint_url('payment-methods'),
            );
        }

        // Make API request to save the credit card information
        $response = WC_Cardknox_API::request(
            array(
                'xCommand' => 'cc:save',
                'xCardNum' => wc_clean($_POST['xCardNum']),
                'xCVV'     => wc_clean($_POST['xCVV']),
                'xExp'     => str_replace(' ', '', wc_clean($_POST['xExp'])),
            )
        );

        // Handle API response errors
        if (is_wp_error($response)) {
            $this->log('Error: ' . $response->get_error_message());
            wc_add_notice(__('There was a problem adding the card.', 'woocommerce-gateway-cardknox'), 'error');
        } elseif (!empty($response['xToken'])) {
            $log_string = 'Success: ';
            foreach ($response as $key => $value) {
                $log_string .= $key . ' - ' . $value . ' | ';
            }
            $log_string = rtrim($log_string, ' | '); // Remove the trailing '|' character
            $this->log(html_entity_decode(strip_tags($log_string)));

            try {
                // Add the card
                $card = $this->add_card($response);
                if (is_wp_error($card)) {
                    // Handle card addition errors
                    $localized_messages = $this->get_localized_messages();
                    $error_msg = __('There was a problem adding the card.', 'woocommerce-gateway-cardknox');
                    foreach ($card->errors as $error => $msg) {
                        if (isset($localized_messages[$error])) {
                            $error_msg = $localized_messages[$error];
                        }
                    }
                    wc_add_notice($error_msg, 'error');
                }
            } catch (\Throwable $th) {
                // Handle general errors during card addition
                $this->log('Error: ' . $th->getMessage());
                wc_add_notice('An error occurred while Adding Payment Method', 'error');
            }
        } else {
            // No 'xToken' found in the API response, return a WP_Error
            $response = new WP_Error('save_card_failed', 'woocommerce-gateway-cardknox');
        }

        // Return the result based on the response
        return array(
            'result' => is_wp_error($response) ? 'failure' : 'success',
            'redirect' => wc_get_endpoint_url('payment-methods'),
        );
    }

    /**
     * Refund a charge
     * @param  int $orderId
     * @param  float $amount
     * @return bool
     */
    public function process_refund($orderId, $amount = null, $reason = '')
    {
        $order = wc_get_order($orderId);

        if (!$order || !get_post_meta($orderId, '_cardknox_xrefnum', true)) {
            return false;
        }

        $captured = get_post_meta($orderId, '_cardknox_transaction_captured', true);

        $body = array();

        if (!is_null($amount)) {
            //check if amount is set to 0
            if ($amount < .01) {
                $this->log('Error: Amount Required ' . $amount);
                return new WP_Error('Error', 'Refund Amount Required ' . $amount);
            }
            $body['xAmount']    = $this->get_cardknox_amount($amount);
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

        $body['xRefNum'] = get_post_meta($orderId, '_cardknox_xrefnum', true);

        $this->log("Info: Beginning refund for order $orderId for the amount of {$amount}");

        $response = WC_Cardknox_API::request(
            $body
        );

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
     * @param int $orderId
     * @return null
     */
    public function send_failed_order_email($orderId)
    {
        $emails = WC()->mailer()->get_emails();
        if (!empty($emails) && !empty($orderId)) {
            $emails['WC_Email_Failed_Order']->trigger($orderId);
        }
    }

    /**
     * Add a card (cardknox token) for this customer.
     * @param string $token
     * @param bool $retry
     * @return WP_Error|int
     */
    public function add_card($response)
    {
        // Add token to WooCommerce
        if (get_current_user_id() && class_exists('WC_Payment_Token_CC')) {
            $myExp = '';
            if ($response['xExp']) {
                $myExp = str_replace(' ', '', $response['xExp']);
            } elseif (wc_clean($_POST['xExp']) != '') {
                $myExp = str_replace(' ', '', wc_clean($_POST['xExp']));
            }
            if ($myExp) {
                $token = new WC_Payment_Token_CC();
                $token->set_token($response['xToken']);
                $token->set_gateway_id('cardknox');
                $token->set_card_type(strtolower($response['xCardType']));
                $token->set_last4($response['xMaskedCardNumber']);
                $token->set_expiry_month(substr($myExp, 0, 2));
                $token->set_expiry_year('20' . substr($myExp, 2, 2));
                $token->set_user_id(get_current_user_id());
                $response = $token->save();
                do_action('woocommerce_cardknox_add_card', get_current_user_id(), $response);
            }
        }
        return "";
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

    /*
	 * Card last 4 digits.
	 */
    public function cardknox_order_meta_general($order)
    {
        $orderId = $order->get_id();
        $cardknox_masked_card = get_post_meta($orderId, '_cardknox_masked_card', true);
        if ($cardknox_masked_card) {
        ?>
            <br class="clear" />
            <p><strong><?php echo __('Last 4 Digits Of Credit Card:', 'woocommerce-gateway-cardknox'); ?></strong><br>
                <?php echo substr($cardknox_masked_card, -4); ?></p>
        <?php
        }
    }

    /**
     * Admin options for all payment method
     */
    public function admin_options()
    {
        ?>
        <div id="wc-master-gateway-tabs" class="nav-tab-wrapper">
            <a href="#credit-card-settings" class="nav-tab nav-tab-active"><?php _e('Credit Card', 'woocommerce'); ?></a>
            <a href="#apple-pay-settings" class="nav-tab"><?php _e('Apple Pay', 'woocommerce'); ?></a>
            <a href="#google-pay-settings" class="nav-tab"><?php _e('Google Pay', 'woocommerce'); ?></a>
        </div>
        <div id="credit-card-settings" class="panel">
            <table class="form-table" style="width:400px;">
                <thead>
                    <tr>
                        <th><?php _e('Credit Card Settings', 'woocommerce'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $this->generate_settings_html();
                    ?>
                </tbody>
            </table>
        </div>
        <div id="apple-pay-settings" class="panel">
            <table class="form-table" style="width:400px;">
                <thead>
                    <tr>
                        <th><?php _e('Apple Pay Settings', 'woocommerce'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $this->apple_pay_gateway->generate_settings_html();
                    ?>
                </tbody>
            </table>
        </div>
        <div id="google-pay-settings" class="panel">
            <table class="form-table" style="width:400px;">
                <thead>
                    <tr>
                        <th><?php _e('Google Pay Settings', 'woocommerce'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $this->google_pay_gateway->generate_settings_html();
                    ?>
                </tbody>
            </table>
        </div>
<?php
    }
    public function cardknox_gateway_icon($icon, $id)
    {
        if ($id === 'cardknox') {
            $icon = plugin_dir_url(__DIR__) . 'images/card-logos.png';
            return '<img src="' . $icon . '"> ';
        } else {
            return $icon;
        }
    }
}
