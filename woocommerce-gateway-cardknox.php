<?php
/*
Plugin Name: WooCommerce Cardknox Gateway
Description: Accept credit card payments on your store using the Cardknox gateway.
Author: Cardknox Development Inc.
Author URI: https://www.cardknox.com/
Version: 1.0.1
Requires at least: 4.4
Tested up to: 4.8
WC requires at least: 2.5
WC tested up to: 3.2
Text Domain: woocommerce-gateway-cardknox
Domain Path: /languages

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


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_CARDKNOX_VERSION', '1.0.0' );
define( 'WC_CARDKNOX_MIN_PHP_VER', '5.6.0' );
define( 'WC_CARDKNOX_MIN_WC_VER', '2.5.0' );
define( 'WC_CARDKNOX_MAIN_FILE', __FILE__ );
define( 'WC_CARDKNOX_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_CARDKNOX_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_Cardknox' ) ) :

	class WC_Cardknox {

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
		public static function get_instance() {
			if ( null === self::$instance ) {
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
		private function __wakeup() {}

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
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}

			include_once( dirname( __FILE__ ) . '/includes/class-wc-cardknox-api.php' );
//			include_once( dirname( __FILE__ ) . '/includes/class-wc-cardknox-customer.php' );

			// Init the gateway itself
			$this->init_gateways();
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'refund_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'refund_payment' ) );
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation. Also handles upgrade routines.
		 */
		public function check_environment() {
			if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_CARDKNOX_VERSION !== get_option( 'wc_cardknox_version' ) ) ) {
				$this->install();

				do_action( 'woocommerce_cardknox_updated' );
			}

			$environment_warning = self::get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}

			// Check if secret key present. Otherwise prompt, via notice, to go to
			// setting.
			if ( ! class_exists( 'WC_Cardknox_API' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-cardknox-api.php' );
			}

			$secret = WC_Cardknox_API::get_transaction_key();

			if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'cardknox' === $_GET['section'] ) ) {
				$setting_link = $this->get_setting_link();
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'Cardknox is almost ready. To get started, <a href="%s">set your Cardknox account keys</a>.', 'woocommerce-gateway-cardknox' ), $setting_link ) );
			}
		}

		/**
		 * Updates the plugin version in db
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 * @return bool
		 */
		private static function _update_plugin_version() {
			delete_option( 'wc_cardknox_version' );
			update_option( 'wc_cardknox_version', WC_CARDKNOX_VERSION );

			return true;
		}

		/**
		 * Handles upgrade routines.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function install() {
			if ( ! defined( 'WC_CARDKNOX_INSTALLING' ) ) {
				define( 'WC_CARDKNOX_INSTALLING', true );
			}

			$this->_update_plugin_version();
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning() {
			if ( version_compare( phpversion(), WC_CARDKNOX_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce Cardknox - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-cardknox' );

				return sprintf( $message, WC_CARDKNOX_MIN_PHP_VER, phpversion() );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce Cardknox requires WooCommerce to be activated to work.', 'woocommerce-gateway-cardknox' );
			}

			if ( version_compare( WC_VERSION, WC_CARDKNOX_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce Cardknox - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-cardknox' );

				return sprintf( $message, WC_CARDKNOX_MIN_WC_VER, WC_VERSION );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce Cardknox - cURL is not installed.', 'woocommerce-gateway-cardknox' );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @since 1.0.0
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-cardknox' ) . '</a>',
				'<a href="https://docs.woocommerce.com/document/cardknox/">' . __( 'Docs', 'woocommerce-gateway-cardknox' ) . '</a>',
				'<a href="https://woocommerce.com/contact-us/">' . __( 'Support', 'woocommerce-gateway-cardknox' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'cardknox' : strtolower( 'WC_Gateway_Cardknox' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {

			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$this->subscription_support_enabled = true;
			}

			if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
				$this->pre_order_enabled = true;
			}

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-cardknox.php' );
			} else {
				include_once( dirname( __FILE__ ) . '/includes/legacy/class-wc-gateway-cardknox.php' );
			}

			load_plugin_textdomain( 'woocommerce-gateway-cardknox', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

			$load_addons = (
				$this->subscription_support_enabled
				||
				$this->pre_order_enabled
			);

			if ( $load_addons ) {
				require_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-cardknox-addons.php' );
			}
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.0
		 */
		public function add_gateways( $methods ) {
			if ( $this->subscription_support_enabled || $this->pre_order_enabled ) {
				$methods[] = 'WC_Gateway_Cardknox_Addons';
			} else {
				$methods[] = 'WC_Gateway_Cardknox';
			}
			return $methods;
		}

		/**
		 * List of currencies supported by Cardknox that has no decimals.
		 *
		 * @return array $currencies
		 */
		public static function no_decimal_currencies() {
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
		public function capture_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( 'cardknox' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
				$my_xrefnum   = get_post_meta( $order_id, '_cardknox_xrefnum', true );
				$captured = get_post_meta( $order_id, '_cardknox_charge_captured', true );

				if ( $my_xrefnum && 'no' === $captured ) {
					$result = WC_Cardknox_API::request( array(
						'xAmount'   => $order->get_total(),
						'xCommand' => 'cc:capture',
						'xRefnum' => $my_xrefnum
					) );

					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to capture transaction!', 'woocommerce-gateway-cardknox' ) . ' ' . $result->get_error_message() );
					} else {
						$order->add_order_note( sprintf( __( 'Cardknox transaction captured (Charge ID: %s)', 'woocommerce-gateway-cardknox' ), $result['xRefNum'] ) );
						update_post_meta( $order_id, '_cardknox_transaction_captured', 'yes' );

						// Store other data such as fees
						update_post_meta( $order_id, 'Cardknox Payment ID', $result['xRefNum'] );
						update_post_meta( $order_id, '_transaction_id', $result['xRefNum'] );
						$order->set_transaction_id($result['xRefNum']);
					}
				}
			}
		}

		/**
		 * Cancel pre-auth on refund/cancellation by changing the status in the admin panel
		 *
		 * @param  int $order_id
		 */
		public function refund_payment( $order_id) {
			$order = wc_get_order( $order_id );

			if ( 'cardknox' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
				$my_xrefnum   = get_post_meta( $order_id, '_cardknox_xrefnum', true );

				if ( $my_xrefnum ) {
					$result = WC_Cardknox_API::request( array(
//						'xAmount' => $order->get_total(),
						'xCommand' => 'cc:voidrefund',
						'xRefNum' => $my_xrefnum
					) );

					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to refund transaction!', 'woocommerce-gateway-cardknox' ) . ' ' . $result['xError'] );
					} else {
						$order->add_order_note( sprintf( __( 'Cardknox transaction refunded (RefNum: %s)', 'woocommerce-gateway-cardknox' ), $result['xRefNum'] ) );
						delete_post_meta( $order_id, '_cardknox_transaction_captured' );
						delete_post_meta( $order_id, '_cardknox_xrefnum' );
					}
				}
			}
		}

		/**
		 * Checks Cardknox minimum order value authorized per currency
		 */
		public static function get_minimum_amount() {
			// Check order amount
			switch ( get_woocommerce_currency() ) {
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
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'woocommerce-gateway-cardknox', $message );
		}
	}

	$GLOBALS['wc_cardknox'] = WC_Cardknox::get_instance();

endif;
