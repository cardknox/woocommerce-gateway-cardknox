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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Cardknox class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Cardknox extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'cardknox';
		$this->method_title         = __( 'Cardknox', 'woocommerce-gateway-cardknox' );
		$this->method_description   = __( 'Cardknox works by adding credit card fields on the checkout and then sending the details to Cardknox for verification.', 'woocommerce-gateway-cardknox' );
		$this->has_fields           = true;
		$this->view_transaction_url = 'https://dashboard.cardknox.com/payments/%s';
		$this->supports             = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change', // Subs 1.n compatibility
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
			'pre-orders',
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description' );
		$this->enabled                = $this->get_option( 'enabled' );
		$this->capture                = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->saved_cards            = 'yes' === $this->get_option( 'saved_cards' );
		$this->transaction_key      =        $this->get_option( 'transaction_key' );
		$this->token_key        =  $this->get_option( 'token_key' );

		$this->logging                = 'yes' === $this->get_option( 'logging' );

		WC_Cardknox_API::set_transaction_key( $this->transaction_key );

		// Hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$ext   = version_compare( WC()->version, '2.6', '>=' ) ? '.svg' : '.png';
		$style = version_compare( WC()->version, '2.6', '>=' ) ? 'style="margin-left: 0.3em"' : '';

		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa' . $ext ) . '" alt="Visa" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard' . $ext ) . '" alt="Mastercard" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex' . $ext ) . '" alt="Amex" width="32" ' . $style . ' />';

		if ( 'USD' === get_woocommerce_currency() ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover' . $ext ) . '" alt="Discover" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb' . $ext ) . '" alt="JCB" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners' . $ext ) . '" alt="Diners" width="32" ' . $style . ' />';
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Get Cardknox amount to pay
	 * @return float
	 */
	public function get_cardknox_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}
		switch ( strtoupper( $currency ) ) {
			// Zero decimal currencies
			case 'BIF' :
			case 'CLP' :
			case 'DJF' :
			case 'GNF' :
			case 'JPY' :
			case 'KMF' :
			case 'KRW' :
			case 'MGA' :
			case 'PYG' :
			case 'RWF' :
			case 'VND' :
			case 'VUV' :
			case 'XAF' :
			case 'XOF' :
			case 'XPF' :
				$total = absint( $total );
				break;
			default :
				$total = absint( $total );
				break;
		}
		return $total;
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( $this->enabled == 'no' ) {
			return;
		}

		$addons = ( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) ? '_addons' : '';

		// Check required fields
		if ( ! $this->transaction_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Cardknox error: Please enter your secret key <a href="%s">here</a>', 'woocommerce-gateway-cardknox' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_cardknox' . $addons ) ) . '</p></div>';
			return;

		} elseif ( ! $this->token_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Cardknox error: Please enter your publishable key <a href="%s">here</a>', 'woocommerce-gateway-cardknox' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_cardknox' . $addons ) ) . '</p></div>';
			return;
		}

		// Simple check for duplicate keys
		if ( $this->transaction_key == $this->token_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Cardknox error: Your secret and publishable keys match. Please check and re-enter.', 'woocommerce-gateway-cardknox' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_cardknox' . $addons ) ) . '</p></div>';
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
		if ( ( function_exists( 'wc_site_is_https' ) && ! wc_site_is_https() ) && ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) && ! class_exists( 'WordPressHTTPS' ) ) ) {
			echo '<div class="error"><p>' . sprintf( __( 'Cardknox is enabled, but the <a href="%1$s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid <a href="%2$s" target="_blank">SSL certificate</a> - Cardknox will only work in test mode.', 'woocommerce-gateway-cardknox' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->transaction_key || ! $this->token_key ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( untrailingslashit( plugin_dir_path( WC_CARDKNOX_MAIN_FILE ) ) . '/includes/settings-cardknox.php' );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		?>
		<fieldset class="cardknox-legacy-payment-fields">
			<?php
				if ( $this->description ) {
					echo apply_filters( 'wc_cardknox_description', wpautop( wp_kses_post( $this->description ) ) );
				}
				if ( $this->saved_cards && is_user_logged_in() ) {
					$cardknox_customer = new WC_Cardknox_Customer( get_current_user_id() );
					?>
					<p class="form-row form-row-wide">
						<a class="<?php echo apply_filters( 'wc_cardknox_manage_saved_cards_class', 'button' ); ?>" style="float:right;" href="<?php echo apply_filters( 'wc_cardknox_manage_saved_cards_url', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>#saved-cards"><?php esc_html_e( 'Manage cards', 'woocommerce-gateway-cardknox' ); ?></a>
						<?php
						if ( $cards = $cardknox_customer->get_cards() ) {
							$default_card = $cards[0]->id;
							foreach ( (array) $cards as $card ) {
								if ( 'card' !== $card->object ) {
									continue;
								}
								?>
								<label for="cardknox_card_<?php echo $card->id; ?>" class="brand-<?php echo esc_attr( strtolower( $card->brand ) ); ?>">
									<input type="radio" id="cardknox_card_<?php echo $card->id; ?>" name="wc-cardknox-payment-token" value="<?php echo $card->id; ?>" <?php checked( $default_card, $card->id ) ?> />
									<?php printf( __( '%s card ending in %s (Expires %s/%s)', 'woocommerce-gateway-cardknox' ), $card->brand, $card->last4, $card->exp_month, $card->exp_year ); ?>
								</label>
								<?php
							}
						}
						?>
						<label for="new">
							<input type="radio" id="new" name="wc-cardknox-payment-token" value="new" />
							<?php _e( 'Use a new credit card', 'woocommerce-gateway-cardknox' ); ?>
						</label>
					</p>
					<?php
				}

				$user = wp_get_current_user();

				if ( $user ) {
					$user_email = get_user_meta( $user->ID, 'billing_email', true );
					$user_email = $user_email ? $user_email : $user->user_email;
				} else {
					$user_email = '';
				}

				$display = '';

				if ( $this->cardknox_checkout || $this->saved_cards && ! empty( $cards ) ) {
					$display = 'style="display:none;"';
				}

				echo '<div ' . $display . ' id="cardknox-payment-data"
					data-description=""
					data-email="' . esc_attr( $user_email ) . '"
					data-amount="' . esc_attr( $this->get_cardknox_amount( WC()->cart->total ) ) . '"
					data-name="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '"
					data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';
				echo '</div>';
			?>
		</fieldset>
		<?php
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for cardknox payment
	 *
	 * @access public
	 */
	public function payment_scripts() {

			wp_enqueue_script( 'cardknox', 'https://js.cardknox.com/v2/', '', '1.0', true );
			wp_enqueue_script( 'woocommerce_cardknox', plugins_url( 'assets/js/cardknox.js', WC_CARDKNOX_MAIN_FILE ), array( 'jquery-payment', 'cardknox' ), WC_CARDKNOX_VERSION, true );
		$cardknox_params = array(
			'key'                  => $this->token_key,
			'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-cardknox' ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-cardknox' ),
		);
		wp_localize_script( 'woocommerce_cardknox', 'wc_cardknox_params', apply_filters( 'wc_cardknox_params', $cardknox_params ) );
	}

	/**
	 * Generate the request for the payment.
	 * @param  WC_Order $order
	 * @param  object $source
	 * @return array()
	 */
	protected function generate_payment_request( $order, $source ) {
		$post_data                = array();
		$post_data['xCurrency']    = strtolower( $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() );
		$post_data['xAmount']      = $this->get_cardknox_amount( $order->get_total(), $post_data['currency'] );
		$post_data['xDescription'] = sprintf( __( '%s - Order %s', 'woocommerce-gateway-cardknox' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$post_data['xCommand']     = $this->capture ? 'cc:sale' : 'cc:authonly';
//TODO need to add lagacy support for billing data
//		$post_data['xBillFirstName'] = $order->billing_first_name : $order->get_billing_first_name();
//		$post_data['xBillLastName']  = $order->billing_last_name : $order->get_billing_last_name();
//		$post_data['xInvoice'] =  $order->id : $order->get_id();
//
//		$post_data['xBillCompany'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_company() : $order->get_billing_company();
//		$post_data['xBillStreet'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_address_1() : $order->get_billing_address_1();
//		$post_data['xBillStreet2'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_address_2() : $order->get_billing_address_2();
//		$post_data['xBillCity'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_city() : $order->get_billing_city();
//		$post_data['xBillState'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_state() : $order->get_billing_state();
//		$post_data['xBillZip'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_postcode() : $order->get_billing_postcode();
//		$post_data['xBillCountry'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_country() : $order->get_billing_country();
//		$post_data['xBillPhone'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_billing_phone() : $order->get_billing_phone();
//		$post_data['xIP'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_customer_ip_address() : $order->get_customer_ip_address();

		if ( ! empty( $order->billing_email ) && apply_filters( 'wc_cardknox_send_cardknox_receipt', false ) ) {
			$post_data['xEmail'] = $order->billing_email;
		}

//		if ( $source->customer ) {
//			$post_data['customer'] = $source->customer;
//		}
//
//		if ( $source->source ) {
//			$post_data['source'] = $source->source;
//		}

		return apply_filters( 'wc_cardknox_generate_payment_request', $post_data, $order, $source );
	}

	/**
	 * Get payment source. This can be a new token or existing card.
	 * @param  bool $force_customer Should we force customer creation?
	 * @return object
	 */
	protected function get_source( $user_id, $force_customer = false ) {
		$cardknox_customer = new WC_Cardknox_Customer( $user_id );
		$cardknox_source   = false;
		$token_id        = false;

		// New CC info was entered and we have a new token to process
		if ( isset( $_POST['cardknox_token'] ) ) {
			$cardknox_token     = wc_clean( $_POST['cardknox_token'] );
			$maybe_saved_card = isset( $_POST['wc-cardknox-new-payment-method'] ) && ! empty( $_POST['wc-cardknox-new-payment-method'] );

			// This is true if the user wants to store the card to their account.
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card ) || $force_customer ) {
				$cardknox_source = $cardknox_customer->add_card( $cardknox_token );

				if ( is_wp_error( $cardknox_source ) ) {
					throw new Exception( $cardknox_source->get_error_message() );
				}

			} else {
				// Not saving token, so don't define customer either.
				$cardknox_source   = $cardknox_token;
				$cardknox_customer = false;
			}
		}

		// Use an existing token, and then process the payment
		elseif ( isset( $_POST['wc-cardknox-payment-token'] ) && 'new' !== $_POST['wc-cardknox-payment-token'] ) {
			$cardknox_source = wc_clean( $_POST['wc-cardknox-payment-token'] );
		}

		return (object) array(
			'token_id' => $token_id,
			'customer' => $cardknox_customer ? $cardknox_customer->get_id() : false,
			'source'   => $cardknox_source,
		);
	}

	/**
	 * Get payment source from an order. This could be used in the future for
	 * a subscription as an example, therefore using the current user ID would
	 * not work - the customer won't be logged in :)
	 *
	 * Not using 2.6 tokens for this part since we need a customer AND a card
	 * token, and not just one.
	 *
	 * @param object $order
	 * @return object
	 */
	protected function get_order_source( $order = null ) {
		$cardknox_customer = new WC_Cardknox_Customer();
		$cardknox_source   = false;
		$token_id        = false;

		if ( $order ) {
			if ( $meta_value = get_post_meta( $order->id, '_cardknox_token', true ) ) {
				$cardknox_source = $meta_value;
			}
		}

		return (object) array(
			'token_id' => $token_id,
			'customer' => $cardknox_customer ? $cardknox_customer->get_id() : false,
			'source'   => $cardknox_source,
		);
	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			$order  = wc_get_order( $order_id );

			// Handle payment
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Cardknox::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-cardknox' ), wc_price( WC_Cardknox::get_minimum_amount() / 100 ) ) );
				}

				WC_Cardknox::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Make the request
				$response = WC_Cardknox_API::request( $this->generate_payment_request( $order) );

				if ( is_wp_error( $response ) ) {

					throw new Exception( $response['xErrorCode']. ': ' . $response['xError'] );
				}
				$cardknox_customer = new WC_Cardknox_Customer( get_current_user_id() );
				$my_force_customer  = apply_filters( 'wc_cardknox_force_customer_creation', $force_customer, $cardknox_customer );
				$maybe_saved_card = isset( $_POST['wc-cardknox-new-payment-method'] ) && ! empty( $_POST['wc-cardknox-new-payment-method'] );

				if ( ( get_current_user_id() && $this->saved_cards && $maybe_saved_card ) || $force_customer ) {
					$cardknox_source = $cardknox_customer->add_card( $response );

					if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
						$subscriptions = wcs_get_subscriptions_for_order( $order_id );
					} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
						$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
					} else {
						$subscriptions = array();
					}

					foreach ( $subscriptions as $subscription ) {
						$subscription_id = $this->wc_pre_30 ? $subscription->id : $subscription->get_id();
						update_post_meta( $subscription_id, '_cardknox_token', $response['xToken'] );
					}


					if ( is_wp_error( $cardknox_source ) ) {
						throw new Exception( $cardknox_source->get_error_message() );
					}

				} else {
					// Not saving token, so don't define customer either.
					$cardknox_source = false;

				}
					// Process valid response
				$this->process_response( $response, $order );
			} else {

				$order->payment_complete();
			}

			// Remove cart
			WC()->cart->empty_cart();

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			WC()->session->set( 'refresh_totals', true );
			WC_Cardknox::log( sprintf( __( 'Error: %s', 'woocommerce-gateway-cardknox' ), $e->getMessage() ) );
			return;
		}
	}

	/**
	 * Save source to order.
	 */
	protected function save_source( $order, $source ) {
		// Store source in the order
		if ( $source->source ) {
			update_post_meta( $order->id, '_cardknox_token', $source->source->id );
		}
	}

	/**
	 * Store extra meta data for an order from a Cardknox Response.
	 */
	public function process_response( $response, $order ) {
		WC_Cardknox::log( "Processing response: " . print_r( $response, true ) );

		// Store charge data
		update_post_meta( $order->id, '_cardknox_xrefnum', $response->id );
		update_post_meta( $order->id, '_cardknox_transaction_captured', $response->captured ? 'yes' : 'no' );

		if ( $response->captured ) {
			$order->payment_complete($response['xRefNum']);

			WC_Cardknox::log( "Successful charge: " . $response['xRefNum'] );
		} else {
			update_post_meta( $order->id, '_transaction_id', $response['xRefNum'], true );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$order->reduce_order_stock();
			}

			$order->update_status( 'on-hold', sprintf( __( 'Cardknox charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-cardknox' ), $response['xRefNum'] ) );
			WC_Cardknox::log( "Successful auth: " . $response['xRefNum'] );
		}

		return $response;
	}

	/**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the Cardknox API.
	 * @since 3.0.0
	 */
	public function add_payment_method() {
		if ( empty( $_POST['xCardNum']  ) || ! is_user_logged_in() ) {
			wc_add_notice( __( 'There was a problem adding the card.', 'woocommerce-gateway-cardknox' ), 'error' );
			return;
		}

		$cardknox_customer = new WC_Cardknox_Customer( get_current_user_id() );


		$response = WC_Cardknox_API::request( array(
				'xCommand' => 'cc:save',
				'xCardNum' => wc_clean( $_POST['xCardNum']),
				'xCVV' => wc_clean( $_POST['xCVV']),
				'xExp' => wc_clean( $_POST['xExp'])
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response['xError'] );
		}

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}

	/**
	 * Refund a charge
	 * @param  int $order_id
	 * @param  float $amount
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		$command = 'cc:voidrefund';
		$total = $order->get_total();

		if ( $total !=  $amount) {
			$command = 'cc:refund';
		}

		$body = array();

		if ( ! is_null( $amount ) ) {
			$body['xAmount']	= $this->get_cardknox_amount( $amount );

		}
		$body['xCommand'] = $command;
		$body['xRefNum'] =  $order->get_transaction_id();

		WC_Cardknox::log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );

		$response = WC_Cardknox_API::request( $body );

		if ( is_wp_error( $response ) ) {
			WC_Cardknox::log( "Error: " . $response['xError'] );
			return $response;
		} elseif ( ! empty( $response['xRefNum'] ) ) {
			$refund_message = sprintf( __( 'Refunded %s - Refund ID: %s - Reason: %s', 'woocommerce-gateway-cardknox' ), wc_price( $response['xAuthAmount'] ), $response['xRefNum'], $reason );
			$order->add_order_note( $refund_message );
			WC_Cardknox::log( "Success: " . html_entity_decode( strip_tags( $refund_message ) ) );
			return true;
		}
	}
}
