<?php
if (!defined('ABSPATH')) {
    exit;
}

include_once 'settings-cardknox-ach.php';

/**
 * WCCardknoxACH class.
 *
 * ACH (bank account) payment gateway for Cardknox / Sola.
 *
 * @extends WC_Payment_Gateway
 */
class WCCardknoxACH extends WC_Payment_Gateway
{
    /**
     * @var bool
     */
    public $logging;

    /**
     * Require re-entry of routing/account numbers.
     *
     * @var bool
     */
    public $reenter;

    /**
     * @var string
     */
    public $token_key;

    /**
     * @var bool
     */
    public $wcVersion;

    public function __construct()
    {
        $this->id                   = 'cardknox-ach';
        $this->method_title         = __('Sola ACH', 'woo-cardknox-gateway');
        $this->method_description   = __('Please ensure ACH is enabled on your Sola Account before activating this feature.', 'woo-cardknox-gateway');
        $this->has_fields           = true;
        $this->supports             = array(
            'products',
            'refunds',
        );

        $this->init_form_fields();
        $this->init_settings();

        $option = get_option('woocommerce_cardknox_settings', array());

        $this->enabled     = $this->get_option('ach_enabled', 'no');
        $this->title       = $this->get_option('ach_title', __('ACH', 'woo-cardknox-gateway'));
        $this->description = $this->get_option('ach_description', __('Pay securely with your bank account (ACH).', 'woo-cardknox-gateway'));
        $this->reenter     = 'yes' === $this->get_option('ach_reenter', 'yes');
        $this->logging     = 'yes' === (isset($option['logging']) ? $option['logging'] : 'no');
        $this->token_key   = isset($option['token_key']) ? $option['token_key'] : '';
        $this->wcVersion   = version_compare(WC_VERSION, '3.0.0', '<');

        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        // Save ACH settings from the main Sola gateway ACH tab.
        add_action('woocommerce_update_options_payment_gateways_cardknox', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = $GLOBALS['wc_cardknox_ach_settings'];
    }

    /**
     * Check if gateway is available for use.
     *
     * @return bool
     */
    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            return false;
        }

        $transaction_key = WC_Cardknox_API::getTransactionKey();
        if (empty($transaction_key) || empty($this->token_key)) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Enqueue ACH checkout scripts, styles, and Sola iFields SDK.
     */
    public function payment_scripts()
    {
        if ('yes' !== $this->enabled) {
            return;
        }

        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        wp_enqueue_style(
            'woocommerce_cardknox_ach',
            plugins_url('/assets/css/ach.css', WC_CARDKNOX_MAIN_FILE),
            array(),
            WC_CARDKNOX_VERSION
        );

        // Load Sola iFields SDK (same version as credit card gateway).
        $ifields_already = wp_script_is('cardknox-ifields', 'registered')
            || wp_script_is('cardknox-ifields', 'enqueued')
            || wp_script_is('cardknox', 'enqueued');

        if (!$ifields_already) {
            wp_enqueue_script('cardknox', CARDKNOX_IFIELDS_URL, array(), '3.5.2607.1401', false);
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_script(
            'woocommerce_cardknox_ach',
            plugins_url('assets/js/cardknox-ach' . $suffix . '.js', WC_CARDKNOX_MAIN_FILE),
            array('jquery'),
            WC_CARDKNOX_VERSION,
            true
        );

        wp_localize_script(
            'woocommerce_cardknox_ach',
            'wc_cardknox_ach_params',
            array(
                'key'                     => $this->token_key,
                'software_name'           => 'Wordpress_WooCommerce',
                'software_version'        => WC_CARDKNOX_VERSION,
                'reenter'                 => $this->reenter ? 'yes' : 'no',
                'i18n_account_type'       => __('Please select an account type.', 'woo-cardknox-gateway'),
                'i18n_name_required'      => __('Name on account is required.', 'woo-cardknox-gateway'),
                'i18n_routing_invalid'    => __('Please enter a valid 9-digit routing number.', 'woo-cardknox-gateway'),
                'i18n_routing_mismatch'   => __('Routing numbers do not match.', 'woo-cardknox-gateway'),
                'i18n_account_required'   => __('Account number is required.', 'woo-cardknox-gateway'),
                'i18n_account_invalid'    => __('Please enter a valid account number.', 'woo-cardknox-gateway'),
                'i18n_consent_required'   => __('You must acknowledge and agree to the ACH terms and conditions.', 'woo-cardknox-gateway'),
                'i18n_ifields_unavailable'=> __('Payment form is not ready. Please refresh and try again.', 'woo-cardknox-gateway'),
            )
        );
    }

    /**
     * ACH authorization / click-wrap legal text.
     *
     * @return string
     */
    protected function get_ach_agreement_text()
    {
        $store_name = get_bloginfo('name');

        $text  = sprintf(
            /* translators: %s: store name */
            __('I hereby authorize %s ("Company") to debit my bank account through the electronic Automated Clearing House (ACH) network within one to three business days from the payment date for the amount of my purchase(s) or payment(s) as indicated at checkout.', 'woo-cardknox-gateway'),
            $store_name
        );
        $text .= ' ';
        $text .= __('I understand that this authorization will remain in effect until I notify Company in writing that I wish to revoke this authorization. I agree that ACH transactions I authorize comply with all applicable law.', 'woo-cardknox-gateway');
        $text .= ' ';
        $text .= __('I acknowledge that the debit will appear on my bank statement and that I am an authorized signer on the account provided. If any payment is returned unpaid, I authorize Company to collect a returned item fee to the extent permitted by law.', 'woo-cardknox-gateway');

        return apply_filters('wc_cardknox_ach_agreement_text', $text, $store_name);
    }

    /**
     * Payment form on checkout page (iFields for account number).
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wp_kses_post(wpautop(wptexturize($this->description)));
        }

        $account_type = isset($_POST['cardknox_ach_account_type']) ? sanitize_text_field(wp_unslash($_POST['cardknox_ach_account_type'])) : 'Checking';
        $name_on_account = isset($_POST['cardknox_ach_name_on_account']) ? sanitize_text_field(wp_unslash($_POST['cardknox_ach_name_on_account'])) : '';
        $timestamp = time();
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-form" class="wc-payment-form wc-cardknox-ach-form">
            <p class="form-row form-row-wide">
                <label><?php esc_html_e('Account Type', 'woo-cardknox-gateway'); ?> <span class="required">*</span></label>
                <span class="wc-cardknox-ach-account-type">
                    <label>
                        <input type="radio" name="cardknox_ach_account_type" value="Checking" <?php checked($account_type, 'Checking'); ?> />
                        <?php esc_html_e('Checking', 'woo-cardknox-gateway'); ?>
                    </label>
                    <label>
                        <input type="radio" name="cardknox_ach_account_type" value="Savings" <?php checked($account_type, 'Savings'); ?> />
                        <?php esc_html_e('Savings', 'woo-cardknox-gateway'); ?>
                    </label>
                </span>
                <span class="wc-cardknox-ach-error"></span>
            </p>

            <p class="form-row form-row-wide">
                <label for="cardknox_ach_name_on_account"><?php esc_html_e('Name on Account', 'woo-cardknox-gateway'); ?> <span class="required">*</span></label>
                <input id="cardknox_ach_name_on_account" name="cardknox_ach_name_on_account" type="text" class="input-text" autocomplete="name" value="<?php echo esc_attr($name_on_account); ?>" />
                <span class="wc-cardknox-ach-error"></span>
            </p>

            <p class="form-row form-row-wide">
                <label for="cardknox_ach_routing_number"><?php esc_html_e('Routing Number', 'woo-cardknox-gateway'); ?> <span class="required">*</span></label>
                <input id="cardknox_ach_routing_number" name="cardknox_ach_routing_number" type="text" class="input-text" inputmode="numeric" autocomplete="off" maxlength="9" />
                <span class="wc-cardknox-ach-error"></span>
            </p>

            <?php if ($this->reenter) : ?>
                <p class="form-row form-row-wide">
                    <label for="cardknox_ach_routing_number_confirm"><?php esc_html_e('Re-enter Routing Number', 'woo-cardknox-gateway'); ?> <span class="required">*</span></label>
                    <input id="cardknox_ach_routing_number_confirm" name="cardknox_ach_routing_number_confirm" type="text" class="input-text" inputmode="numeric" autocomplete="off" maxlength="9" />
                    <span class="wc-cardknox-ach-error"></span>
                </p>
            <?php endif; ?>

            <p class="form-row form-row-wide wc-cardknox-ach-account-field">
                <label><?php esc_html_e('Account Number', 'woo-cardknox-gateway'); ?> <span class="required">*</span></label>
                <iframe
                    data-ifields-id="ach"
                    data-ifields-placeholder="<?php echo esc_attr__('Account Number', 'woo-cardknox-gateway'); ?>"
                    src="https://cdn.cardknox.com/ifields/3.5.2607.1401/ifield.htm?<?php echo esc_attr($timestamp); ?>"
                    frameBorder="0"
                    width="100%"
                    height="55"
                    title="<?php echo esc_attr__('Account Number', 'woo-cardknox-gateway'); ?>"
                ></iframe>
                <input data-ifields-id="ach-token" name="xACH" id="cardknox-ach-token" type="hidden" value="" />
                <label id="cardknox-ach-ifields-error" data-ifields-id="card-data-error" style="color: red; display: none;"></label>
            </p>

            <div class="clear"></div>

            <div class="wc-cardknox-ach-agreement">
                <?php echo esc_html($this->get_ach_agreement_text()); ?>
            </div>

            <p class="form-row form-row-wide wc-cardknox-ach-consent">
                <label for="cardknox_ach_consent">
                    <input id="cardknox_ach_consent" name="cardknox_ach_consent" type="checkbox" value="1" />
                    <?php esc_html_e('I acknowledge that I have read, understand and agree to the full terms and conditions set forth above.', 'woo-cardknox-gateway'); ?>
                    <span class="required">*</span>
                </label>
                <span class="wc-cardknox-ach-error"></span>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Validate ACH fields on checkout.
     *
     * @return bool
     */
    public function validate_fields()
    {
        $account_type    = isset($_POST['cardknox_ach_account_type']) ? sanitize_text_field(wp_unslash($_POST['cardknox_ach_account_type'])) : '';
        $name_on_account = isset($_POST['cardknox_ach_name_on_account']) ? sanitize_text_field(wp_unslash($_POST['cardknox_ach_name_on_account'])) : '';
        $routing = (isset($_POST['cardknox_ach_routing_number']) && is_scalar($_POST['cardknox_ach_routing_number'])) ? preg_replace('/\D/', '', wp_unslash($_POST['cardknox_ach_routing_number'])) : '';


        $ach_token       = isset($_POST['xACH']) ? sanitize_text_field(wp_unslash($_POST['xACH'])) : '';
        $consent         = !empty($_POST['cardknox_ach_consent']);

        $valid = true;

        if (!in_array($account_type, array('Checking', 'Savings'), true)) {
            wc_add_notice(__('Please select an account type.', 'woo-cardknox-gateway'), 'error');
            $valid = false;
        }

        if ('' === $name_on_account) {
            wc_add_notice(__('Name on account is required.', 'woo-cardknox-gateway'), 'error');
            $valid = false;
        }

        if (!$this->is_valid_routing_number($routing)) {
            wc_add_notice(__('Please enter a valid 9-digit routing number.', 'woo-cardknox-gateway'), 'error');
            $valid = false;
        }

        if ($this->reenter) {
            $routing_confirm = isset($_POST['cardknox_ach_routing_number_confirm']) ? preg_replace('/\D/', '', wp_unslash($_POST['cardknox_ach_routing_number_confirm'])) : '';

            if ($routing !== $routing_confirm) {
                wc_add_notice(__('Routing numbers do not match.', 'woo-cardknox-gateway'), 'error');
                $valid = false;
            }
        }

        // Account number is tokenized via iFields (xACH SUT).
        if ('' === $ach_token) {
            wc_add_notice(__('Account number is required.', 'woo-cardknox-gateway'), 'error');
            $valid = false;
        }

        if (!$consent) {
            wc_add_notice(__('You must acknowledge and agree to the ACH terms and conditions.', 'woo-cardknox-gateway'), 'error');
            $valid = false;
        }

        return $valid;
    }

    /**
     * ABA routing number checksum validation.
     *
     * @param string $routing
     * @return bool
     */
    protected function is_valid_routing_number($routing)
    {
        if (!preg_match('/^\d{9}$/', $routing)) {
            return false;
        }

        $digits = array_map('intval', str_split($routing));
        $checksum = 3 * ($digits[0] + $digits[3] + $digits[6])
            + 7 * ($digits[1] + $digits[4] + $digits[7])
            + ($digits[2] + $digits[5] + $digits[8]);

        return 0 === ($checksum % 10);
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    protected function get_client_ip()
    {
        // Do not trust HTTP_CLIENT_IP / HTTP_X_FORWARDED_FOR for consent audit IP.
        // Those headers are client-controlled unless a trusted proxy rewrites them.
        if (class_exists('WC_Geolocation')) {
            return WC_Geolocation::get_ip_address();
        }

        return isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
    }

    /**
     * Store click-wrap consent details on the order.
     *
     * @param WC_Order $order
     * @param string   $name_on_account
     */
    protected function store_ach_consent($order, $name_on_account)
    {
        $order_id = $this->wcVersion ? $order->id : $order->get_id();
        $timestamp = current_time('mysql');
        $ip = $this->get_client_ip();

        $consent_data = array(
            'name'      => $name_on_account,
            'timestamp' => $timestamp,
            'ip'        => $ip,
            'agreed'    => 'yes',
        );

        update_post_meta($order_id, '_cardknox_ach_consent_name', $name_on_account);
        update_post_meta($order_id, '_cardknox_ach_consent_timestamp', $timestamp);
        update_post_meta($order_id, '_cardknox_ach_consent_ip', $ip);
        update_post_meta($order_id, '_cardknox_ach_consent', $consent_data);

        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_cardknox_ach_consent_name', $name_on_account);
            $order->update_meta_data('_cardknox_ach_consent_timestamp', $timestamp);
            $order->update_meta_data('_cardknox_ach_consent_ip', $ip);
            $order->update_meta_data('_cardknox_ach_consent', $consent_data);
            $order->save();
        }

        $order->add_order_note(
            sprintf(
                /* translators: 1: name, 2: timestamp, 3: IP */
                __('ACH click-wrap agreement accepted by %1$s on %2$s (IP: %3$s).', 'woo-cardknox-gateway'),
                $name_on_account,
                $timestamp,
                $ip
            )
        );
    }

    /**
     * Format amount for Cardknox.
     *
     * @param float  $total
     * @param string $currency
     * @return float|int
     */
    public function get_cardknox_amount($total, $currency = '')
    {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }

        switch (strtoupper($currency)) {
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
                break;
        }

        return $total;
    }

    /**
     * Build ACH payment request for Cardknox check:sale.
     * Account number is sent as iFields SUT (xACH), per Sola iFields standard.
     *
     * @param WC_Order $order
     * @return array
     */
    protected function generate_payment_request($order)
    {
        $account_type    = isset($_POST['cardknox_ach_account_type']) ? sanitize_text_field(wp_unslash($_POST['cardknox_ach_account_type'])) : 'Checking';
        $name_on_account = isset($_POST['cardknox_ach_name_on_account']) ? sanitize_text_field(wp_unslash($_POST['cardknox_ach_name_on_account'])) : '';
        $routing         = isset($_POST['cardknox_ach_routing_number']) ? preg_replace('/\D/', '', wp_unslash($_POST['cardknox_ach_routing_number'])) : '';
        $ach_token       = isset($_POST['xACH']) ? sanitize_text_field(wp_unslash($_POST['xACH'])) : '';

        $billing_email = $this->wcVersion ? $order->billing_email : $order->get_billing_email();

        $post_data = array(
            'xCommand'       => 'check:sale',
            'xAmount'        => $this->get_cardknox_amount($order->get_total()),
            'xRouting'       => $routing,
            'xAccount'       => $ach_token,
            'xName'          => $name_on_account,
            'xAccountType'   => $account_type,
            'xPaymentOrigin' => 'Internet',
            'xCurrency'      => strtolower($this->wcVersion ? $order->get_order_currency() : $order->get_currency()),
            'xEmail'         => $billing_email,
            'xInvoice'       => $this->wcVersion ? $order->id : $order->get_id(),
            'xIP'            => $this->wcVersion ? $order->customer_ip_address : $order->get_customer_ip_address(),
            'xTax'           => $order->get_total_tax() > 0 ? $order->get_total_tax() : 0,
        );

        // Billing
        $post_data['xBillCompany']   = $this->wcVersion ? $order->billing_company : $order->get_billing_company();
        $post_data['xBillFirstName'] = $this->wcVersion ? $order->billing_first_name : $order->get_billing_first_name();
        $post_data['xBillLastName']  = $this->wcVersion ? $order->billing_last_name : $order->get_billing_last_name();
        $post_data['xBillStreet']    = $this->wcVersion ? $order->billing_address_1 : $order->get_billing_address_1();
        $post_data['xBillStreet2']   = $this->wcVersion ? $order->billing_address_2 : $order->get_billing_address_2();
        $post_data['xBillCity']      = $this->wcVersion ? $order->billing_city : $order->get_billing_city();
        $post_data['xBillState']     = $this->wcVersion ? $order->billing_state : $order->get_billing_state();
        $post_data['xBillZip']       = $this->wcVersion ? $order->billing_postcode : $order->get_billing_postcode();
        $post_data['xBillCountry']   = $this->wcVersion ? $order->billing_country : $order->get_billing_country();
        $post_data['xBillPhone']     = $this->wcVersion ? $order->billing_phone : $order->get_billing_phone();

        // Shipping
        $post_data['xShipCompany']   = $this->wcVersion ? $order->shipping_company : $order->get_shipping_company();
        $post_data['xShipFirstName'] = $this->wcVersion ? $order->shipping_first_name : $order->get_shipping_first_name();
        $post_data['xShipLastName']  = $this->wcVersion ? $order->shipping_last_name : $order->get_shipping_last_name();
        $post_data['xShipStreet']    = $this->wcVersion ? $order->shipping_address_1 : $order->get_shipping_address_1();
        $post_data['xShipStreet2']   = $this->wcVersion ? $order->shipping_address_2 : $order->get_shipping_address_2();
        $post_data['xShipCity']      = $this->wcVersion ? $order->shipping_city : $order->get_shipping_city();
        $post_data['xShipState']     = $this->wcVersion ? $order->shipping_state : $order->get_shipping_state();
        $post_data['xShipZip']       = $this->wcVersion ? $order->shipping_postcode : $order->get_shipping_postcode();
        $post_data['xShipCountry']   = $this->wcVersion ? $order->shipping_country : $order->get_shipping_country();

        if (!empty($billing_email) && apply_filters('wc_cardknox_send_cardknox_receipt', false)) {
            $post_data['xCustReceipt'] = '1';
        }

        return apply_filters('wc_cardknox_ach_generate_payment_request', $post_data, $order);
    }

    /**
     * Process ACH payment.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        try {
            $order = wc_get_order($order_id);

            $name_on_account = isset($_POST['cardknox_ach_name_on_account']) ? sanitize_text_field(wp_unslash($_POST['cardknox_ach_name_on_account'])) : '';
            $this->store_ach_consent($order, $name_on_account);

            $response = null;

            if ($order->get_total() > 0) {
                if ($order->get_total() < WC_Cardknox::get_minimum_amount() / 100) {
                    throw new Exception(
                        sprintf(
                            __('Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woo-cardknox-gateway'),
                            wc_price(WC_Cardknox::get_minimum_amount() / 100)
                        )
                    );
                }

                $this->log("Info: Begin ACH processing for order $order_id for the amount of {$order->get_total()}");

                $response = WC_Cardknox_API::request($this->generate_payment_request($order));

                if (is_wp_error($response)) {
                    //$order->add_order_note($response->get_error_message());
                    //throw new Exception(__('The ACH transaction was declined, please try again.', 'woo-cardknox-gateway'));

                    $gateway_error = $response->get_error_message();
                    $order->add_order_note($gateway_error);
                    $this->log('ACH gateway error: ' . $gateway_error);
                    throw new Exception(__('The ACH transaction was declined, please try again.', 'woo-cardknox-gateway'));
                }

                $order->set_transaction_id($response['xRefNum']);
                $this->process_response($response, $order);
            } else {
                $order->payment_complete();
            }

            WC()->cart->empty_cart();

            do_action('wc_gateway_cardknox_ach_process_payment', $response, $order);

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            $this->log(sprintf(__('ACH Error: %s', 'woo-cardknox-gateway'), $e->getMessage()));

            if (isset($order) && $order && $order->has_status(array('pending', 'failed'))) {
                $this->send_failed_order_email($order_id);
                if ('pending' === $order->get_status()) {
                    $order->update_status('failed');
                }
            }

            do_action('wc_gateway_cardknox_ach_process_payment_error', $e, isset($order) ? $order : null);

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Store response meta and complete payment.
     *
     * @param array    $response
     * @param WC_Order $order
     * @return array
     */
    public function process_response($response, $order)
    {
        $order_id = $this->wcVersion ? $order->id : $order->get_id();

        update_post_meta($order_id, '_cardknox_xrefnum', $response['xRefNum']);
        update_post_meta($order_id, '_cardknox_transaction_captured', 'yes');
        update_post_meta($order_id, '_transaction_id', $response['xRefNum'], true);
        update_post_meta($order_id, '_cardknox_payment_type', 'ach');

        if (!empty($response['xMaskedAccountNumber'])) {
            update_post_meta($order_id, '_cardknox_masked_account', $response['xMaskedAccountNumber']);
        }

        $order->payment_complete($response['xRefNum']);

        $message = sprintf(
            __('Sola ACH transaction completed (RefNum: %s)', 'woo-cardknox-gateway'),
            $response['xRefNum']
        );
        $order->add_order_note($message);
        $this->log('Success: ' . $message);

        do_action('wc_gateway_cardknox_ach_process_response', $response, $order);

        return $response;
    }

    /**
     * Refund an ACH charge via check:refund / check:void.
     *
     * @param int    $order_id
     * @param float  $amount
     * @param string $reason
     * @return bool|WP_Error
     */
        public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        if (!$order || !get_post_meta($order_id, '_cardknox_xrefnum', true)) {
            return false;
        }

        if (!is_null($amount) && $amount < 0.01) {
            return new WP_Error('Error', __('Refund Amount Required', 'woo-cardknox-gateway'));
        }

        $total = $order->get_total();
        $xref  = get_post_meta($order_id, '_cardknox_xrefnum', true);

        // Partial ACH refunds are not supported by check:refund.
        if (!is_null($amount) && (float) $amount !== (float) $total) {
            return new WP_Error('Error', __('Partial ACH refunds are not supported.', 'woo-cardknox-gateway'));
        }

        $body = array(
            'xCommand' => 'check:refund',
            'xRefNum'  => $xref,
        );

        if (!is_null($amount)) {
            $body['xAmount'] = $this->get_cardknox_amount($amount);
        }

        $this->log("Info: Beginning ACH refund for order $order_id for the amount of {$amount}");

        $response = WC_Cardknox_API::request($body);

        if (is_wp_error($response)) {
            $error_message = strtolower($response->get_error_message());

            // Only fall back to check:void when Cardknox indicates the ACH
            $should_try_void = (
                false !== strpos($error_message, 'void')
                || false !== strpos($error_message, 'unsettled')
                || false !== strpos($error_message, 'pending')
                || false !== strpos($error_message, '01151') 
                || false !== strpos($error_message, '01493') 
                || false !== strpos($error_message, '01704') 
            );

            if (!$should_try_void) {
                $this->log('Error: ' . $response->get_error_message());
                return $response;
            }

            // Pending ACH transactions may need void instead of refund.
            $void_response = WC_Cardknox_API::request(
                array(
                    'xCommand' => 'check:void',
                    'xRefNum'  => $xref,
                )
            );

            if (is_wp_error($void_response)) {
                $this->log('Error: ' . $response->get_error_message());
                return $response;
            }

            $response = $void_response;
        }

        if (!empty($response['xRefNum'])) {
            $refund_message = sprintf(
                __('ACH Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woo-cardknox-gateway'),
                wc_price(isset($response['xAuthAmount']) ? $response['xAuthAmount'] : $amount),
                $response['xRefNum'],
                $reason
            );
            $order->add_order_note($refund_message);
            $this->log('Success: ' . html_entity_decode(strip_tags((string) $refund_message)));
            return true;
        }

        return new WP_Error('refund_failed', __('ACH refund failed', 'woo-cardknox-gateway'));
    }

    /**
     * Send failed order email to admin.
     *
     * @param int $order_id
     */
    public function send_failed_order_email($order_id)
    {
        $emails = WC()->mailer()->get_emails();
        if (!empty($emails) && !empty($order_id) && isset($emails['WC_Email_Failed_Order'])) {
            $emails['WC_Email_Failed_Order']->trigger($order_id);
        }
    }

    /**
     * Log messages when logging is enabled.
     *
     * @param string $message
     */
    public function log($message)
    {
        if ($this->logging) {
            WC_Cardknox::log($message);
        }
    }
}
