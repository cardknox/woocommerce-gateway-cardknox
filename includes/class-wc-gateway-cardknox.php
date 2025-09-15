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

    const COMMAND_SAVE = 'cc:save';
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
      
        if (class_exists('WC_Subscriptions_Order')) {
            $this->view_transaction_url = 'https://portal.solapayments.com/transactions?disabled=true&expandedRow=%s&referenceNumber=%s';
        }
        else
        {
            $this->view_transaction_url = 'https://portal.solapayments.com/transactions?disabled=true&referenceNumber=%s';
        }

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
        $this->applicable_countries    = $this->get_option('applicable_countries');
        $this->specific_countries      = $this->get_option('specific_countries');



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
        add_filter('woocommerce_available_payment_gateways', array($this, 'cardknox_allow_payment_method_by_country'));
        // Improve saved token display to include Cardknox masked number when available
        add_filter('woocommerce_payment_token_get_display_name', array($this, 'filter_token_display_name'), 10, 2);
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
			<label style="margin:0px;" for="' . esc_attr($this->id) . '-card-cvc">' . esc_html__('Card Code', 'woocommerce') . ' <span class="required">*</span></label>
			<iframe data-ifields-id="cvv" data-ifields-placeholder="CVV"
                        src="https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm?" + "' . esc_attr($timestamp) . '" frameBorder="0" width="100%"
                        height="55" id="cvv-frame"></iframe>
            <label data-ifields-id="card-data-error" style="color: red;"></label>
		</p><input data-ifields-id="cvv-token" name="xCVV" id="cardknox-card-cvc" type="hidden"/>';

        $default_fields = array(
            'card-number-field' => '<p style="margin:0px; padding:0px;"><label style="margin:0px !important; data-ifields-id="card-data-error" id="ifieldsError" style="display:none; margin-bottom:0px;""></label></p>
			<p class="form-row-wide" style="padding-bottom: 0; margin: 0;">
				<label style="margin:0px !important; line-height: inherit;" for="' . esc_attr($this->id) . '-card-number">' . esc_html__('Card Number', 'woocommerce') . ' <span class="required">*</span></label>

				<iframe data-ifields-id="card-number" data-ifields-placeholder="Card Number"
                        src="https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm?" + "' . esc_attr($timestamp) . '" frameBorder="0" width="100%"
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

        $blocks_scripts_registered = wp_script_is('wc-cardknox-blocks', 'registered') || wp_script_is('wc-cardknox-blocks', 'enqueued');
        $ifields_already_registered = wp_script_is('cardknox-ifields', 'registered') || wp_script_is('cardknox-ifields', 'enqueued') || wp_script_is('cardknox', 'enqueued');

        // Avoid loading iFields twice (classic + blocks) which causes global redeclaration errors
        if (! $ifields_already_registered) {
            wp_enqueue_script('cardknox', 'https://cdn.cardknox.com/ifields/3.0.2503.2101/ifields.min.js', '', '1.0.0', false);
        }

        // Do not enqueue the classic checkout controller when using the Blocks checkout
        if (! $blocks_scripts_registered) {
            wp_enqueue_script(
                'woocommerce_cardknox',
                plugins_url('assets/js/cardknox' . $suffix . '.js', WC_CARDKNOX_MAIN_FILE),
                array('jquery-payment'),
                filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/cardknox' . $suffix . '.js'),
                true
            );
        }
        

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
        // Extract payment data from multiple sources (classic form, request vars, Store API JSON)
        $req = $this->build_request_map();

        // Check for block editor payment data (Store API)
        if (!empty($req['cardknox_card_token']) && !empty($req['cardknox_cvv_token'])) {
            $card_token = $req['cardknox_card_token'];
            $cvv_token  = $req['cardknox_cvv_token'];

            // tokens present from Blocks Store API

            $postData['xCardNum'] = $card_token;
            $postData['xCVV']     = $cvv_token;

            // Format expiration date properly - use 2 digits for year instead of 4
            $month      = isset($req['cardknox_exp_month']) ? $req['cardknox_exp_month'] : '';
            $year       = isset($req['cardknox_exp_year']) ? $req['cardknox_exp_year'] : '';
            $year_short = substr((string) $year, -2);
            if ($month !== '' && $year_short !== '') {
                $postData['xExp'] = sprintf('%02d%s', (int) $month, $year_short);
            }

            // Don't call validate_payment_data for block editor as tokens are already validated
            return $postData;
        }
        
        // Original code for classic checkout and Blocks saved token support
        // Support classic field name
        if (!empty($req['wc-cardknox-payment-token']) && 'new' !== $req['wc-cardknox-payment-token']) {
            $token_id = $req['wc-cardknox-payment-token'];
            $token = WC_Payment_Tokens::get($token_id);
            $postData['xToken'] = $token->get_token();
        // Support WooCommerce Blocks saved token param name
        } elseif (!empty($req['wc_token']) && 'new' !== $req['wc_token']) {
            $token_id = $req['wc_token'];
            $token = WC_Payment_Tokens::get($token_id);
            if ($token && $token->get_id()) {
                $postData['xToken'] = $token->get_token();
            }
        } elseif (!empty($req['xToken'])) {
            //token came in (recurring charge)
        } else {
            $card_num = isset($req['xCardNum']) ? $req['xCardNum'] : '';
            $cvv      = isset($req['xCVV']) ? $req['xCVV'] : '';
            
            // tokens present from classic checkout
            
            $postData['xCardNum'] = $card_num;
            $postData['xCVV'] = $cvv;
            $postData['xExp'] = isset($req['xExp']) ? str_replace(' ', '', $req['xExp']) : '';
        }

        $this->validate_payment_data($postData);
        return $postData;
    }

    public function validate_payment_data($postData)
    {
        if (isset($postData['xToken'])) {
            return true;
        } else {
            // Check if we have card data in any format (classic checkout or block editor)
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

                // 3DS branch
                if ($this->enable_3ds === 'yes') {

                    if (is_wp_error($response)) {
                        $order->add_order_note($response->get_error_message());
                        throw new Exception($response->get_error_message());
                    } elseif ($response['xResult'] === 'A') {
                        // Treat approved result same as non-3DS path: set transaction, save card if requested, then process response
                        $this->log("Info: set_transaction_id");
                        $order->set_transaction_id($response['xRefNum']);

                        $this->log("Info: save_payment");
                        $this->save_payment($forceCustomer, $response);

                        if ($forceCustomer) {
                            $this->save_payment_for_subscription($orderId, $response);
                        }

                        $this->log("Info: process_response");
                        $this->process_response($response, $order);
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
        // Check both classic checkout and block editor save card fields
        $req = $this->build_request_map();
        $maybe_saved_card = (
            (isset($req['wc-cardknox-new-payment-method']) && !empty($req['wc-cardknox-new-payment-method'])) ||
            (isset($req['cardknox_save_card']) && ($req['cardknox_save_card'] === 'yes' || $req['cardknox_save_card'] === '1')) ||
            $this->is_flag_enabled_in_store_payment_data('wc-cardknox-new-payment-method') ||
            $this->is_flag_enabled_in_store_payment_data('cardknox_save_card')
        );
        // Log whether a save will be attempted
        $this->log('Save payment requested=' . ($maybe_saved_card ? 'yes' : 'no'));
        // This is true if the user wants to store the card to their account.
        if ((get_current_user_id() && $this->saved_cards && $maybe_saved_card) || $my_force_customer) {
            try {
                // If sale/auth response does not include xToken, fall back to an explicit cc:save using current tokens
                if ((!isset($response['xToken']) || empty($response['xToken'])) && (isset($req['xCardNum']) || isset($req['cardknox_card_token']))) {
                    $saveReq = array(
                        'xCommand' => self::COMMAND_SAVE,
                    );
                    // Reuse parsed payment data
                    if (!empty($req['xCardNum'])) {
                        $saveReq['xCardNum'] = $req['xCardNum'];
                    } elseif (!empty($req['cardknox_card_token'])) {
                        $saveReq['xCardNum'] = $req['cardknox_card_token'];
                    }
                    if (!empty($req['xCVV'])) {
                        $saveReq['xCVV'] = $req['xCVV'];
                    } elseif (!empty($req['cardknox_cvv_token'])) {
                        $saveReq['xCVV'] = $req['cardknox_cvv_token'];
                    }
                    if (!empty($req['xExp'])) {
                        $saveReq['xExp'] = $req['xExp'];
                    } else {
                        $month = isset($req['cardknox_exp_month']) ? $req['cardknox_exp_month'] : '';
                        $year  = isset($req['cardknox_exp_year']) ? $req['cardknox_exp_year'] : '';
                        if ($month !== '' && $year !== '') {
                            $saveReq['xExp'] = sprintf('%02d%s', (int) $month, substr((string) $year, -2));
                        }
                    }

                $this->log('Attempting cc:save (fallback)');
                    $saveResp = WC_Cardknox_API::request($saveReq);
                    if (is_wp_error($saveResp)) {
                        $this->log('cc:save failed: ' . $saveResp->get_error_message());
                    } elseif (!empty($saveResp['xToken'])) {
                        $this->log('cc:save succeeded');
                        $response = $saveResp;
                    } else {
                        $this->log('cc:save returned without token');
                    }
                }
                $this->add_card($response);
            } catch (\Throwable $th) {
                $this->log('Error: ' . $th->getMessage());
            }
        }
    }

    /**
     * Build a unified request map from classic POST/REQUEST vars and Store API JSON payment_data.
     *
     * @return array<string, mixed>
     */
    private function build_request_map()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $map = array();
        // Start with POST/REQUEST
        foreach (array_merge($_REQUEST ?? array(), $_POST ?? array()) as $k => $v) {
            if (is_string($k)) {
                $map[$k] = is_string($v) ? wc_clean($v) : $v;
            }
        }

        // Try to enrich from Store API payment_data
        $entries = $this->get_store_api_payment_data_entries();
        if (!empty($entries)) {
            foreach ($entries as $entry) {
                if (!isset($entry['key'])) {
                    continue;
                }
                $key = (string) $entry['key'];
                $val = isset($entry['value']) ? $entry['value'] : '';
                // Prefer a truthy value if duplicates exist
                if (!isset($map[$key]) || empty($map[$key])) {
                    $map[$key] = is_string($val) ? wc_clean($val) : $val;
                } elseif (!$this->is_truthy($map[$key]) && $this->is_truthy($val)) {
                    $map[$key] = $val;
                }
            }
        }

        $cache = $map;
        return $cache;
    }

    /**
     * Return Store API payment_data entries if the request was made via the Blocks checkout (JSON body).
     *
     * @return array<int, array{key:mixed, value:mixed}>
     */
    private function get_store_api_payment_data_entries()
    {
        static $entries = null;
        if ($entries !== null) {
            return $entries;
        }
        $entries = array();

        // Some environments forward payment_data through $_POST
        if (isset($_POST['payment_data'])) {
            $pd = $_POST['payment_data'];
            if (is_array($pd)) {
                $entries = $pd;
            } elseif (is_string($pd)) {
                // Try to decode JSON-encoded string
                $decoded = json_decode($pd, true);
                if (is_array($decoded)) {
                    $entries = $decoded;
                }
            }
        }

        // Fallback: decode raw JSON body from Store API
        if (empty($entries)) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $json = json_decode($raw, true);
                if (is_array($json) && isset($json['payment_data']) && is_array($json['payment_data'])) {
                    $entries = $json['payment_data'];
                }
            }
        }

        return $entries;
    }

    /**
     * Check if a given key appears with a truthy value in Store API payment_data.
     */
    private function is_flag_enabled_in_store_payment_data($key)
    {
        $entries = $this->get_store_api_payment_data_entries();
        foreach ($entries as $entry) {
            if (!isset($entry['key']) || (string) $entry['key'] !== $key) {
                continue;
            }
            $val = isset($entry['value']) ? $entry['value'] : null;
            if ($this->is_truthy($val)) {
                return true;
            }
        }
        return false;
    }

    private function is_truthy($val)
    {
        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int) $val === 1;
        }
        if (is_string($val)) {
            $val = strtolower(trim($val));
            return in_array($val, array('1', 'yes', 'true', 'on'), true);
        }
        return !empty($val);
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
        if (!get_current_user_id()) {
            $this->log('add_card skipped: no logged-in user');
            return "";
        }

        if (empty($response['xToken'])) {
            $this->log('add_card aborted: missing xToken in response');
            return "";
        }

        if (class_exists('WC_Payment_Token_CC')) {
            $myExp = '';
            if ($response['xExp']) {
                $myExp = str_replace(' ', '', $response['xExp']);
            } elseif (wc_clean($_POST['xExp']) != '') {
                $myExp = str_replace(' ', '', wc_clean($_POST['xExp']));
            } else {
                // Fallback for Blocks: combine posted month/year into MMYY if available
                $month = isset($_POST['cardknox_exp_month']) ? wc_clean($_POST['cardknox_exp_month']) : '';
                $year = isset($_POST['cardknox_exp_year']) ? wc_clean($_POST['cardknox_exp_year']) : '';
                if ($month !== '' && $year !== '') {
                    $year_short = substr((string) $year, -2);
                    $myExp = sprintf('%02d%s', (int) $month, $year_short);
                }
            }
            if ($myExp) {
                $token = new WC_Payment_Token_CC();
                $token->set_token($response['xToken']);
                $token->set_gateway_id('cardknox');
                $token->set_card_type($this->normalizeCardType(isset($response['xCardType']) ? (string) $response['xCardType'] : ''));
                // Ensure only last 4 digits are stored
                $last4 = isset($response['xMaskedCardNumber']) ? $response['xMaskedCardNumber'] : '';
                $last4 = preg_replace('/\D+/', '', (string) $last4);
                $last4 = $last4 ? substr($last4, -4) : '';
                if ($last4 !== '') {
                    $token->set_last4($last4);
                }
                $token->set_expiry_month(substr($myExp, 0, 2));
                $token->set_expiry_year('20' . substr($myExp, 2, 2));
                $token->set_user_id(get_current_user_id());
                $saved_id = $token->save();
                // Persist masked number for display purposes (classic + blocks)
                if (!empty($response['xMaskedCardNumber'])) {
                    $token->update_meta_data('cardknox_masked', (string) $response['xMaskedCardNumber']);
                    $token->save();
                }
                $this->log('add_card saved WC token id=' . $saved_id . ' type=' . $token->get_card_type() . ' last4=' . $token->get_last4() . ' exp=' . $token->get_expiry_month() . '/' . $token->get_expiry_year());
                do_action('woocommerce_cardknox_add_card', get_current_user_id(), $saved_id);
            }
        }
        return "";
    }

    /**
     * Customize saved token display to use Cardknox masked number when available
     *
     * @param string               $display
     * @param WC_Payment_Token_CC  $token
     * @return string
     */
    public function filter_token_display_name($display, $token)
    {
        if (! $token instanceof WC_Payment_Token_CC) {
            return $display;
        }
        if ($token->get_gateway_id() !== 'cardknox') {
            return $display;
        }
        $masked = $token->get_meta('cardknox_masked', true);
        if (!$masked) {
            // Derive a Cardknox-like masked number from brand + last4 and persist for future
            $masked = $this->derive_masked_from_brand_and_last4($token->get_card_type(), $token->get_last4());
            if ($masked) {
                $token->update_meta_data('cardknox_masked', $masked);
                $token->save();
            }
        }

        if ($masked) {
            $brand = strtoupper($token->get_card_type());
            $exp   = sprintf('%02d/%02d', (int) $token->get_expiry_month(), (int) substr((string) $token->get_expiry_year(), -2));
            // Match requested format: VISA •••• 4xxxxxxxxxxx1111 (MM/YY)
            return sprintf('%s •••• %s (%s)', $brand, $masked, $exp);
        }
        return $display;
    }

    /**
     * Derive Cardknox-style masked number like 4xxxxxxxxxxx1111 from brand + last4.
     */
    private function derive_masked_from_brand_and_last4($brand, $last4)
    {
        $brand = strtolower(trim((string) $brand));
        $last4 = preg_replace('/\D+/', '', (string) $last4);
        if (strlen($last4) !== 4) {
            return '';
        }

        // Card length by brand (approx.)
        $lenByBrand = array(
            'amex' => 15,
            'american express' => 15,
            'visa' => 16,
            'mastercard' => 16,
            'discover' => 16,
            'diners' => 16,
            'diners club' => 16,
            'jcb' => 16,
        );
        $firstDigitByBrand = array(
            'amex' => '3',
            'american express' => '3',
            'visa' => '4',
            'mastercard' => '5',
            'discover' => '6',
            'diners' => '3',
            'diners club' => '3',
            'jcb' => '3',
        );

        $length = isset($lenByBrand[$brand]) ? $lenByBrand[$brand] : 16;
        $first  = isset($firstDigitByBrand[$brand]) ? $firstDigitByBrand[$brand] : '4';

        // 1 leading digit + N x's + 4 last digits must equal length
        $numXs = max(0, $length - 1 - 4);
        return $first . str_repeat('x', $numXs) . $last4;
    }

    private function normalizeCardType($type)
    {
        $t = strtolower(trim($type));
        $map = array(
            'american express' => 'amex',
            'americanexpress' => 'amex',
            'amex' => 'amex',
            'visa' => 'visa',
            'mastercard' => 'mastercard',
            'master card' => 'mastercard',
            'mc' => 'mastercard',
            'discover' => 'discover',
            'diners club' => 'diners',
            'diners' => 'diners',
            'jcb' => 'jcb',
        );
        if (isset($map[$t])) {
            return $map[$t];
        }
        return $t;
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
            <a href="#credit-card-settings" class="nav-tab nav-tab-active"><?php _e('Credit Card', 'woocommerce-gateway-cardknox'); ?></a>
            <a href="#apple-pay-settings" class="nav-tab"><?php _e('Apple Pay', 'woocommerce-gateway-cardknox'); ?></a>
            <a href="#google-pay-settings" class="nav-tab"><?php _e('Google Pay', 'woocommerce-gateway-cardknox'); ?></a>
        </div>
            <div id="credit-card-settings" class="panel">
                <table class="form-table" style="width:400px;">
                    <thead>
                        <tr>
                            <th><?php _e('Credit Card Settings', 'woocommerce-gateway-cardknox'); ?></th>
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
                <div style="margin-top: 30px;">
                <h3 style="margin-bottom: 10px;"><?php esc_html_e( 'Apple Pay Registration Instructions', 'woocommerce-gateway-cardknox' ); ?></h3>
                <ol class="apple-pay-instructions">
                    <li><?php esc_html_e( 'Login to your', 'woocommerce-gateway-cardknox' ); ?> <strong><?php esc_html_e( 'Sola Portal', 'woocommerce-gateway-cardknox' ); ?></strong></li>
                    <li>
                        <?php esc_html_e( 'Go to', 'woocommerce-gateway-cardknox' ); ?> 
                        <strong><?php esc_html_e( 'Settings > Gateway Settings > Payment Methods', 'woocommerce-gateway-cardknox' ); ?></strong>
                        <a href="<?php echo esc_url( 'https://portal.solapayments.com/settings/gateway-settings/payment-methods' ); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e( 'Open Link', 'woocommerce-gateway-cardknox' ); ?>
                        </a>
                    </li>
                    <li><?php esc_html_e( 'Please use this option:', 'woocommerce-gateway-cardknox' ); ?> <strong><?php esc_html_e( 'Register for Apple Pay with Cardknox Certificate', 'woocommerce-gateway-cardknox' ); ?></strong></li>
                    <li><?php esc_html_e( 'Click on', 'woocommerce-gateway-cardknox' ); ?> <strong><?php esc_html_e( 'Register', 'woocommerce-gateway-cardknox' ); ?></strong> <?php esc_html_e( 'button', 'woocommerce-gateway-cardknox' ); ?></li>
                    <li><?php esc_html_e( 'Download the Apple developer association certificate file', 'woocommerce-gateway-cardknox' ); ?></li>
                    <li><?php printf(esc_html__( 'Upload Applepay Certificate by click on below %s button', 'woocommerce-gateway-cardknox' ),'<strong>' . esc_html__( '"Choose Certificate"', 'woocommerce-gateway-cardknox' ) . '</strong>');?></li>
                    <li><?php esc_html_e( 'Save', 'woocommerce-gateway-cardknox' ); ?></li>
                    <li><?php esc_html_e( 'Register domain.', 'woocommerce-gateway-cardknox' ); ?></li>
                </ol>
            </div>

            <?php 
                    /*
                     * Add Apple Pay Certificate Upload Validation Message Display
                    */
                    settings_errors('woocommerce_cardknox_applepay');
            ?>

            <table class="form-table" style="width:400px;">
                <thead>
                    <tr>
                        <th><?php _e('Apple Pay Settings', 'woocommerce-gateway-cardknox'); ?></th>
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
                        <th><?php _e('Google Pay Settings', 'woocommerce-gateway-cardknox'); ?></th>
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
    /**
     * Credit Card available based on specific countries.
     *
     * @param [type] $available_gateways
     * @return void
     */
    public function cardknox_allow_payment_method_by_country($available_gateways)
    {

        if ( is_admin() ||  !is_object(WC()->customer) || !method_exists(WC()->customer, 'get_billing_country') ) {
            return $available_gateways;
        }

        $applicable_countries = $this->applicable_countries;
        $specific_countries    = $this->specific_countries;

        if (isset($applicable_countries) && $applicable_countries == 1) {
            // Get the customer's billing and shipping addresses
            $billing_country = WC()->customer->get_billing_country();

            // Define the country codes for which you want to allow the payment method
            $enabled_countries = $specific_countries; // Add the country codes to this array

            // Check if the billing or shipping address country is in the allow countries array
            if (!in_array($billing_country, $enabled_countries)) {
                // allow the payment method by unsetting it from the available gateways
                unset($available_gateways['cardknox']);
            }
        }
        return $available_gateways;
    }
}
