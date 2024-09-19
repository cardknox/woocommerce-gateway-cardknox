<?php
if (!defined('ABSPATH')) {
    exit;
}
include_once 'settings-cardknox-googlepay.php';

/**
 * WC_Gateway_Cardknox class.
 *
 * @extends WC_Payment_Gateway
 */
class WCCardknoxGooglepay extends WC_Payment_Gateway_CC
{
    /**
     * Should we capture Credit cards
     *
     * @var bool
     */
    public $capture;

    public function __construct()
    {
        $this->id                   = 'cardknox-googlepay';
        $this->method_title         = __('Cardknox', 'woocommerce-gateway-cardknox');
        $this->title                = __('Cardknox', 'woocommerce-other-payment-gateway');
        $this->method_description   = __('Cardknox Google Pay', 'woocommerce-other-payment-gateway');
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
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_date_changes',
            'multiple_subscriptions',
        );

        // Load the form fields.
        $this->init_gform_fields();

        // Load the settings.
        $this->init_settings();

        $option = get_option('woocommerce_cardknox_settings');

        $this->enabled                          = $this->get_option('googlepay_enabled');
        $this->google_quickcheckout             = $this->get_option('googlepay_quickcheckout');
        $this->title                            = $this->get_option('googlepay_title');
        $this->description                      = __('Pay with your Google Pay.', 'woocommerce-gateway-cardknox');
        $this->googlepay_merchant_name          = $this->get_option('googlepay_merchant_name');
        $this->googlepay_environment            = $this->get_option('googlepay_environment');
        $this->googlepay_button_style           = $this->get_option('googlepay_button_style');
        $this->capture                          = 'yes' === $this->get_option('googlepay_capture', 'yes');
        $this->authonly_status                  = $this->get_option('googlepay_auth_only_order_status');
        $this->googlepay_applicable_countries   = $this->get_option('googlepay_applicable_countries');
        $this->googlepay_specific_countries     = $this->get_option('googlepay_specific_countries');

        $this->wcVersion = version_compare(WC_VERSION, '3.0.0', '<');

        // Hooks.
        add_action('wp_enqueue_scripts', array($this, 'gpayment_scripts'));
        add_action('woocommerce_update_options_payment_gateways_cardknox', array($this, 'process_admin_options'));

        add_action('woocommerce_review_order_after_submit', array($this, 'cardknox_gpay_order_after_submit'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'cardknox_allow_gpay_method_by_country'));

        if (is_cart() && $this->google_quickcheckout == 'no') {
            add_action('woocommerce_proceed_to_checkout', array($this, 'cardknox_gpay_order_after_submit'), 20);
        }
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
        <input type="hidden" name="xCardNumToken" value="" id="googlePaytoken">
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
    public function get_cardknox_gamount($total, $currency = '')
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
    public function init_gform_fields()
    {
        $this->form_fields = $GLOBALS['wc_cardknox_google_pay_settings'];
    }
    /**
     * Localize Cardknox messages based on code
     *
     * @since 3.0.6
     * @version 3.0.6
     * @return array
     */
    public function get_localized_gmessages()
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
    public function gpayment_scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page()) {
            return;
        }

        wp_enqueue_style(
            'woocommerce_cardknox_gpay',
            plugins_url(
                '/assets/css/google-pay.css',
                WC_CARDKNOX_MAIN_FILE
            ),
            false,
            '1.0',
            'all'
        );

        wp_enqueue_script(
            'woocommerce_cardknox_google_pay',
            plugins_url('assets/js/cardknox-google-pay.min.js', WC_CARDKNOX_MAIN_FILE),
            array('jquery-payment'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/cardknox-google-pay.min.js'),
            true
        );

        $cardknoxGooglepaySettings = array(
            'enabled'                 => $this->enabled,
            'title'                   => $this->title,
            'merchant_name'           => $this->googlepay_merchant_name,
            'environment'             => $this->googlepay_environment,
            'button_style'            => $this->googlepay_button_style,
            'payment_action'          => $this->capture,
            'applicable_countries'    => $this->googlepay_applicable_countries,
            'specific_countries'      => $this->googlepay_specific_countries,
            'total'                   => WC()->cart->total,
            'currencyCode'            => get_woocommerce_currency(),
        );

        $cardknoxGooglepaySettings = array_merge($cardknoxGooglepaySettings, $this->get_localized_gmessages());
        wp_localize_script('woocommerce_cardknox_google_pay', 'googlePaysettings', $cardknoxGooglepaySettings);
    }

    /**
     * Generate the request for the google payment.
     * @param  WC_Order $order
     * @param  object $source
     * @return array()
     */
    protected function generate_payment_grequest($order)
    {
        $postData                = array();
        $postData['xCommand']    = $this->capture ? 'cc:sale' : 'cc:authonly';

        $postData = self::get_order_gdata($postData, $order);
        $postData = self::get_billing_shiping_ginfo($postData, $order);
        $postData = self::get_payment_gdata($postData);

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

    public function get_order_gdata($postData, $order)
    {
        $wcVersionLessThanThree = $this->wcVersion;

        $billingEmail = $wcVersionLessThanThree ? $order->billing_email : $order->get_billing_email();
        $postData['xCurrency'] = strtolower(
            $wcVersionLessThanThree
                ? $order->get_order_currency()
                : $order->get_currency()
        );
        $postData['xAmount'] = $this->get_cardknox_gamount($order->get_total());
        $postData['xEmail'] = $billingEmail;
        $postData['xInvoice'] = $wcVersionLessThanThree ? $order->id : $order->get_id();
        $postData['xIP'] = $wcVersionLessThanThree
            ? $order->customer_ip_address
            : $order->get_customer_ip_address();
        if (!empty($billingEmail) && apply_filters('wc_cardknox_send_cardknox_receipt', false)) {
            $postData['xCustReceipt'] = '1';
        }

        return $postData;
    }

    public function get_payment_gdata($postData)
    {
        if (isset($_POST['xCardNumToken'])) {

            $postData['xCardNum']               = wc_clean($_POST['xCardNumToken']);
            $postData['xAmount']                = WC()->cart->total;
            $postData['xDigitalWalletType']     = 'googlepay';
        }
        return $postData;
    }

    public function get_billing_shiping_ginfo($postData, $order)
    {
        $wcVersionLessThanThree = $this->wcVersion;

        // Billing info
        $postData['xBillCompany'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_company');
        $postData['xBillFirstName'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_first_name');
        $postData['xBillLastName'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_last_name');
        $postData['xBillStreet'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_address_1');
        $postData['xBillStreet2'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_address_2');
        $postData['xBillCity'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_city');
        $postData['xBillState'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_state');
        $postData['xBillZip'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_postcode');
        $postData['xBillCountry'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_country');
        $postData['xBillPhone'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'billing_phone');

        // Shipping info
        $postData['xShipCompany'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_company');
        $postData['xShipFirstName'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_first_name');
        $postData['xShipLastName'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_last_name');
        $postData['xShipStreet'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_address_1');
        $postData['xShipStreet2'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_address_2');
        $postData['xShipCity'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_city');
        $postData['xShipState'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_state');
        $postData['xShipZip'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_postcode');
        $postData['xShipCountry'] = $this->getBillingInfog($order, $wcVersionLessThanThree, 'shipping_country');

        return $postData;
    }

    private function getBillingInfog($order, $wcVersionLessThanThree, $field)
    {
        return $wcVersionLessThanThree ? $order->$field : $order->{"get_$field"}();
    }

    /**
     * Process the google payment
     */
    public function process_payment($orderId, $retry = true, $forceCustomer = false)
    {
        try {
            $orderGooglePay = wc_get_order($orderId);

            // Result from Cardknox API request.
            $response = null;

            // Handle payment.
            if ($orderGooglePay->get_total() > 0) {

                if ($orderGooglePay->get_total() < WC_Cardknox::get_minimum_amount() / 100) {
                    throw new Exception(
                        sprintf(
                            __(
                                'Sorry, the minimum allowed order total is %1$s to use this payment method.',
                                'woocommerce-gateway-cardknox'
                            ),
                            wc_price(WC_Cardknox::get_minimum_amount() / 100)
                        )
                    );
                }

                $this->glog("Info: Begin processing payment for order $orderId for the amount of " .
                    "{$orderGooglePay->get_total()}");


                // Make the request.
                $response = WC_Cardknox_API::request($this->generate_payment_grequest($orderGooglePay));

                if (is_wp_error($response)) {
                    $orderGooglePay->add_order_note($response->get_error_message());
                    throw new Exception("The transaction was declined please try again");
                }

                $this->glog("Info: set_transaction_id");
                $orderGooglePay->set_transaction_id($response['xRefNum']);

                // Process valid response.
                $this->glog("Info: process_response");
                $this->process_gresponse($response, $orderGooglePay);
            } else {
                $orderGooglePay->payment_complete();
            }

            $this->glog("Info: empty_cart");

            // Remove cart.
            WC()->cart->empty_cart();

            $this->glog("Info: wc_gateway_cardknox_process_payment");
            do_action('wc_gateway_cardknox_process_payment', $response, $orderGooglePay);

            $this->glog("Info: thank you page redirect");
            // Return thank you page redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($orderGooglePay),
            );
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            $this->glog(sprintf(__('Error: %s', 'woocommerce-gateway-cardknox'), $e->getMessage()));

            if ($orderGooglePay->has_status(array('pending', 'failed'))) {
                $this->send_failed_order_gemailg($orderId);

                $orderStatus = $orderGooglePay->get_status();
                if ('pending' == $orderStatus) {
                    $orderGooglePay->update_status('failed');
                }
            }

            do_action('wc_gateway_cardknox_process_payment_error', $e, $orderGooglePay);

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Store extra meta data for an order from a Cardknox Response.
     */
    public function process_gresponse($response, $order)
    {
        $orderGpayId = $this->wcVersion ? $order->id : $order->get_id();

        // Store charge data
        update_post_meta($orderGpayId, '_cardknox_xrefnum', $response['xRefNum']);
        update_post_meta($orderGpayId, '_cardknox_transaction_captured', $this->capture ? 'yes' : 'no');

        if ($this->capture) {
            update_post_meta($orderGpayId, '_transaction_id', $response['xRefNum'], true);
            update_post_meta($orderGpayId, '_cardknox_masked_card', $response['xMaskedCardNumber']);
            $order->payment_complete($response['xRefNum']);

            $message = sprintf(
                __(
                    'Cardknox transaction captured (capture RefNum: %s)',
                    'woocommerce-gateway-cardknox'
                ),
                $response['xRefNum']
            );
            $order->add_order_note($message);
            $this->glog('Success: ' . $message);
        } else {
            update_post_meta($orderGpayId, '_transaction_id', $response['xRefNum'], true);

            if ($order->has_status(array('pending', 'failed'))) {
                if ($this->wcVersion) {
                    $order->reduce_order_stock();
                } else {
                    wc_reduce_stock_levels($orderGpayId);
                }
            }
            $xRefNum =  $response['xRefNum'];

            if ($this->authonly_status == "on-hold") {
                $order->update_status(
                    'on-hold',
                    sprintf(
                        __(
                            'Cardknox charge authorized (Charge ID: %s).
                    Process order to take payment, or cancel to remove the pre-authorization.',
                            'woocommerce-gateway-cardknox'
                        ),
                        $response['xRefNum']
                    )
                );
            } else {
                $order->update_status('processing', sprintf(
                    __(
                        'Cardknox charge authorized (Charge ID: %s).
                    Complete order to take payment, or cancel to remove the pre-authorization.',
                        'woocommerce-gateway-cardknox'
                    ),
                    $response['xRefNum']
                ));
            }

            $this->glog("Successful auth: $xRefNum");
        }

        do_action('wc_gateway_cardknox_process_response', $response, $order);

        return $response;
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
        $result = false;

        if (!$order || !get_post_meta($orderId, '_cardknox_xrefnum', true)) {
            $result = false;
        } else {
            $captured = get_post_meta($orderId, '_cardknox_transaction_captured', true);
            $body = [];

            if (!is_null($amount) && $amount < 0.01) {
                $this->glog('Error: Amount Required ' . $amount);
                $result = new WP_Error('Error', 'Refund Amount Required ' . $amount);
            } else {
                if (!is_null($amount)) {
                    $body['xAmount'] = $this->get_cardknox_gamount($amount);
                }

                $command = $this->getRefundCommandg($amount, $order, $captured);

                if (is_wp_error($command)) {
                    $result = $command;
                } else {
                    $body['xCommand'] = $command;
                    $body['xRefNum'] = get_post_meta($orderId, '_cardknox_xrefnum', true);
                    $this->glog("Info: Beginning refund for order $orderId for the amount of {$amount}");

                    $response = WC_Cardknox_API::request($body);

                    if (is_wp_error($response)) {
                        $this->glog('Error: ' . $response->get_error_message());
                        $result = $response;
                    } elseif (!empty($response['xRefNum'])) {
                        $refundMessage = $this->getRefundMessageg($response, $reason);
                        $order->add_order_note($refundMessage);
                        $this->glog('Success: ' . html_entity_decode(strip_tags((string) $refundMessage)));
                        $result = true;
                    } else {
                        $result = new WP_Error("refund failed", 'woocommerce-gateway-cardknox');
                    }
                }
            }
        }

        return $result;
    }

    private function getRefundCommandg($amount, $order, $captured)
    {
        $total = $order->get_total();

        if ($total != $amount) {
            if ($captured === "no") {
                return new WP_Error('Error', 'Partial Refund Not Allowed On Authorize Only Transactions');
            } else {
                return 'cc:refund';
            }
        } else {
            return 'cc:voidrefund';
        }
    }

    private function getRefundMessageg($response, $reason)
    {
        return sprintf(
            __('Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-cardknox'),
            wc_price($response['xAuthAmount']),
            $response['xRefNum'],
            $reason
        );
    }

    /**
     * Sends the failed order email to admin
     *
     * @version 3.1.0
     * @since 3.1.0
     * @param int $orderId
     * @return null
     */
    public function send_failed_order_gemailg($orderId)
    {
        $emails = WC()->mailer()->get_emails();
        if (!empty($emails) && !empty($orderId)) {
            $emails['WC_Email_Failed_Order']->trigger($orderId);
        }
    }

    /**
     * Google Pay Logs
     *
     * @since 3.1.0
     * @version 3.1.0
     *
     * @param string $message
     */
    public function glog($message)
    {
        if ($this->logging) {
            WC_Cardknox::log($message);
        }
    }
    /**
     * Google Pay Button
     *
     * @return void
     */
    public function cardknox_gpay_order_after_submit()
    {
        if ($this->enabled == 'yes') {
        ?>
            <div class="messages">
                <div class="message message-error error gpay-error" style="display: none;"></div>
            </div>
            <div id="divGpay" class="gp hidden">
                <iframe id="igp" class="gp" data-ifields-id="igp" data-ifields-oninit="gpRequest.initGP" src="https://cdn.cardknox.com/ifields/2.15.2405.1601/igp.htm" allowpaymentrequest sandbox="allow-popups allow-modals allow-scripts allow-same-origin
                                 allow-forms allow-popups-to-escape-sandbox allow-top-navigation" title="GPay checkout page">
                </iframe>
            </div>
<?php
        }
    }
    /**
     * Google Pay available based on specific countries.
     *
     * @param [type] $available_gateways
     * @return void
     */
    public function cardknox_allow_gpay_method_by_country($available_gateways)
    {

        if (is_admin()) {
            return $available_gateways;
        }

        $applicable_countries  = $this->googlepay_applicable_countries;
        $specific_countries    = $this->googlepay_specific_countries;

        if (isset($applicable_countries) && $applicable_countries == 1) {
            // Get the customer's billing and shipping addresses
            $billing_country = WC()->customer->get_billing_country();

            // Define the country codes for which you want to allow the payment method
            $enabled_countries = $specific_countries; // Add the country codes to this array

            // Check if the billing or shipping address country is in the allow countries array
            if (!in_array($billing_country, $enabled_countries)) {
                // allow the payment method by unsetting it from the available gateways
                unset($available_gateways['cardknox-googlepay']);
            }
        }
        return $available_gateways;
    }
}
