<?php
/*
Plugin Name: WooCommerce Cardknox Gateway
Description: Accept payments via credit card, Apple Pay, Google Pay, and manage transactions within WordPress.
Author: Sola Payments.
Author URI: https://solapayments.com/
Version: 1.2.81
Requires at least: 4.4
Tested up to: 6.7.1
WC requires at least: 2.5
WC tested up to: 8.4.0
WooCommerce Subscriptions tested up to: 6.7.0
Text Domain: woocommerce-gateway-cardknox
Domain Path: /languages
Requires Plugins: woocommerce

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
 * Required minimums and constants
 */
define('WC_CARDKNOX_VERSION', '1.2.81');
define('WC_CARDKNOX_MIN_PHP_VER', '5.6.0');
define('WC_CARDKNOX_MIN_WC_VER', '2.5.0');
define('WC_CARDKNOX_MAIN_FILE', __FILE__);
define('WC_CARDKNOX_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_CARDKNOX_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

define( 'CARDKNOX_IFIELDS_URL', 'https://cdn.cardknox.com/ifields/3.0.2503.2101/ifields.min.js' );



/** 
 * WordPress version Check, if WordPress Version 6.5 or Higher
 * then Cardknox Plugin will Activate otherwise Deactivate.
 */ 
if (version_compare(get_bloginfo('version'), '6.5', '<')) {
    // Display admin notice
    add_action('admin_notices', function () {
        echo '<div class="error"><p>';
        esc_html_e(
            'WooCommerce Sola Gateway requires WordPress version 6.5 or higher. Please update WordPress to use this plugin.',
            'woocommerce-gateway-cardknox'
        );
        echo '</p></div>';
    });

    // Deactivate the plugin
    add_action('admin_init', function () {
        deactivate_plugins(plugin_basename(__FILE__));
    });

    // Stop further execution of the plugin
    return;
}


add_action('init', function() {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once __DIR__ . '/includes/class-wc-gateway-cardknox-blocks.php';              // card
        require_once __DIR__ . '/includes/class-wc-cardknox-applepay-blocks-support.php';     // apple

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry) {
                $registry->register(new WC_Gateway_Cardknox_Blocks_Support());

                $apple = new WC_Cardknox_ApplePay_Blocks_Support();
                if ($apple->is_active()) {
                    $registry->register($apple);
                }
            }
        );
    }
}, 5);


if (!class_exists('WC_Cardknox')) :

    class WC_Cardknox
    {

        /**
         * @var Singleton The reference the *Singleton* instance of this class
         */
        private static $instance;

        /**
         * @var Reference to logging class.
         */
        private static $log;

        /**
         * Returns the *Singleton* instance of this class.
         *
         * @return Singleton The *Singleton* instance.
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private clone method to prevent cloning of the instance of the
         * *Singleton* instance.
         *
         * @return void
         */
        private function __clone() {}

        /**
         * Private unserialize method to prevent unserializing of the *Singleton*
         * instance.
         *
         * @return void
         */
        public function __wakeup() {}

        /**
         * Flag to indicate whether or not we need to load code for / support subscriptions.
         *
         * @var bool
         */
        private $subscription_support_enabled = false;

        /**
         * Flag to indicate whether or not we need to load support for pre-orders.
         *
         * @since 3.0.3
         *
         * @var bool
         */
        private $pre_order_enabled = false;

        /**
         * Notices (array)
         * @var array
         */
        public $notices = array();
        /**
         * settingPage variable
         */
        public $settingPage;

        /**
         * Protected constructor to prevent creating a new instance of the
         * *Singleton* via the `new` operator from outside of this class.
         */
        protected function __construct()
        {
            add_action('admin_init', array($this, 'check_environment'));
            add_action('admin_notices', array($this, 'admin_notices'), 15);
            add_action('init', array($this, 'init_plugin'));
            add_action('init', array($this, 'register_block_scripts'));
            add_action('wp_enqueue_scripts', array($this, 'quickChekoutPaymentScripts'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_block_styles'));

            add_action('wp_ajax_update_cart_total', array($this, 'updateCartTotal'));
            add_action('wp_ajax_nopriv_update_cart_total', array($this, 'updateCartTotal'));

            add_action('wp_ajax_cardknox_create_order', array($this, 'googlepayCardknoxCreateorder'));
            add_action('wp_ajax_nopriv_cardknox_create_order', array($this, 'googlepayCardknoxCreateorder'));

            add_action('wp_ajax_applepay_cardknox_create_order', array($this, 'applepayCardknoxCreateorder'));
            add_action('wp_ajax_nopriv_applepay_cardknox_create_order', array($this, 'applepayCardknoxCreateorder'));
        }

        /**
         * Register scripts for block editor support
         */
        public function register_block_scripts() {
            if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                return;
            }
        
            // Only handle assets specific to Blocks checkout here; the Blocks integration
            // class is responsible for registering/enqueuing the JS handle.
            $script_path = '/blocks/build/index.js';
            if (!file_exists(WC_CARDKNOX_PLUGIN_PATH . $script_path)) {
                return;
            }
            
            // Register styles (enqueue only when Blocks checkout is active)
            wp_register_style(
                'wc-cardknox-blocks-style',
                WC_CARDKNOX_PLUGIN_URL . '/blocks/src/style.css',
                array(),
                WC_CARDKNOX_VERSION . '.' . time()
            );
            if ( $this->isBlocksCheckoutActive() ) {
                wp_enqueue_style('wc-cardknox-blocks-style');
            }
        
            // Load iFields SDK
            if ( $this->isBlocksCheckoutActive() ) {
                wp_register_script(
                    'cardknox-ifields',
                    CARDKNOX_IFIELDS_URL,
                    array(),
                    '3.0.2503.2101',
                    false
                );
                if (! wp_script_is('cardknox-ifields', 'enqueued')) {
                    wp_enqueue_script('cardknox-ifields');
                }
            }
        
            // Pass data to the block scripts
            wp_localize_script('wc-cardknox-blocks', 'wc_cardknox_blocks_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'ifields_cdn_url' => CARDKNOX_IFIELDS_URL,
                'card_logos_url' => WC_CARDKNOX_PLUGIN_URL . '/images/card-logos.png',
                'nonce' => wp_create_nonce('wc-cardknox-blocks'),
            ));
        }
        
        /**
         * Enqueue block styles on checkout pages
         */
        public function enqueue_block_styles() {
            if ( ! is_admin() && $this->isBlocksCheckoutActive() ) {
                wp_enqueue_style(
                    'wc-cardknox-blocks-style',
                    WC_CARDKNOX_PLUGIN_URL . '/blocks/src/style.css',
                    array(),
                    WC_CARDKNOX_VERSION . '.' . time()
                );
            }
        }

        /**
         * Detect whether the site is using the Checkout Block on the checkout page.
         */
        private function isBlocksCheckoutActive() {
            if ( ! function_exists('has_block') ) {
                return false;
            }
            $checkout_page_id = wc_get_page_id('checkout');
            if ( $checkout_page_id && $checkout_page_id !== -1 ) {
                return has_block('woocommerce/checkout', $checkout_page_id);
            }
            return false;
        }
        
        /*
         * Initialize the plugin functionality as per the standard
        */
        public function init_plugin() {
            if (self::get_environment_warning()) {
                return;
            }
        
            load_plugin_textdomain('woocommerce-gateway-cardknox', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
            include_once(dirname(__FILE__) . '/includes/class-wc-cardknox-api.php');
        
            if (class_exists('WC_Payment_Gateway_CC')) {
                include_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox.php');
                include_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox-applepay.php');
                include_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox-googlepay.php');
            } else {
                include_once(dirname(__FILE__) . '/includes/legacy/class-wc-gateway-cardknox.php');
            }
        
            $this->init_gateways();
        
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
            add_action('woocommerce_order_status_on-hold_to_processing', array($this, 'capture_payment'));
            add_action('woocommerce_order_status_on-hold_to_completed', array($this, 'capture_payment'));
            add_action('woocommerce_order_status_on-hold_to_cancelled', array($this, 'refund_payment'));
            add_action('woocommerce_order_status_on-hold_to_refunded', array($this, 'refund_payment'));
            add_action('woocommerce_order_status_processing_to_cancelled', array($this, 'refund_payment'));
            add_action('woocommerce_order_status_processing_to_completed', array($this, 'capture_payment'));
            $this->settingPage = 'admin.php?page=wc-settings&tab=checkout&section=';
            add_action('wp_ajax_nopriv_get_data', array($this, 'threedsAjaxHandler'));
            add_action('wp_ajax_get_data', array($this, 'threedsAjaxHandler'));
        }
        /**
         * Allow this class and other classes to add slug keyed notices (to avoid duplication)
         */
        public function add_admin_notice($slug, $class, $message)
        {
            $this->notices[$slug] = array(
                'class'   => $class,
                'message' => $message,
            );
        }

        /**
         * The backup sanity check, in case the plugin is activated in a weird way,
         * or the environment changes after activation. Also handles upgrade routines.
         */
        public function check_environment()
        {
            if (!defined('IFRAME_REQUEST') && (WC_CARDKNOX_VERSION !== get_option('wc_cardknox_version'))) {
                $this->install();

                do_action('woocommerce_cardknox_updated');
            }

            $environment_warning = self::get_environment_warning();

            if ($environment_warning && is_plugin_active(plugin_basename(__FILE__))) {
                $this->add_admin_notice('bad_environment', 'error', $environment_warning);
            }

            // Check if secret key present. Otherwise prompt, via notice, to go to
            // setting.
            if (!class_exists('WC_Cardknox_API')) {
                include_once(dirname(__FILE__) . '/includes/class-wc-cardknox-api.php');
            }

            $secret = WC_Cardknox_API::getTransactionKey();

            if (empty($secret) && !(isset($_GET['page'], $_GET['section']) && 'wc-settings' === $_GET['page'] && 'cardknox' === $_GET['section'])) {
                $setting_link = $this->get_setting_link();
                $this->add_admin_notice('prompt_connect', 'notice notice-warning', sprintf(__('Sola is almost ready. To get started, <a href="%s">set your Sola account keys</a>.', 'woocommerce-gateway-cardknox'), $setting_link));
            }
        }

        /**
         * Updates the plugin version in db
         *
         * @since 3.1.0
         * @version 3.1.0
         * @return bool
         */
        private static function _update_plugin_version()
        {
            delete_option('wc_cardknox_version');
            update_option('wc_cardknox_version', WC_CARDKNOX_VERSION);

            return true;
        }

        /**
         * Handles upgrade routines.
         *
         * @since 3.1.0
         * @version 3.1.0
         */
        public function install()
        {
            if (!defined('WC_CARDKNOX_INSTALLING')) {
                define('WC_CARDKNOX_INSTALLING', true);
            }

            $this->_update_plugin_version();
        }

        /**
         * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
         * found or false if the environment has no problems.
         */
        public static function get_environment_warning()
        {
            if (version_compare(phpversion(), WC_CARDKNOX_MIN_PHP_VER, '<')) {
                $message = __('WooCommerce Sola - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-cardknox');

                return sprintf($message, WC_CARDKNOX_MIN_PHP_VER, phpversion());
            }

            if (!defined('WC_VERSION')) {
                return __('WooCommerce Sola requires WooCommerce to be activated to work.', 'woocommerce-gateway-cardknox');
            }

            if (version_compare(WC_VERSION, WC_CARDKNOX_MIN_WC_VER, '<')) {
                $message = __('WooCommerce Sola - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-cardknox');

                return sprintf($message, WC_CARDKNOX_MIN_WC_VER, WC_VERSION);
            }

            if (!function_exists('curl_init')) {
                return __('WooCommerce Sola - cURL is not installed.', 'woocommerce-gateway-cardknox');
            }

            return false;
        }

        public function generateLink($link)
        {
            return '<a href="' . $link . '">';
        }
        /**
         * Adds plugin action links
         *
         * @since 1.0.0
         */
        public function plugin_action_links($links)
        {
            $setting_link = $this->get_setting_link();

            $plugin_links = array(
                $this->generateLink($setting_link) . __('Settings', 'woocommerce-gateway-cardknox') . '</a>',
                '<a href="https://docs.woocommerce.com/document/cardknox/">' . __('Docs', 'woocommerce-gateway-cardknox') . '</a>',
                '<a href="https://woocommerce.com/contact-us/">' . __('Support', 'woocommerce-gateway-cardknox') . '</a>',
            );
            return array_merge($plugin_links, $links);
        }

        /**
         * Get setting link.
         *
         * @since 1.0.0
         *
         * @return string Setting link
         */
        public function get_setting_link()
        {
            $use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;

            $section_slug = $use_id_as_section ? 'cardknox' : strtolower('WC_Gateway_Cardknox');

            return admin_url($this->settingPage . $section_slug);
        }

        /**
         * Get apple pay setting link.
         *
         * @since 1.0.15
         *
         * @return string Setting link
         */
        public function get_setting_applepay_link()
        {
            $use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;

            $section_slug = $use_id_as_section ? 'cardknox-applepay' : strtolower('WC_Gateway_Cardknox');

            return admin_url($this->settingPage . $section_slug);
        }

        /**
         * Get google pay setting link.
         *
         * @since 1.0.15
         *
         * @return string Setting link
         */
        public function getSettingGooglepayLink()
        {
            $use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;

            $section_slug = $use_id_as_section ? 'cardknox-googlepay' : strtolower('WC_Gateway_Cardknox');

            return admin_url($this->settingPage . $section_slug);
        }

        /**
         * Display any notices we've collected thus far (e.g. for connection, disconnection)
         */
        public function admin_notices()
        {

            foreach ((array) $this->notices as $notice_key => $notice) {
                echo "<div class='" . esc_attr($notice['class']) . "'><p>";
                echo wp_kses($notice['message'], array('a' => array('href' => array())));
                echo '</p></div>';
            }
        }
        /*
         * Set Seprate Call init_gateways
        */
        public function init_gateways() {
            if (!class_exists('WC_Payment_Gateway')) {
                return;
            }
        
            add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
        
            if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
                $this->subscription_support_enabled = true;
            }
            if (class_exists('WC_Pre_Orders_Order')) {
                $this->pre_order_enabled = true;
            }
        
            $load_addons = ($this->subscription_support_enabled || $this->pre_order_enabled);
        
            if ($load_addons) {
                require_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox-addons.php');
            }
        }

        /**
         * Add the gateways to WooCommerce
         *
         * @since 1.0.0
         */
        public function add_gateways($methods)
        {
            if ($this->subscription_support_enabled || $this->pre_order_enabled) {
                $methods[] = 'WC_Gateway_Cardknox_Addons';
                $methods[] = 'WCCardknoxApplepay';
                $methods[] = 'WCCardknoxGooglepay';
            } else {
                $methods[] = 'WC_Gateway_Cardknox';
                $methods[] = 'WCCardknoxApplepay';
                $methods[] = 'WCCardknoxGooglepay';
            }
            return $methods;
        }

        /**
         * List of currencies supported by Cardknox that has no decimals.
         *
         * @return array $currencies
         */
        public static function no_decimal_currencies()
        {
            return array(
                'bif', // Burundian Franc
                'djf', // Djiboutian Franc
                'jpy', // Japanese Yen
                'krw', // South Korean Won
                'pyg', // Paraguayan Guaraní
                'vnd', // Vietnamese Đồng
                'xaf', // Central African Cfa Franc
                'xpf', // Cfp Franc
                'clp', // Chilean Peso
                'gnf', // Guinean Franc
                'kmf', // Comorian Franc
                'mga', // Malagasy Ariary
                'rwf', // Rwandan Franc
                'vuv', // Vanuatu Vatu
                'xof', // West African Cfa Franc
            );
        }

        /**
         * Capture payment when the order is changed from on-hold to complete or processing
         *
         * @param  int $order_id
         */
        public function capture_payment($order_id)
        {
            $order = wc_get_order($order_id);

            if ('cardknox' === (version_compare(WC_VERSION, '3.0.0', '<') ? $order->payment_method : $order->get_payment_method())) {
                $my_xrefnum   = get_post_meta($order_id, '_cardknox_xrefnum', true);

                $captured = get_post_meta($order_id, '_cardknox_transaction_captured', true);
                if ($my_xrefnum && 'no' === $captured) {
                    $result = WC_Cardknox_API::request(array(
                        'xAmount'   => $order->get_total(),
                        'xCommand' => 'cc:capture',
                        'xRefnum' => $my_xrefnum
                    ));

                    if (is_wp_error($result)) {
                        $order->add_order_note(__('Unable to capture transaction!', 'woocommerce-gateway-cardknox') . ' ' . $result->get_error_message());
                    } else {
                        $order->add_order_note(sprintf(__('Sola transaction captured (Charge ID: %s)', 'woocommerce-gateway-cardknox'), $result['xRefNum']));
                        update_post_meta($order_id, '_cardknox_transaction_captured', 'yes');

                        // Store other data such as fees
                        update_post_meta($order_id, 'Sola Payment ID', $result['xRefNum']);
                        update_post_meta($order_id, '_transaction_id', $result['xRefNum']);
                        $order->payment_complete($result['xRefNum']);
                    }
                }
            }
        }

        /**
         * Cancel pre-auth on refund/cancellation by changing the status in the admin panel
         *
         * @param  int $order_id
         */
        public function refund_payment($order_id)
        {
            $order = wc_get_order($order_id);

            if ('cardknox' === (version_compare(WC_VERSION, '3.0.0', '<') ? $order->payment_method : $order->get_payment_method())) {
                $my_xrefnum   = get_post_meta($order_id, '_cardknox_xrefnum', true);

                if ($my_xrefnum) {
                    $result = WC_Cardknox_API::request(array(
                        //						'xAmount' => $order->get_total(),
                        'xCommand' => 'cc:voidrefund',
                        'xRefNum' => $my_xrefnum
                    ));

                    if (is_wp_error($result)) {
                        $order->add_order_note(__('Unable to refund transaction!', 'woocommerce-gateway-cardknox') . ' ' . $result->get_error_message());
                    } else {
                        $order->add_order_note(sprintf(__('Sola transaction refunded (RefNum: %s)', 'woocommerce-gateway-cardknox'), $result['xRefNum']));
                        delete_post_meta($order_id, '_cardknox_transaction_captured');
                        delete_post_meta($order_id, '_cardknox_xrefnum');
                    }
                }
            }
        }

        /**
         * Checks Cardknox minimum order value authorized per currency
         */
        public static function get_minimum_amount()
        {
            // Check order amount
            switch (get_woocommerce_currency()) {
                case 'USD':
                case 'CAD':
                case 'EUR':
                case 'GBP':
                    $minimum_amount = 1;
                default:
                    $minimum_amount = 1;
                    break;
            }
            return $minimum_amount;
        }

        /**
         */
        public static function log($message)
        {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }

            self::$log->add('woocommerce-gateway-cardknox', $message);
        }

        public function updateCartTotal()
        {
            if (defined('DOING_AJAX') && DOING_AJAX) {
                // Calculate total
                $cart_total = WC()->cart->total;

                // Return response
                wp_send_json_success(array('total' => $cart_total));
            }
            wp_die();
        }

        /**
         * For cart page load gpay button.
         */
        public function quickChekoutPaymentScripts() {

            // ---- Read options safely (Google Pay) ----
            $options  = (array) get_option( 'woocommerce_cardknox-googlepay_settings', [] );
            $gpay_defaults = [
                'googlepay_quickcheckout'        => 'no',
                'googlepay_enabled'              => 'no',
                'googlepay_title'                => 'Google Pay',
                'googlepay_merchant_name'        => '',
                'googlepay_environment'          => 'TEST',
                'googlepay_button_style'         => 'black',
                'googlepay_capture'              => 'capture',   // or 'authorize'
                'googlepay_applicable_countries' => 'all',       // 'all' | 'specific'
                'googlepay_specific_countries'   => [],          // array of ISO codes
            ];
            $options = wp_parse_args( $options, $gpay_defaults );
        
            // ---- Read options safely (Apple Pay) ----
            $applePayoptions  = (array) get_option( 'woocommerce_cardknox-applepay_settings', [] );
            $apple_defaults = [
                'applepay_quickcheckout'         => 'no',
                'applepay_enabled'               => 'no',
                'applepay_title'                 => 'Apple Pay',
                'applepay_merchant_identifier'   => '',
                'applepay_environment'           => 'TEST',
                'applepay_button_style'          => 'black',
                'applepay_button_type'           => 'plain',
                'applepay_capture'               => 'capture',
                'applepay_applicable_countries'  => 'all',
                'applepay_specific_countries'    => [],
            ];
            $applePayoptions = wp_parse_args( $applePayoptions, $apple_defaults );
        
            $googlepay_quickcheckout = $options['googlepay_quickcheckout'];
            $applePay_quickcheckout  = $applePayoptions['applepay_quickcheckout'];
        
            // Cart page ke bahar kuch mat karo
            if ( ! is_cart() ) {
                return;
            }
        
            // Common iFields script
            wp_enqueue_script(
                'cardknox',
                'https://cdn.cardknox.com/ifields/3.0.2503.2101/ifields.min.js',
                [],
                '3.0.2503.2101',
                false
            );
        
            // Google Pay on cart
            if ( 'no' === $googlepay_quickcheckout ) {
                $this->handleGooglepayCartQuickcheckout( $options );
            }
        
            // Apple Pay on cart
            if ( 'no' === $applePay_quickcheckout ) {
                $this->handleApplepayCartQuickcheckout( $applePayoptions );
            }
        }
        
        
        private function handleGooglepayCartQuickcheckout( array $options ): void {

            wp_enqueue_style(
                'woocommerce_cardknox_gpay',
                plugins_url( '/assets/css/google-pay.css', WC_CARDKNOX_MAIN_FILE ),
                [],
                '1.0',
                'all'
            );
        
            wp_enqueue_script(
                'woocommerce_cardknox_google_pay',
                plugins_url( 'assets/js/cardknox-google-pay-cart.min.js', WC_CARDKNOX_MAIN_FILE ),
                [ 'jquery' ],
                '1.0',
                true
            );
        
            // Build shipping costs
            $shippingCostsGoogle = [];
            foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
                foreach ( $zone['shipping_methods'] as $shipping_method ) {
                    if ( $shipping_method instanceof WC_Shipping_Method ) {
                        $method_id   = $shipping_method->id;
                        $method_cost = ( $shipping_method instanceof WC_Shipping_Free_Shipping )
                            ? 0.00
                            : (float) $shipping_method->cost;
                        $shippingCostsGoogle[ $method_id ] = $method_cost;
                    }
                }
            }
        
            // Method labels
            $methods = [];
            foreach ( WC()->shipping()->get_shipping_methods() as $method ) {
                $methods[] = [
                    'id'          => $method->id,
                    'label'       => $method->method_title,
                    'description' => $method->method_description,
                ];
            }
        
            // Read normalized values
            $cardknoxGooglepaySettings = [
                'enabled'               => $options['googlepay_enabled'],
                'title'                 => $options['googlepay_title'],
                'merchant_name'         => $options['googlepay_merchant_name'],
                'environment'           => $options['googlepay_environment'],
                'button_style'          => $options['googlepay_button_style'],
                'payment_action'        => $options['googlepay_capture'],
                'applicable_countries'  => $options['googlepay_applicable_countries'],
                'specific_countries'    => (array) $options['googlepay_specific_countries'],
                'total'                 => (float) WC()->cart->total,
                'currencyCode'          => get_woocommerce_currency(),
                'shippingMethods'       => $methods,
                'shippingCosts'         => $shippingCostsGoogle,
                'ajax_url'              => admin_url( 'admin-ajax.php' ),
                'create_order_nonce'    => wp_create_nonce( 'create_order_nonce' ),
            ];
        
            wp_localize_script(
                'woocommerce_cardknox_google_pay',
                'googlePaysettings',
                $cardknoxGooglepaySettings
            );
        }
        
        private function handleApplepayCartQuickcheckout( array $applePayoptions ): void {

            wp_enqueue_style(
                'woocommerce_cardknox_applepay',
                plugins_url( '/assets/css/apple-pay.css', WC_CARDKNOX_MAIN_FILE ),
                [],
                '1.0',
                'all'
            );
        
            wp_enqueue_script(
                'woocommerce_cardknox_apple_pay',
                plugins_url( 'assets/js/cardknox-apple-pay-cart.min.js', WC_CARDKNOX_MAIN_FILE ),
                [ 'jquery' ],
                '1.0',
                true
            );
        
            $shippingCostsApple = [];
            foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
                foreach ( $zone['shipping_methods'] as $shipping_method ) {
                    if ( $shipping_method instanceof WC_Shipping_Method ) {
                        $method_id   = $shipping_method->id;
                        $method_cost = ( $shipping_method instanceof WC_Shipping_Free_Shipping )
                            ? 0.00
                            : (float) $shipping_method->cost;
                        $detail      = str_replace(
                            [ "\n", "\r" ],
                            '',
                            strip_tags( $shipping_method->get_method_description() )
                        );
        
                        $shippingCostsApple[] = [
                            'identifier' => $method_id,
                            'label'      => $shipping_method->get_method_title(),
                            'amount'     => number_format( $method_cost, 2, '.', '' ),
                            'detail'     => $detail,
                        ];
                    }
                }
            }
        
            $cardknoxApplepaySettings = [
                'enabled'               => $applePayoptions['applepay_enabled'],
                'title'                 => $applePayoptions['applepay_title'],
                'merchant_identifier'   => $applePayoptions['applepay_merchant_identifier'],
                'environment'           => $applePayoptions['applepay_environment'],
                'button_style'          => $applePayoptions['applepay_button_style'],
                'button_type'           => $applePayoptions['applepay_button_type'],
                'payment_action'        => $applePayoptions['applepay_capture'],
                'applicable_countries'  => $applePayoptions['applepay_applicable_countries'],
                'specific_countries'    => (array) $applePayoptions['applepay_specific_countries'],
                'total'                 => (float) WC()->cart->total,
                'shippingMethods'       => $shippingCostsApple,
                'ajax_url'              => admin_url( 'admin-ajax.php' ),
                'create_order_nonce'    => wp_create_nonce( 'create_order_nonce' ),
            ];
        
            wp_localize_script(
                'woocommerce_cardknox_apple_pay',
                'applePaysettings',
                $cardknoxApplepaySettings
            );
        }


        /**
         * For shipping method details.
         */
        public function getShippingMethodDetails($shipping_method_slug)
        {
            $shipping_methods = [];

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();
            foreach ($zones as $zone) {
                $zone_obj = new WC_Shipping_Zone($zone['id']);
                $zone_methods = $zone_obj->get_shipping_methods();

                foreach ($zone_methods as $method) {
                    if ($method->id === $shipping_method_slug) {
                        $shipping_methods[] = [
                            'zone_id' => $zone['id'],
                            'zone_name' => $zone['zone_name'],
                            'instance_id' => $method->instance_id,
                            'method_title' => $method->title,
                            'method_id' => $method->id . ':' . $method->instance_id,
                            'method_cost' => $method->cost, // Adding cost information
                        ];
                    }
                }
            }

            // Get the default zone (Zone 0)
            $default_zone = new WC_Shipping_Zone(0);
            $default_methods = $default_zone->get_shipping_methods();

            foreach ($default_methods as $method) {
                if ($method->id === $shipping_method_slug) {
                    $shipping_methods[] = [
                        'zone_id' => 0,
                        'zone_name' => 'Default Zone',
                        'instance_id' => $method->instance_id,
                        'method_title' => $method->title,
                        'method_id' => $method->id . ':' . $method->instance_id,
                        'method_cost' => $method->cost, // Adding cost information
                    ];
                }
            }

            return $shipping_methods;
        }
        /**
         * For Googlepay quick create order.
         */
        public function googlepayCardknoxCreateorder()
        {

            // Verify nonce
            check_ajax_referer('create_order_nonce', 'security');

            // Get and sanitize inputs
            $google_email = sanitize_text_field($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $shippingOptionData = json_decode(stripslashes($_POST['shippingOptionData']), true);
            $shippingAddress = json_decode(stripslashes($_POST['shippingAddress']), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid shipping address format', 'woocommerce'));
                wp_die();
            }

            $shipping_method_slug = $shippingOptionData['id'];
            $shipping_method_details = $this->getShippingMethodDetails($shipping_method_slug);

            // Create the order
            $order = wc_create_order();
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = wc_get_product($cart_item['product_id']);
                $order->add_product($product, $cart_item['quantity']);
            }

            // add shipping options
            $shipping = new WC_Order_Item_Shipping();
            $shipping->set_method_title($shipping_method_details[0]['method_title']);
            $shipping->set_method_id($shipping_method_details[0]['method_id']);
            $shipping->set_total($shipping_method_details[0]['method_cost']);
            $order->add_item($shipping);

            // add billing and shipping addresses
            $full_name = sanitize_text_field($shippingAddress['name']);
            $name_parts = explode(' ', $full_name);
            $first_name = array_shift($name_parts);
            $last_name = implode(' ', $name_parts);

            $address = array(
                'first_name'    => $first_name,
                'last_name'     => $last_name,
                'address_1'     => sanitize_text_field($shippingAddress['address1']),
                'address_2'     => sanitize_text_field($shippingAddress['address2']),
                'city'          => sanitize_text_field($shippingAddress['locality']),
                'state'         => sanitize_text_field($shippingAddress['administrativeArea']),
                'postcode'      => sanitize_text_field($shippingAddress['postalCode']),
                'country'       => sanitize_text_field($shippingAddress['countryCode']),
                'email'         => sanitize_email($google_email),
                'phone'         => $phone,
            );

            $order->set_address($address, 'billing');
            $order->set_address($address, 'shipping');

            // Set payment method and order status
            $order->set_payment_method('cardknox-googlepay');
            $order->set_payment_method_title('Sola Google Pay');
            $order->calculate_totals();
            $order->update_status('pending', __('Order pending payment', 'woocommerce'));

            // Process the payment
            $result = $order->payment_complete();

            if ($result) {
                WC()->cart->empty_cart();
                wp_send_json_success(['redirect_url' => $order->get_checkout_order_received_url()]);
            } else {
                wp_send_json_error(__('Payment failed', 'woocommerce'));
            }

            wp_die();
        }
        /**
         * For Applepay quick create order.
         */
        public function applepayCardknoxCreateorder()
        {

            // Verify nonce
            check_ajax_referer('create_order_nonce', 'security');

            $billingContact = json_decode(stripslashes($_POST['billingContact']), true);
            $shippingContact = json_decode(stripslashes($_POST['shippingContact']), true);
            $selectedShipping = json_decode(stripslashes($_POST['selectedShipping']), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid shipping address format', 'woocommerce'));
                wp_die();
            }

            // Create the order
            $order = wc_create_order();
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = wc_get_product($cart_item['product_id']);
                $order->add_product($product, $cart_item['quantity']);
            }

            $shipping_method_details = $this->getShippingMethodDetails($selectedShipping['identifier']);

            // add shipping options
            $shipping = new WC_Order_Item_Shipping();
            $shipping->set_method_title($shipping_method_details[0]['method_title']);
            $shipping->set_method_id($shipping_method_details[0]['method_id']);
            $shipping->set_total($shipping_method_details[0]['method_cost']);
            $order->add_item($shipping);

            // add billing and shipping addresses
            $billing = array(
                'first_name'    => $billingContact['firstName'],
                'last_name'     => $billingContact['billingLastName'],
                'address_1'     => sanitize_text_field($billingContact['address']),
                'address_2'     => '',
                'city'          => sanitize_text_field($billingContact['city']),
                'state'         => sanitize_text_field($billingContact['administrativeArea']),
                'postcode'      => sanitize_text_field($billingContact['postcode']),
                'country'       => sanitize_text_field($billingContact['country']),
                'email'         => sanitize_email($billingContact['emailAddress']),
                'phone'         => sanitize_text_field($shippingContact['phoneNumber'])
            );

            $shipping = array(
                'first_name'    => $shippingContact['firstName'],
                'last_name'     => $shippingContact['billingLastName'],
                'address_1'     => sanitize_text_field($shippingContact['address']),
                'address_2'     => '',
                'city'          => sanitize_text_field($shippingContact['city']),
                'state'         => sanitize_text_field($shippingContact['administrativeArea']),
                'postcode'      => sanitize_text_field($shippingContact['postcode']),
                'country'       => sanitize_text_field($shippingContact['country']),
                'email'         => sanitize_email($shippingContact['emailAddress']),
                'phone'         => sanitize_text_field($shippingContact['phoneNumber'])
            );

            $order->set_address($billing, 'billing');
            $order->set_address($shipping, 'shipping');

            // Set payment method and order status
            $order->set_payment_method('cardknox-applepay');
            $order->set_payment_method_title('Sola Apple Pay');
            $order->calculate_totals();
            $order->update_status('pending', __('Order pending payment', 'woocommerce'));

            // Process the payment
            $result = $order->payment_complete();

            if ($result) {
                WC()->cart->empty_cart();
                wp_send_json_success(['redirect_url' => $order->get_checkout_order_received_url()]);
            } else {
                wp_send_json_error(__('Payment failed', 'woocommerce'));
            }

            wp_die();
        }
        /**
         * 3ds API verificaion
         */
        public function threedsAjaxHandler()
        {

            $apiUrl = "https://x1.cardknox.com/verify";

            
            $options = get_option('woocommerce_cardknox_settings');
            if (is_serialized($options)) {
                $options = maybe_unserialize($options); 
            }
            $transaction_key = isset($options['transaction_key']) ? $options['transaction_key'] : null;
            $request = $_POST;
            $request['xKey'] = $transaction_key;
        
            unset($request['action']);

            $response = wp_safe_remote_post(
                $apiUrl,
                array(
                    'method'     => 'POST',
                    'body'       => $request,
                    'timeout'    => 70
                )
            );

            $parsedResponse = [];
            parse_str($response['body'], $parsedResponse);

            try {
                if ($parsedResponse['xResult'] === "E" || $parsedResponse['xResult'] === "D") {
                    wc_add_notice($parsedResponse['xError'], 'error');
                    $this->log(sprintf(__('Error: %s', 'woocommerce-gateway-cardknox'), $parsedResponse['xError']));

                    return wp_send_json($parsedResponse);
                } else {

                    $paymentInfo = new WC_Gateway_Cardknox();

                    $order  = wc_get_order($parsedResponse['xInvoice']);
                    $redirect = $order->get_checkout_order_received_url();

                    if (is_wp_error($parsedResponse)) {
                        $order->add_order_note($parsedResponse->get_error_message());
                        throw new Exception("The transaction was declined please try again");
                    }

                    $this->log("Info: set_transaction_id");
                    $order->set_transaction_id($parsedResponse['xRefNum']);
                    
                    
                    $this->log("Info: save_payment");
                    $paymentInfo->save_payment($forceCustomer, $parsedResponse);
                    $this->log("Info: save_payment forceCustomer=".$forceCustomer );
                    $this->log("Info: parsedResponse parsedResponse=".$parsedResponse );

                    $this->log("Info: process_response");
                    $paymentInfo->process_response($parsedResponse, $order);

                    $this->log("Info: empty_cart");

                    WC()->cart->empty_cart();

                    $this->log("Info: wc_gateway_cardknox_process_payment");
                    do_action('wc_gateway_cardknox_process_payment', $parsedResponse, $order);

                    $this->log("Info: thank you page redirect");

                    $parsedResponse['redirect'] = $redirect;

                    return wp_send_json($parsedResponse);
                }
            } catch (Exception $e) {
                wc_add_notice($e->getMessage(), 'error');
                $this->log(sprintf(__('Error: %s', 'woocommerce-gateway-cardknox'), $e->getMessage()));

                return $e->getMessage();
            }

            die();
        }
    }

    /*
       Declare compatibility with WooCommerce HPOS
    */
    add_action('before_woocommerce_init', function() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    });


    $GLOBALS['wc_cardknox'] = WC_Cardknox::get_instance();

endif;
