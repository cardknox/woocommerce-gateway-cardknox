<?php
/*
Plugin Name: WooCommerce Cardknox Gateway
Description: Accept payments via credit card, Apple Pay, Google Pay, and manage transactions within WordPress.
Author: Sola Payments.
Author URI: https://solapayments.com/
Version: 1.2.94
Requires at least: 6.5
Tested up to: 7.0
WC requires at least: 2.5
WC tested up to: 10.8.1
WooCommerce Subscriptions tested up to: 8.6.1
Text Domain: woo-cardknox-gateway
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
define('WC_CARDKNOX_VERSION', '1.2.94');
define('WC_CARDKNOX_MIN_PHP_VER', '8.2');
define('WC_CARDKNOX_MIN_WC_VER', '8.8');
define('WC_CARDKNOX_MAIN_FILE', __FILE__);
define('WC_CARDKNOX_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_CARDKNOX_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

define( 'CARDKNOX_IFIELDS_URL', 'https://cdn.cardknox.com/ifields/3.5.2607.1401/ifields.min.js' );



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
            'woo-cardknox-gateway'
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

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry) {
                $registry->register(new WC_Gateway_Cardknox_Blocks_Support());
            }
        );
    }
}, 5);

/**
 * Detect whether the current request is using WooCommerce Checkout Block.
 *
 * @return bool
 */
function wc_cardknox_is_blocks_checkout_active() {
    if ( ! function_exists( 'has_block' ) ) {
        return false;
    }

    if ( is_checkout() && has_block( 'woocommerce/checkout' ) ) {
        return true;
    }

    if ( function_exists( 'wc_get_page_id' ) ) {
        $checkout_page_id = wc_get_page_id( 'checkout' );

        if ( $checkout_page_id && $checkout_page_id !== -1 ) {
            return has_block( 'woocommerce/checkout', $checkout_page_id );
        }
    }

    return false;
}


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
            
            add_action('init', array($this, 'register_quickcheckout_ajax_handlers'), 20);
            
        }

        
        /**
         * Register cart quick-checkout AJAX endpoints only when the related
         * gateway and quick-checkout feature are enabled.
         */
        public function register_quickcheckout_ajax_handlers()
        {
            $gpay_options = wp_parse_args(
                (array) get_option('woocommerce_cardknox-googlepay_settings', array()),
                array(
                    'googlepay_enabled'       => 'no',
                    'googlepay_quickcheckout' => 'no',
                )
            );
            $apay_options = wp_parse_args(
                (array) get_option('woocommerce_cardknox-applepay_settings', array()),
                array(
                    'applepay_enabled'       => 'no',
                    'applepay_quickcheckout' => 'no',
                )
            );

            // Option is "Disable Quick Checkout": 'no' = show on cart, 'yes' = disabled.
            $gpay_quickcheckout_enabled = ('yes' === $gpay_options['googlepay_enabled']
                && 'no' === $gpay_options['googlepay_quickcheckout']);
            $apay_quickcheckout_enabled = ('yes' === $apay_options['applepay_enabled']
                && 'no' === $apay_options['applepay_quickcheckout']);

            if ($gpay_quickcheckout_enabled || $apay_quickcheckout_enabled) {
                add_action('wp_ajax_update_cart_total', array($this, 'updateCartTotal'));
                add_action('wp_ajax_nopriv_update_cart_total', array($this, 'updateCartTotal'));
                add_action('wp_ajax_cardknox_wallet_calculate_totals', array($this, 'cardknoxWalletCalculateTotals'));
                add_action('wp_ajax_nopriv_cardknox_wallet_calculate_totals', array($this, 'cardknoxWalletCalculateTotals'));
            }

            if ($gpay_quickcheckout_enabled) {
                add_action('wp_ajax_cardknox_create_order', array($this, 'googlepayCardknoxCreateorder'));
                add_action('wp_ajax_nopriv_cardknox_create_order', array($this, 'googlepayCardknoxCreateorder'));
            }

            if ($apay_quickcheckout_enabled) {
                add_action('wp_ajax_applepay_cardknox_create_order', array($this, 'applepayCardknoxCreateorder'));
                add_action('wp_ajax_nopriv_applepay_cardknox_create_order', array($this, 'applepayCardknoxCreateorder'));
            }
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
                    '3.5.2607.1401',
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

            $handle = 'cardknox-ifields';

            wp_add_inline_script(
                $handle,
                'window.wcCardknoxData = ' . wp_json_encode(
                    array(
                        'cardLogoUrl' => WC_CARDKNOX_PLUGIN_URL . '/images/card-logos.png',
                    )
                ) . ';',
                'before'
            );

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

            return wc_cardknox_is_blocks_checkout_active();
        }
        
        /*
         * Initialize the plugin functionality as per the standard
        */
        public function init_plugin() {
            if (self::get_environment_warning()) {
                return;
            }
        
            load_plugin_textdomain('woo-cardknox-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
            include_once(dirname(__FILE__) . '/includes/class-wc-cardknox-api.php');
        
            if (class_exists('WC_Payment_Gateway_CC')) {
                include_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox.php');
                include_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox-applepay.php');
                include_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox-googlepay.php');
            } else {
                include_once(dirname(__FILE__) . '/includes/legacy/class-wc-gateway-cardknox.php');
            }

            if (class_exists('WC_Payment_Gateway')) {
                include_once(dirname(__FILE__) . '/includes/class-wc-gateway-cardknox-ach.php');
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
                $this->add_admin_notice('prompt_connect', 'notice notice-warning', sprintf(__('Sola is almost ready. To get started, <a href="%s">set your Sola account keys</a>.', 'woo-cardknox-gateway'), $setting_link));
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
                $message = __('WooCommerce Sola - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woo-cardknox-gateway');

                return sprintf($message, WC_CARDKNOX_MIN_PHP_VER, phpversion());
            }

            if (!defined('WC_VERSION')) {
                return __('WooCommerce Sola requires WooCommerce to be activated to work.', 'woo-cardknox-gateway');
            }

            if (version_compare(WC_VERSION, WC_CARDKNOX_MIN_WC_VER, '<')) {
                $message = __('WooCommerce Sola - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woo-cardknox-gateway');

                return sprintf($message, WC_CARDKNOX_MIN_WC_VER, WC_VERSION);
            }

            if (!function_exists('curl_init')) {
                return __('WooCommerce Sola - cURL is not installed.', 'woo-cardknox-gateway');
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
                $this->generateLink($setting_link) . __('Settings', 'woo-cardknox-gateway') . '</a>',
                '<a href="https://docs.woocommerce.com/document/cardknox/">' . __('Docs', 'woo-cardknox-gateway') . '</a>',
                '<a href="https://woocommerce.com/contact-us/">' . __('Support', 'woo-cardknox-gateway') . '</a>',
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
                $methods[] = 'WCCardknoxACH';
            } else {
                $methods[] = 'WC_Gateway_Cardknox';
                $methods[] = 'WCCardknoxApplepay';
                $methods[] = 'WCCardknoxGooglepay';
                $methods[] = 'WCCardknoxACH';
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
                        $order->add_order_note(__('Unable to capture transaction!', 'woo-cardknox-gateway') . ' ' . $result->get_error_message());
                    } else {
                        $order->add_order_note(sprintf(__('Sola transaction captured (Charge ID: %s)', 'woo-cardknox-gateway'), $result['xRefNum']));
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
                        $order->add_order_note(__('Unable to refund transaction!', 'woo-cardknox-gateway') . ' ' . $result->get_error_message());
                    } else {
                        $order->add_order_note(sprintf(__('Sola transaction refunded (RefNum: %s)', 'woo-cardknox-gateway'), $result['xRefNum']));
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
         * Recalculate cart totals for Apple Pay / Google Pay sheet updates when
         * the shopper changes address or shipping method inside the wallet UI.
         */
        public function cardknoxWalletCalculateTotals()
        {
            check_ajax_referer('create_order_nonce', 'security');

            if (!WC()->cart || WC()->cart->is_empty()) {
                wp_send_json_error(__('Your cart is empty.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $address_raw = json_decode(stripslashes($_POST['address'] ?? ''), true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($address_raw) || !is_array($address_raw)) {
                wp_send_json_error(__('Invalid shipping address format', 'woocommerce'));
                wp_die();
            }

            $address = $this->normalize_wallet_shipping_address($address_raw);
            if (empty($address['country'])) {
                wp_send_json_error(__('Invalid shipping address format', 'woocommerce'));
                wp_die();
            }

            // Optional billing address for tax-based-on-billing stores.
            // Leave customer billing alone when not provided (e.g. Google Pay sheet updates).
            $billing = null;
            $billing_raw = isset($_POST['billing_address']) ? stripslashes($_POST['billing_address']) : '';
            if ($billing_raw !== '') {
                $billing_decoded = json_decode($billing_raw, true);
                if (is_array($billing_decoded) && !empty($billing_decoded)) {
                    $billing_normalized = $this->normalize_wallet_shipping_address($billing_decoded);
                    if (!empty($billing_normalized['country'])) {
                        $billing = $billing_normalized;
                    }
                }
            }

            $shipping_method_slug = sanitize_text_field(wp_unslash($_POST['shipping_method'] ?? ''));
            $rates = $this->get_shipping_rates_for_address($address, $billing);

            if (empty($rates)) {
                wp_send_json_error(__('Shipping is not available for the given address.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $chosen = null;
            if (!empty($shipping_method_slug)) {
                foreach ($rates as $rate) {
                    // Match only full rate ids (e.g. flat_rate:3), never method types.
                    if ($rate['method_id'] === $shipping_method_slug) {
                        $chosen = $rate;
                        break;
                    }
                }
            }
            if (!$chosen) {
                $chosen = $rates[0];
            }

            $this->apply_chosen_shipping_method($chosen['method_id']);

            $shipping_methods_apple = array();
            $shipping_methods_google = array();
            $shipping_costs_google = array();

            foreach ($rates as $rate) {
                $amount = number_format((float) $rate['method_cost'], 2, '.', '');
                // Full WooCommerce rate id so Standard vs Express stay distinct.
                $wallet_id = $rate['method_id'];

                $shipping_methods_apple[] = array(
                    'identifier' => $wallet_id,
                    'label'      => $rate['method_title'],
                    'amount'     => $amount,
                    'detail'     => '',
                );

                $shipping_methods_google[] = array(
                    'id'          => $wallet_id,
                    'label'       => $rate['method_title'],
                    'description' => '',
                );
                $shipping_costs_google[$wallet_id] = (float) $rate['method_cost'];
            }

            wp_send_json_success(
                array(
                    'total'                  => number_format((float) WC()->cart->get_total('edit'), 2, '.', ''),
                    'subtotal'               => number_format((float) WC()->cart->get_subtotal(), 2, '.', ''),
                    'shipping_total'         => number_format((float) WC()->cart->get_shipping_total(), 2, '.', ''),
                    'tax_total'              => number_format((float) WC()->cart->get_total_tax(), 2, '.', ''),
                    'chosen_shipping_method' => $chosen['method_id'],
                    'shipping_methods_apple' => $shipping_methods_apple,
                    'shipping_methods_google'=> $shipping_methods_google,
                    'shipping_costs_google'  => $shipping_costs_google,
                    'currency'               => get_woocommerce_currency(),
                )
            );
            wp_die();
        }

        /**
         * Normalize Apple Pay / Google Pay address payloads into WC-style keys.
         *
         * @param array $address_raw Raw address from the wallet sheet.
         * @return array
         */
        private function normalize_wallet_shipping_address(array $address_raw)
        {
            $address1 = $address_raw['address_1']
                ?? $address_raw['address1']
                ?? $address_raw['address']
                ?? '';
            if (is_array($address1)) {
                $address1 = implode(' ', array_filter($address1));
            }

            $first_name = sanitize_text_field($address_raw['first_name'] ?? $address_raw['firstName'] ?? '');
            $last_name  = sanitize_text_field($address_raw['last_name'] ?? $address_raw['lastName'] ?? '');
            if ($first_name === '' && $last_name === '' && !empty($address_raw['name'])) {
                $name_parts = preg_split('/\s+/', trim(sanitize_text_field($address_raw['name'])), 2);
                $first_name = $name_parts[0] ?? '';
                $last_name  = $name_parts[1] ?? '';
            }

            $phone = sanitize_text_field(
                $address_raw['phone']
                    ?? $address_raw['phoneNumber']
                    ?? ''
            );

            return array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'address_1'  => sanitize_text_field($address1),
                'address_2'  => sanitize_text_field($address_raw['address_2'] ?? $address_raw['address2'] ?? ''),
                'city'       => sanitize_text_field($address_raw['city'] ?? $address_raw['locality'] ?? ''),
                'state'      => sanitize_text_field($address_raw['state'] ?? $address_raw['administrativeArea'] ?? ''),
                'postcode'   => sanitize_text_field($address_raw['postcode'] ?? $address_raw['postalCode'] ?? ''),
                'country'    => sanitize_text_field($address_raw['country'] ?? $address_raw['countryCode'] ?? ''),
                'phone'      => $phone,
            );
        }

        /**
         * Get live shipping rates for a wallet shipping address.
         *
         * @param array $address Normalized address.
         * @param array      $address Shipping address.
         * @param array|null $billing Optional billing address for tax calculation.
         * @return array[] List of { method_id, method_title, method_cost }
         */
        private function get_shipping_rates_for_address(array $address, $billing = null)
        {
            if (empty($address['country']) || !WC()->customer || !WC()->cart) {
                return array();
            }

            $this->apply_wallet_customer_locations($address, $billing);

            WC()->shipping()->reset_shipping();
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();

            $rates = array();
            $packages = WC()->shipping()->get_packages();
            foreach ($packages as $package) {
                if (empty($package['rates']) || !is_array($package['rates'])) {
                    continue;
                }
                foreach ($package['rates'] as $rate_id => $rate) {
                    if (!is_object($rate)) {
                        continue;
                    }
                    $rates[] = array(
                        'method_id'    => $rate_id,
                        'method_title' => $rate->get_label(),
                        'method_cost'  => (float) $rate->get_cost(),
                    );
                }
            }

            return $rates;
        }

        /**
         * Collect enabled shipping method instances as wallet options.
         *
         * Uses full rate ids (method_id:instance_id) so multiple flat_rate
         * instances stay distinct. Costs here are a page-load fallback; the
         * wallet recalculates from the shopper's package via AJAX.
         *
         * @return array{google_methods: array, google_costs: array, apple_methods: array}
         */
        private function get_wallet_shipping_method_catalog()
        {
            $google_methods = array();
            $google_costs   = array();
            $apple_methods  = array();
            $seen           = array();

            $zones = WC_Shipping_Zones::get_zones();
            $zones[0] = array(
                'shipping_methods' => WC_Shipping_Zones::get_zone(0)->get_shipping_methods(true),
            );

            foreach ($zones as $zone) {
                if (empty($zone['shipping_methods']) || !is_array($zone['shipping_methods'])) {
                    continue;
                }

                foreach ($zone['shipping_methods'] as $shipping_method) {
                    if (!($shipping_method instanceof WC_Shipping_Method) || !$shipping_method->is_enabled()) {
                        continue;
                    }

                    $rate_id = method_exists($shipping_method, 'get_rate_id')
                        ? $shipping_method->get_rate_id()
                        : $shipping_method->id . ':' . (int) $shipping_method->instance_id;

                    if (isset($seen[$rate_id])) {
                        continue;
                    }
                    $seen[$rate_id] = true;

                    $method_cost = ($shipping_method instanceof WC_Shipping_Free_Shipping)
                        ? 0.00
                        : (float) ($shipping_method->cost ?? 0);
                    $label = $shipping_method->get_method_title();
                    $detail = str_replace(
                        array("\n", "\r"),
                        '',
                        wp_strip_all_tags($shipping_method->get_method_description())
                    );

                    $google_methods[] = array(
                        'id'          => $rate_id,
                        'label'       => $label,
                        'description' => $detail,
                    );
                    $google_costs[$rate_id] = $method_cost;

                    $apple_methods[] = array(
                        'identifier' => $rate_id,
                        'label'      => $label,
                        'amount'     => number_format($method_cost, 2, '.', ''),
                        'detail'     => $detail,
                    );
                }
            }

            return array(
                'google_methods' => $google_methods,
                'google_costs'   => $google_costs,
                'apple_methods'  => $apple_methods,
            );
        }

        /**
         * Reject wallet checkout when the amount shown to the shopper does not
         * match the server-calculated order total.
         *
         * @param WC_Order $order         Order created from the cart.
         * @param string   $client_amount Amount posted from the wallet sheet.
         * @return true|WP_Error
         */
        private function validate_wallet_client_amount($order, $client_amount)
        {
            if ($client_amount === '' || $client_amount === null) {
                return true;
            }

            $client = (float) wc_format_decimal($client_amount);
            $server = (float) $order->get_total();

            if (abs($client - $server) > 0.01) {
                return new WP_Error(
                    'wallet_total_mismatch',
                    __('Your total has changed, please try again.', 'woo-cardknox-gateway')
                );
            }

            return true;
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
        
            
            // Option is "Disable Quick Checkout": show when gateway enabled AND disable checkbox is unchecked ('no').
            $show_googlepay_quickcheckout = ( 'yes' === $options['googlepay_enabled'] && 'no' === $googlepay_quickcheckout );
            $show_applepay_quickcheckout  = ( 'yes' === $applePayoptions['applepay_enabled'] && 'no' === $applePay_quickcheckout );

            // Do not load iFields on cart when both wallet quick-checkouts are disabled
            // (avoids "iframe with Google Pay button not found" console error).
            if ( ! $show_googlepay_quickcheckout && ! $show_applepay_quickcheckout ) {
                return;
            }

            // Common iFields script (only when a cart wallet button will render)
            wp_enqueue_script(
                'cardknox',
                CARDKNOX_IFIELDS_URL,
                [],
                '3.5.2607.1401',
                false
            );

            if ( $show_googlepay_quickcheckout ) {
                $this->handleGooglepayCartQuickcheckout( $options );
            }
        
            if ( $show_applepay_quickcheckout ) {
                $this->handleApplepayCartQuickcheckout( $applePayoptions );
            }
            
        }
        
        
        private function handleGooglepayCartQuickcheckout( array $options ): void {


            // Register cart button HTML here (same place cart GP scripts load).
            // Gateway constructor is_cart() is unreliable and can leave #divGpay missing.
            add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_cart_googlepay_button' ), 20 );

            wp_enqueue_style(
                'woocommerce_cardknox_gpay',
                plugins_url( '/assets/css/google-pay.css', WC_CARDKNOX_MAIN_FILE ),
                [],
                '1.0',
                'all'
            );
        
            
            // filemtime busts browser cache of .min.js (old cached file caused duplicate cart orders).
            wp_enqueue_script(
                'woocommerce_cardknox_google_pay',
                plugins_url( 'assets/js/cardknox-google-pay-cart.min.js', WC_CARDKNOX_MAIN_FILE ),
                [ 'jquery', 'cardknox' ],
                filemtime( WC_CARDKNOX_PLUGIN_PATH . '/assets/js/cardknox-google-pay-cart.min.js' ),
                true
            );
            
        
            // Rate ids (method_id:instance_id) + instance costs for the wallet sheet.
            $shipping_catalog = $this->get_wallet_shipping_method_catalog();
        
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
                'shippingMethods'       => $shipping_catalog['google_methods'],
                'shippingCosts'         => $shipping_catalog['google_costs'],
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

            
            // Register cart button HTML here (same place cart AP scripts load).
            // Gateway constructor/wp hooks are unreliable for is_cart(), which left #ap-container missing.
            add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_cart_applepay_button' ), 25 );
            

            wp_enqueue_style(
                'woocommerce_cardknox_applepay',
                plugins_url( '/assets/css/apple-pay.css', WC_CARDKNOX_MAIN_FILE ),
                [],
                '1.0',
                'all'
            );
        
            
            // filemtime busts browser cache of .min.js (old cached file caused duplicate cart orders).
            wp_enqueue_script(
                'woocommerce_cardknox_apple_pay',
                plugins_url( 'assets/js/cardknox-apple-pay-cart.min.js', WC_CARDKNOX_MAIN_FILE ),
                [ 'jquery', 'cardknox' ],
                filemtime( WC_CARDKNOX_PLUGIN_PATH . '/assets/js/cardknox-apple-pay-cart.min.js' ),
                true
            );
            
        
            // Rate ids (method_id:instance_id) + instance costs for the wallet sheet.
            $shipping_catalog = $this->get_wallet_shipping_method_catalog();
        
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
                'shippingMethods'       => $shipping_catalog['apple_methods'],
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
         * Output Google Pay button container on the cart page.
        */
        public function render_cart_googlepay_button()
        {
            echo '<div class="messages">';
            echo '<div class="message message-error error gpay-error" style="display: none;"></div>';
            echo '</div>';
            echo '<div id="divGpay" class="gp hidden">';
            echo '<iframe id="igp" class="gp" data-ifields-id="igp" data-ifields-oninit="gpRequest.initGP" src="https://cdn.cardknox.com/ifields/3.5.2607.1401/igp.htm" allowpaymentrequest sandbox="allow-popups allow-modals allow-scripts allow-same-origin allow-forms allow-popups-to-escape-sandbox allow-top-navigation" title="GPay checkout page"></iframe>';
            echo '</div>';
        }


        /**
         * Output Apple Pay button container on the cart page.
         */
        public function render_cart_applepay_button()
        {
            // Start hidden; apButtonLoaded reveals it only when Apple Pay actually loads.
            echo '<div id="ap-container" class="ap hidden" style="min-height:55px;margin:10px 0;"></div>';
            echo '<div class="messages">';
            echo '<div class="message message-error error applepay-error" style="display: none;"></div>';
            echo '</div>';
            
        }


        /**
         * Apply wallet shipping (and optional billing) to the WC customer session.
         *
         * Billing location is only updated when a real billing address is provided,
         * so tax-based-on-billing stores are not forced onto the shipping address.
         *
         * @param array      $shipping Normalized shipping address.
         * @param array|null $billing  Optional normalized billing address.
         * @return void
         */
        private function apply_wallet_customer_locations(array $shipping, $billing = null)
        {
            if (!WC()->customer) {
                return;
            }

            WC()->customer->set_shipping_location(
                $shipping['country'] ?? '',
                $shipping['state'] ?? '',
                $shipping['postcode'] ?? '',
                $shipping['city'] ?? ''
            );
            WC()->customer->set_shipping_address($shipping['address_1'] ?? '');
            WC()->customer->set_shipping_address_2($shipping['address_2'] ?? '');

            if (is_array($billing) && !empty($billing['country'])) {
                WC()->customer->set_billing_location(
                    $billing['country'],
                    $billing['state'] ?? '',
                    $billing['postcode'] ?? '',
                    $billing['city'] ?? ''
                );
                WC()->customer->set_billing_address($billing['address_1'] ?? '');
                WC()->customer->set_billing_address_2($billing['address_2'] ?? '');
            }
        }

        /**
         * Resolve a wallet-selected shipping method against live rates for an address.
         *
         * Matches only on full rate id (e.g. flat_rate:3). Method types like
         * "flat_rate" are ambiguous when a zone has multiple instances.
         *
         * @param string     $shipping_method_slug Rate id from the wallet UI.
         * @param array      $address              Normalized shipping address.
         * @param array|null $billing              Optional billing address for tax calculation.
         * @return array|null { method_id, method_title, method_cost }
         */
        private function resolve_shipping_method_for_address($shipping_method_slug, array $address, $billing = null)
        {
            if (empty($shipping_method_slug) || empty($address['country']) || !WC()->customer || !WC()->cart) {
                return null;
            }

            $this->apply_wallet_customer_locations($address, $billing);

            WC()->shipping()->reset_shipping();
            WC()->cart->calculate_shipping();

            $packages = WC()->shipping()->get_packages();
            foreach ($packages as $package) {
                if (empty($package['rates']) || !is_array($package['rates'])) {
                    continue;
                }

                foreach ($package['rates'] as $rate_id => $rate) {
                    if (!is_object($rate)) {
                        continue;
                    }

                    if ($rate_id === $shipping_method_slug) {
                        return array(
                            'method_id'    => $rate_id,
                            'method_title' => $rate->get_label(),
                            'method_cost'  => (float) $rate->get_cost(),
                        );
                    }
                }
            }

            return null;
        }

        /**
         * Apply the chosen shipping rate to the cart session and recalculate totals.
         *
         * @param string $rate_id Chosen shipping rate id (method_id:instance_id).
         * @return void
         */
        private function apply_chosen_shipping_method($rate_id)
        {
            WC()->session->set('chosen_shipping_methods', array($rate_id));
            WC()->shipping()->reset_shipping();
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
        }

        /**
         * Create an order from the current cart via WooCommerce checkout.
         * Copies variations, coupons, fees, taxes, and shipping from the cart.
         *
         * Runs the same cart-item checks as regular checkout (stock, coupons,
         * usage limits) before create_order(), since wallet quick-checkout
         * bypasses WC_Checkout::process_checkout().
         *
         * @param array $order_data Checkout field data including payment_method and addresses.
         * @return WC_Order|WP_Error
         */
        private function create_quickcheckout_order_from_cart(array $order_data)
        {
            $cart_check = $this->validate_quickcheckout_cart_items();
            if (is_wp_error($cart_check)) {
                return $cart_check;
            }

            $order_id = WC()->checkout()->create_order($order_data);

            if (is_wp_error($order_id)) {
                return $order_id;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error(
                    'order_create_failed',
                    __('Unable to create order.', 'woo-cardknox-gateway')
                );
            }

            return $order;
        }

        /**
         * Mirror WC_Checkout cart validation for wallet quick-checkout.
         *
         * @return true|WP_Error
         */
        private function validate_quickcheckout_cart_items()
        {
            if (!WC()->cart || WC()->cart->is_empty()) {
                return new WP_Error(
                    'cart_empty',
                    __('Your cart is empty.', 'woo-cardknox-gateway')
                );
            }

            // Clears prior notices so we only surface failures from this check.
            wc_clear_notices();

            // Fires woocommerce_check_cart_items — stock, coupons, limits, etc.
            WC()->cart->check_cart_items();

            if (wc_notice_count('error') > 0) {
                $errors = wc_get_notices('error');
                wc_clear_notices();
                $message = __('There was a problem with your cart. Please review it and try again.', 'woo-cardknox-gateway');
                if (!empty($errors[0]['notice'])) {
                    $message = wp_strip_all_tags($errors[0]['notice']);
                }
                return new WP_Error('cart_item_check_failed', $message);
            }

            wc_clear_notices();
            return true;
        }

        /**
         * Build checkout address field array for WC_Checkout::create_order().
         *
         * @param array  $address Address keys: first_name, last_name, address_1, address_2, city, state, postcode, country, email, phone.
         * @param string $prefix  billing|shipping
         * @return array
         */
        private function build_checkout_address_fields(array $address, $prefix)
        {
            return array(
                "{$prefix}_first_name" => $address['first_name'] ?? '',
                "{$prefix}_last_name"  => $address['last_name'] ?? '',
                "{$prefix}_address_1"  => $address['address_1'] ?? '',
                "{$prefix}_address_2"  => $address['address_2'] ?? '',
                "{$prefix}_city"       => $address['city'] ?? '',
                "{$prefix}_state"      => $address['state'] ?? '',
                "{$prefix}_postcode"   => $address['postcode'] ?? '',
                "{$prefix}_country"    => $address['country'] ?? '',
                "{$prefix}_email"      => $address['email'] ?? '',
                "{$prefix}_phone"      => $address['phone'] ?? '',
            );
        }

        /**
         * For Googlepay quick create order.
         */
        public function googlepayCardknoxCreateorder()
        {

            // Verify nonce
            check_ajax_referer('create_order_nonce', 'security');

            // Prevent duplicate cart quick-checkout orders from parallel requests.
            if (!$this->acquire_quickcheckout_lock('gpay')) {
                wp_send_json_error(__('Your order is already being processed. Please wait.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $google_pay_token = isset($_POST['google_pay_token'])
                ? wc_clean(wp_unslash($_POST['google_pay_token']))
                : '';
            if (empty($google_pay_token)) {
                $this->release_quickcheckout_lock('gpay');
                wp_send_json_error(__('Missing Google Pay token.', 'woo-cardknox-gateway'));
                wp_die();
            }

            // Client amount is used only to detect wallet-sheet / server mismatches.
            $client_amount = isset($_POST['amount']) ? wc_clean(wp_unslash($_POST['amount'])) : '';
            $google_email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
            $shippingOptionData = json_decode(stripslashes($_POST['shippingOptionData'] ?? ''), true);
            $shipping_json_ok = (json_last_error() === JSON_ERROR_NONE);
            $shippingAddress = json_decode(stripslashes($_POST['shippingAddress'] ?? ''), true);
            $shipping_json_ok = $shipping_json_ok && (json_last_error() === JSON_ERROR_NONE);

            $billing_raw = isset($_POST['billingAddress']) ? stripslashes($_POST['billingAddress']) : '';
            $billingAddress = ($billing_raw !== '')
                ? json_decode($billing_raw, true)
                : array();
            if (!is_array($billingAddress)) {
                $billingAddress = array();
            }

            if (!$shipping_json_ok || empty($shippingAddress) || empty($shippingOptionData)) {
                $this->release_quickcheckout_lock('gpay');
                wp_send_json_error(__('Invalid shipping address format', 'woocommerce'));
                wp_die();
            }

            if (WC()->cart->is_empty()) {
                $this->release_quickcheckout_lock('gpay');
                wp_send_json_error(__('Your cart is empty.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $shipping = $this->normalize_wallet_shipping_address($shippingAddress);
            $shipping['email'] = $google_email;
            if ($phone !== '') {
                $shipping['phone'] = $phone;
            }

            // Use Google Pay billing address for AVS / order billing when present
            // (gift ship-to-elsewhere must not overwrite cardholder billing).
            $billing = !empty($billingAddress)
                ? $this->normalize_wallet_shipping_address($billingAddress)
                : $shipping;
            $billing['email'] = $google_email;
            if ($phone !== '') {
                $billing['phone'] = $phone;
            } elseif (empty($billing['phone']) && !empty($shipping['phone'])) {
                $billing['phone'] = $shipping['phone'];
            }

            $shipping_method_slug = $shippingOptionData['id'] ?? '';
            // Pass real billing when present so tax-based-on-billing uses cardholder location.
            $billing_for_tax = (!empty($billingAddress) && !empty($billing['country'])) ? $billing : null;
            $shipping_method = $this->resolve_shipping_method_for_address(
                $shipping_method_slug,
                $shipping,
                $billing_for_tax
            );
            if (empty($shipping_method)) {
                $this->release_quickcheckout_lock('gpay');
                wp_send_json_error(__('Invalid shipping method. Please select shipping again.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $this->apply_chosen_shipping_method($shipping_method['method_id']);

            $order_data = array_merge(
                array('payment_method' => 'cardknox-googlepay'),
                $this->build_checkout_address_fields($billing, 'billing'),
                $this->build_checkout_address_fields($shipping, 'shipping')
            );

            $order = $this->create_quickcheckout_order_from_cart($order_data);
            if (is_wp_error($order)) {
                $this->release_quickcheckout_lock('gpay');
                wp_send_json_error($order->get_error_message());
                wp_die();
            }

            $amount_check = $this->validate_wallet_client_amount($order, $client_amount);
            if (is_wp_error($amount_check)) {
                $order->update_status('cancelled', $amount_check->get_error_message());
                $this->release_quickcheckout_lock('gpay');
                wp_send_json_error($amount_check->get_error_message());
                wp_die();
            }

            $order->set_payment_method_title('Sola Google Pay');
            $order->update_status('pending', __('Order pending payment', 'woocommerce'));

            // Map cart wallet token into the field expected by WCCardknoxGooglepay::process_payment()
            $_POST['xCardNumToken'] = $google_pay_token;

            $payment_result = $this->process_quickcheckout_payment($order, 'cardknox-googlepay');
            if (is_wp_error($payment_result)) {
                $this->release_quickcheckout_lock('gpay');
                wp_send_json_error($payment_result->get_error_message());
                wp_die();
            }

            $this->release_quickcheckout_lock('gpay');
            wp_send_json_success(array('redirect_url' => $payment_result));
            wp_die();
        }

        /**
         * For Applepay quick create order.
         */
        public function applepayCardknoxCreateorder()
        {

            // Verify nonce
            check_ajax_referer('create_order_nonce', 'security');

            // Prevent duplicate cart quick-checkout orders from parallel requests.
            if (!$this->acquire_quickcheckout_lock('apay')) {
                wp_send_json_error(__('Your order is already being processed. Please wait.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $apple_pay_token = isset($_POST['apple_pay_token'])
                ? wc_clean(wp_unslash($_POST['apple_pay_token']))
                : '';
            if (empty($apple_pay_token)) {
                $this->release_quickcheckout_lock('apay');
                wp_send_json_error(__('Missing Apple Pay token.', 'woo-cardknox-gateway'));
                wp_die();
            }

            // Client amount is used only to detect wallet-sheet / server mismatches.
            $client_amount = isset($_POST['amount']) ? wc_clean(wp_unslash($_POST['amount'])) : '';
            $billingContact = json_decode(stripslashes($_POST['billingContact'] ?? ''), true);
            $shippingContact = json_decode(stripslashes($_POST['shippingContact'] ?? ''), true);
            $selectedShipping = json_decode(stripslashes($_POST['selectedShipping'] ?? ''), true);

            if (json_last_error() !== JSON_ERROR_NONE || empty($billingContact) || empty($shippingContact) || empty($selectedShipping)) {
                $this->release_quickcheckout_lock('apay');
                wp_send_json_error(__('Invalid shipping address format', 'woocommerce'));
                wp_die();
            }

            if (WC()->cart->is_empty()) {
                $this->release_quickcheckout_lock('apay');
                wp_send_json_error(__('Your cart is empty.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $phone = sanitize_text_field($shippingContact['phoneNumber'] ?? '');

            $billing = array(
                'first_name' => sanitize_text_field($billingContact['firstName'] ?? ''),
                'last_name'  => sanitize_text_field($billingContact['lastName'] ?? ''),
                'address_1'  => sanitize_text_field($billingContact['address'] ?? ''),
                'address_2'  => '',
                'city'       => sanitize_text_field($billingContact['city'] ?? ''),
                'state'      => sanitize_text_field($billingContact['administrativeArea'] ?? ''),
                'postcode'   => sanitize_text_field($billingContact['postcode'] ?? ''),
                'country'    => sanitize_text_field($billingContact['country'] ?? ''),
                'email'      => sanitize_email($billingContact['emailAddress'] ?? ''),
                'phone'      => $phone,
            );

            $shipping_address = array(
                'first_name' => sanitize_text_field($shippingContact['firstName'] ?? ''),
                'last_name'  => sanitize_text_field($shippingContact['lastName'] ?? ''),
                'address_1'  => sanitize_text_field($shippingContact['address'] ?? ''),
                'address_2'  => '',
                'city'       => sanitize_text_field($shippingContact['city'] ?? ''),
                'state'      => sanitize_text_field($shippingContact['administrativeArea'] ?? ''),
                'postcode'   => sanitize_text_field($shippingContact['postcode'] ?? ''),
                'country'    => sanitize_text_field($shippingContact['country'] ?? ''),
                'email'      => sanitize_email($shippingContact['emailAddress'] ?? ''),
                'phone'      => $phone,
            );

            // Apple Pay always has a billing contact here — use it for tax location.
            $shipping_method = $this->resolve_shipping_method_for_address(
                $selectedShipping['identifier'] ?? '',
                $shipping_address,
                $billing
            );
            if (empty($shipping_method)) {
                $this->release_quickcheckout_lock('apay');
                wp_send_json_error(__('Invalid shipping method. Please select shipping again.', 'woo-cardknox-gateway'));
                wp_die();
            }

            $this->apply_chosen_shipping_method($shipping_method['method_id']);

            $order_data = array_merge(
                array('payment_method' => 'cardknox-applepay'),
                $this->build_checkout_address_fields($billing, 'billing'),
                $this->build_checkout_address_fields($shipping_address, 'shipping')
            );

            $order = $this->create_quickcheckout_order_from_cart($order_data);
            if (is_wp_error($order)) {
                $this->release_quickcheckout_lock('apay');
                wp_send_json_error($order->get_error_message());
                wp_die();
            }

            $amount_check = $this->validate_wallet_client_amount($order, $client_amount);
            if (is_wp_error($amount_check)) {
                $order->update_status('cancelled', $amount_check->get_error_message());
                $this->release_quickcheckout_lock('apay');
                wp_send_json_error($amount_check->get_error_message());
                wp_die();
            }

            $order->set_payment_method_title('Sola Apple Pay');
            $order->update_status('pending', __('Order pending payment', 'woocommerce'));

            // Map cart wallet token into the field expected by WCCardknoxApplepay::process_payment()
            $_POST['xCardNumToken'] = $apple_pay_token;

            $payment_result = $this->process_quickcheckout_payment($order, 'cardknox-applepay');
            if (is_wp_error($payment_result)) {
                $this->release_quickcheckout_lock('apay');
                wp_send_json_error($payment_result->get_error_message());
                wp_die();
            }

            $this->release_quickcheckout_lock('apay');
            wp_send_json_success(array('redirect_url' => $payment_result));
            wp_die();
        }

        /**
         * Acquire a short-lived session lock to prevent duplicate cart quick-checkout orders.
         *
         * @param string $wallet gpay|apay
         * @return bool
         */
        private function acquire_quickcheckout_lock($wallet)
        {
            if (!function_exists('WC') || !WC()->session) {
                return true;
            }

            $key  = 'cardknox_qc_lock_' . $wallet;
            $lock = WC()->session->get($key);
            if ($lock && (time() - (int) $lock) < 60) {
                return false;
            }

            WC()->session->set($key, time());
            return true;
        }

        /**
         * Release the cart quick-checkout session lock.
         *
         * @param string $wallet gpay|apay
         * @return void
         */
        private function release_quickcheckout_lock($wallet)
        {
            if (!function_exists('WC') || !WC()->session) {
                return;
            }
            WC()->session->set('cardknox_qc_lock_' . $wallet, null);
        }

        /**
         * Charge a cart quick-checkout order via the existing wallet gateway.
         * Only returns the thank-you URL after a successful Cardknox approval.
         *
         * @param WC_Order $order
         * @param string   $gateway_id
         * @return string|WP_Error
         */
        private function process_quickcheckout_payment($order, $gateway_id)
        {
            $gateways = WC()->payment_gateways()->payment_gateways();
            if (empty($gateways[$gateway_id]) || !is_object($gateways[$gateway_id])) {
                $order->update_status('failed', __('Payment gateway unavailable.', 'woo-cardknox-gateway'));
                return new WP_Error('gateway_unavailable', __('Payment gateway unavailable.', 'woo-cardknox-gateway'));
            }

            $gateway = $gateways[$gateway_id];
            $result  = $gateway->process_payment($order->get_id());

            if (!empty($result['result']) && 'success' === $result['result'] && !empty($result['redirect'])) {
                return $result['redirect'];
            }

            // process_payment() stores the real decline/error via wc_add_notice().
            // Surface that message to the wallet AJAX response and clear session notices
            // so they do not reappear later on the cart/checkout page.
            $errors  = wc_get_notices('error');
            wc_clear_notices();
            $message = !empty($errors[0]['notice'])
                ? wp_strip_all_tags($errors[0]['notice'])
                : __('Payment failed', 'woocommerce');

            if ($order->has_status(array('pending'))) {
                $order->update_status('failed', $message);
            }

            return new WP_Error('payment_failed', $message);
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
                    $this->log(sprintf(__('Error: %s', 'woo-cardknox-gateway'), $parsedResponse['xError']));

                    return wp_send_json($parsedResponse);
                } else {

                    $paymentInfo = new WC_Gateway_Cardknox();

                    $order  = wc_get_order($parsedResponse['xInvoice']);
                    $redirect = $order->get_checkout_order_received_url();

                    if (is_wp_error($parsedResponse)) {
                        $order->add_order_note($parsedResponse->get_error_message());
                        throw new WC_Data_Exception( 'cardknox_declined', __( 'The transaction was declined, please try again.', 'woo-cardknox-gateway' ) );
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
                $this->log(sprintf(__('Error: %s', 'woo-cardknox-gateway'), $e->getMessage()));

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
