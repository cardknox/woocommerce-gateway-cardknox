<?php
if (!defined('ABSPATH')) {
    exit;
}
include_once 'settings-cardknox-applepay.php';

/**
 * WC_Gateway_Cardknox class.
 *
 * @extends WC_Payment_Gateway
 */
class WCCardknoxApplepay extends WC_Payment_Gateway_CC
{
    /**
     * Should we capture Credit cards
     *
     * @var bool
     */
    public $capture;

    public function __construct()
    {
        $this->id                   = 'cardknox-applepay';
        $this->method_title         = __('Cardknox', 'woocommerce-gateway-cardknox');
        $this->title                = __('Cardknox', 'woocommerce-other-payment-gateway');

        $methodDescription = '<strong class="important-label" style="color: #e22626;">Important: </strong>';
        $methodDescription .= 'Please complete the Apple Pay Domain Registration ';
        $methodDescription .= '<a target="_blank" href="https://portal.cardknox.com/account-settings/payment-methods">';
        $methodDescription .= 'here</a> ';
        $methodDescription .= 'prior to enabling Cardknox Apple Pay.';

        $this->method_description = sprintf(
            __($methodDescription, 'woocommerce-gateway-cardknox'),
            'https://www.cardknox.com'
        );
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
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        $option = get_option('woocommerce_cardknox_settings');

        $this->enabled                          = $this->get_option('applepay_enabled');
        $this->apple_quickcheckout              = $this->get_option('applepay_quickcheckout');
        $this->title                            = $this->get_option('applepay_title');
        $this->description                      = __('Pay with your apple card.', 'woocommerce-gateway-cardknox');
        $this->applepay_merchant_identifier     = $this->get_option('applepay_merchant_identifier');
        $this->applepay_environment             = $this->get_option('applepay_environment');
        $this->applepay_button_style            = $this->get_option('applepay_button_style');
        $this->applepay_button_type             = $this->get_option('applepay_button_type');
        $this->capture                          = 'yes' === $option['capture'];
        $this->authonly_status                  = $option['auth_only_order_status'];
        $this->applepay_applicable_countries    = $option['applicable_countries'];
        $this->applepay_specific_countries      = $option['specific_countries'];

        $this->wcVersion = version_compare(WC_VERSION, '3.0.0', '<');

        // Hooks.
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_update_options_payment_gateways_cardknox', array($this, 'process_admin_options'));

        add_action('woocommerce_review_order_after_submit', array($this, 'cardknox_review_order_after_submit'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'cardknox_allow_payment_method_by_country'));

        if (is_cart() && $this->apple_quickcheckout == 'no') {
            add_action('woocommerce_proceed_to_checkout', array($this, 'cardknox_review_order_after_submit'), 20);
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
        $this->form_fields = $GLOBALS['wc_cardknox_apple_pay_settings'];
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

        wp_enqueue_script(
            'woocommerce_cardknox_apple_pay',
            plugins_url('assets/js/cardknox-apple-pay.min.js', WC_CARDKNOX_MAIN_FILE),
            array('jquery-payment'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/cardknox-apple-pay.min.js'),
            true
        );

        $cardknoxApplepaySettings = array(
            'enabled'                 => $this->enabled,
            'title'                   => $this->title,
            'merchant_identifier'     => $this->applepay_merchant_identifier,
            'environment'             => $this->applepay_environment,
            'button_style'            => $this->applepay_button_style,
            'button_type'             => $this->applepay_button_type,
            'payment_action'          => $this->capture,
            'applicable_countries'    => $this->applepay_applicable_countries,
            'specific_countries'      => $this->applepay_specific_countries,
            'total'                   => WC()->cart->total
        );

        $cardknoxApplepaySettings = array_merge($cardknoxApplepaySettings, $this->get_localized_messages());
        wp_localize_script('woocommerce_cardknox_apple_pay', 'applePaysettings', $cardknoxApplepaySettings);
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
        $wcVersionLessThanThree = $this->wcVersion;

        $billingEmail = $wcVersionLessThanThree ? $order->billing_email : $order->get_billing_email();
        $postData['xCurrency'] = strtolower(
            $wcVersionLessThanThree
                ? $order->get_order_currency()
                : $order->get_currency()
        );
        $postData['xAmount'] = $this->get_cardknox_amount($order->get_total());
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

    public function get_payment_data($postData)
    {
        if (isset($_POST['xCardNumToken'])) {

            $postData['xCardNum']               = wc_clean($_POST['xCardNumToken']);
            $postData['xAmount']                = WC()->cart->total;
            $postData['xDigitalWalletType']     = 'applepay';
        }
        return $postData;
    }

    public function get_billing_shiping_info($postData, $order)
    {
        $wcVersionLessThanThree = $this->wcVersion;

        // Billing info
        $postData['xBillCompany'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_company');
        $postData['xBillFirstName'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_first_name');
        $postData['xBillLastName'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_last_name');
        $postData['xBillStreet'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_address_1');
        $postData['xBillStreet2'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_address_2');
        $postData['xBillCity'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_city');
        $postData['xBillState'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_state');
        $postData['xBillZip'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_postcode');
        $postData['xBillCountry'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_country');
        $postData['xBillPhone'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'billing_phone');

        // Shipping info
        $postData['xShipCompany'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_company');
        $postData['xShipFirstName'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_first_name');
        $postData['xShipLastName'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_last_name');
        $postData['xShipStreet'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_address_1');
        $postData['xShipStreet2'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_address_2');
        $postData['xShipCity'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_city');
        $postData['xShipState'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_state');
        $postData['xShipZip'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_postcode');
        $postData['xShipCountry'] = $this->getBillingInfo($order, $wcVersionLessThanThree, 'shipping_country');

        return $postData;
    }

    private function getBillingInfo($order, $wcVersionLessThanThree, $field)
    {
        return $wcVersionLessThanThree ? $order->$field : $order->{"get_$field"}();
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

                $this->log("Info: Begin processing payment for order $orderId for the amount of {$order->get_total()}");

                // Make the request.
                $response = WC_Cardknox_API::request($this->generate_payment_request($order));

                if (is_wp_error($response)) {
                    $order->add_order_note($response->get_error_message());
                    throw new Exception("The transaction was declined please try again");
                }

                $this->log("Info: set_transaction_id");
                $order->set_transaction_id($response['xRefNum']);

                // Process valid response.
                $this->log("Info: process_response");
                $this->process_response($response, $order);
            } else {
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
                'redirect' => $this->get_return_url($order),
            );
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            $this->log(sprintf(__('Error: %s', 'woocommerce-gateway-cardknox'), $e->getMessage()));

            if ($order->has_status(array('pending', 'failed'))) {
                $this->send_failed_order_email($orderId);

                $orderStatus = $order->get_status();
                if ('pending' == $orderStatus) {
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

    /**
     * Store extra meta data for an order from a Cardknox Response.
     */
    public function process_response($response, $order)
    {
        $orderId = $this->wcVersion ? $order->id : $order->get_id();

        // Store charge data
        update_post_meta($orderId, '_cardknox_xrefnum', $response['xRefNum']);
        update_post_meta($orderId, '_cardknox_transaction_captured', $this->capture ? 'yes' : 'no');

        if ($this->capture) {
            update_post_meta($orderId, '_transaction_id', $response['xRefNum'], true);
            update_post_meta($orderId, '_cardknox_masked_card', $response['xMaskedCardNumber']);
            $order->payment_complete($response['xRefNum']);

            $message = sprintf(
                __(
                    'Cardknox transaction captured (capture RefNum: %s)',
                    'woocommerce-gateway-cardknox'
                ),
                $response['xRefNum']
            );
            $order->add_order_note($message);
            $this->log('Success: ' . $message);
        } else {
            update_post_meta($orderId, '_transaction_id', $response['xRefNum'], true);

            if ($order->has_status(array('pending', 'failed'))) {
                if ($this->wcVersion) {
                    $order->reduce_order_stock();
                } else {
                    wc_reduce_stock_levels($orderId);
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

            $this->log("Successful auth: $xRefNum");
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
            $body = array();

            if (!is_null($amount)) {
                if ($amount < .01) {
                    $this->log('Error: Amount Required ' . $amount);
                    return new WP_Error('Error', 'Refund Amount Required ' . $amount);
                } else {
                    $body['xAmount'] = $this->get_cardknox_amount($amount);
                }
            }

            $command = $this->getRefundCommand($amount, $order, $captured);

            if (is_wp_error($command)) {
                $result = $command;
            } else {
                $body['xCommand'] = $command;
                $body['xRefNum'] = get_post_meta($orderId, '_cardknox_xrefnum', true);
                $this->log("Info: Beginning refund for order $orderId for the amount of {$amount}");

                $response = WC_Cardknox_API::request($body);

                if (is_wp_error($response)) {
                    $this->log('Error: ' . $response->get_error_message());
                    $result = $response;
                } elseif (!empty($response['xRefNum'])) {
                    $refundMessage = $this->getRefundMessage($response, $reason);
                    $order->add_order_note($refundMessage);
                    $this->log('Success: ' . html_entity_decode(strip_tags((string) $refundMessage)));
                    $result = true;
                } else {
                    $result = new WP_Error("refund failed", 'woocommerce-gateway-cardknox');
                }
            }
        }

        return $result;
    }

    private function getRefundCommand($amount, $order, $captured)
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

    private function getRefundMessage($response, $reason)
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
    public function send_failed_order_email($orderId)
    {
        $emails = WC()->mailer()->get_emails();
        if (!empty($emails) && !empty($orderId)) {
            $emails['WC_Email_Failed_Order']->trigger($orderId);
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
            echo '<div id="ap-container" class="ap hidden" style="min-height:55px;"></div><br/>';
            echo '<div class="messages">';
            echo '<div class="message message-error error applepay-error" style="display: none;"></div>';
            echo '</div>';
        }
    }

    /**
     * Apple Pay available based on specific countries.
     *
     * @param [type] $available_gateways
     * @return void
     */
    public function cardknox_allow_payment_method_by_country($available_gateways)
    {

        if (is_admin()) return $available_gateways;

        $applicable_countries = $this->applepay_applicable_countries;
        $specific_countries    = $this->applepay_specific_countries;

        if (isset($applicable_countries) && $applicable_countries == 1) {
            // Get the customer's billing and shipping addresses
            $billing_country = WC()->customer->get_billing_country();

            // Define the country codes for which you want to allow the payment method
            $enabled_countries = $specific_countries; // Add the country codes to this array

            // Check if the billing or shipping address country is in the allow countries array
            if (!in_array($billing_country, $enabled_countries)) {
                // allow the payment method by unsetting it from the available gateways
                unset($available_gateways['cardknox-applepay']);
            }
        }
        return $available_gateways;
    }
}
