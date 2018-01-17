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
 * WC_Gateway_Cardknox_Addons class.
 *
 * @extends WC_Gateway_Cardknox
 */
class WC_Gateway_Cardknox_Addons extends WC_Gateway_Cardknox {

	public $wc_pre_30;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_cardknox', array( $this, 'update_failing_payment_method' ), 10, 2 );

			// display the credit card used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

			// allow store managers to manually set Cardknox as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		}

		$this->wc_pre_30 = version_compare( WC_VERSION, '3.0.0', '<' );
	}

	/**
	 * Is $order_id a subscription?
	 * @param  int  $order_id
	 * @return boolean
	 */
	protected function is_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Process the payment based on type.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		if ( $this->is_subscription( $order_id ) ) {
			// Regular payment with force customer enabled
			return parent::process_payment( $order_id, true, true );
		} else {
			return parent::process_payment( $order_id, $retry, $force_customer );
		}
	}

	/**
	 * process_subscription_payment function.
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param string $cardknox_token (default: '')
	 * @param  bool initial_payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {
		if ( $amount  < WC_Cardknox::get_minimum_amount() ) {
			return new WP_Error( 'cardknox_error', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-cardknox' ), wc_price( WC_Cardknox::get_minimum_amount() / 100 ) ) );
		}

		// Get source from order
 		$my_token = $this->get_order_token( $order );

		$order_id = $this->wc_pre_30 ? $order->id : $order->get_id();
		$this->log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

		// Make the request
		$request             = $this->generate_payment_request( $order );
		$request['xAmount']   = $this->get_cardknox_amount( $amount, $request['currency'] );
		$request['xInvoice'] = $order_id;
		$request['xCustom02'] = 'recurring';
		$request['xToken'] = $my_token;
		$response            = WC_Cardknox_API::request( $request );

		// Process valid response
		if ( is_wp_error( $response ) ) {
			return $response; // Default catch all errors.
		}

		$this->process_response( $response, $order );

		return $response;
	}

	/**
	 */
	protected function get_order_token( $order = null ) {
		$token   = false;

		if ( $order ) {
			$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();
			if ( $meta_value = get_post_meta( $order_id, '_cardknox_token', true ) ) {
				$token = $meta_value;
			}
		}
		return $token;
	}

	/**
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( ( $this->wc_pre_30 ? $resubscribe_order->id : $resubscribe_order->get_id() ), '_cardknox_token' );
		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	*/
	public function delete_renewal_meta( $renewal_order ) {
		delete_post_meta( ( $this->wc_pre_30 ? $renewal_order->id : $renewal_order->get_id() ), 'Cardknox Payment ID' );
		return $renewal_order;
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( __( 'Cardknox Transaction Failed (%s)', 'woocommerce-gateway-cardknox' ), $response->get_error_message() ) );
		}
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_source_before_retry( $order ) {
		$order_id = $this->wc_pre_30 ? $order->id : $order->get_id();
		delete_post_meta( $order_id, '_cardknox_token' );
	}


	/**
	 * Update the customer_id for a subscription after using Cardknox to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		if ( $this->wc_pre_30 ) {
			update_post_meta( $subscription->id, '_cardknox_token', $renewal_order->cardknox_card_id );
		} else {
			$subscription->update_meta_data( '_cardknox_token', $renewal_order->get_meta( '_cardknox_token', true ) );
		}
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_cardknox_token' => array(
					'value' => get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_cardknox_token', true ),
					'label' => 'Cardknox Token',
				),
				'_cardknox_MaskedCardNumber' => array(
					'value' => get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_cardknox_masked_card', true ),
					'label' => 'Cardknox Masked Card',
				),
				'_cardknox_cardtype' => array(
					'value' => get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_cardknox_cardtype', true ),
					'label' => 'Cardknox Card Type',
				),
			),
		);
		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {
			if (  empty( $payment_meta['post_meta']['_cardknox_token']['value'] )) {
				throw new Exception( 'Invalid card on file.' );
			}
		}
	}

	//todo need to figure out what this does
	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 1.7.5
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		$customer_user = $this->wc_pre_30 ? $subscription->customer_user : $subscription->get_customer_id();
		// bail for other payment methods
		if ( $this->id !== ( $this->wc_pre_30 ? $subscription->payment_method : $subscription->get_payment_method() ) || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$cardknox_card_id     = get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_cardknox_token', true );
		$cardknox_masked_card		= get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_cardknox_masked_card', true );
		$cardknox_cardtype = 	 get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_cardknox_cardtype', true );

		$payment_method_to_display = sprintf( __( 'Via %1$s card %2$s', 'woocommerce-gateway-cardknox' ), $cardknox_cardtype, $cardknox_masked_card );

		if ( ! $cardknox_card_id || ! is_string( $cardknox_card_id ) ) {
			$user_id            = $customer_user;
			$cardknox_card_id     = get_user_meta( $user_id, '_cardknox_token', true );
		}

		// If we couldn't find a Cardknox customer linked to the account, fallback to the order meta data.

//		if ( false !== $subscription->order ) {
//			$cardknox_card_id     = get_post_meta( ( $this->wc_pre_30 ? $subscription->order->id : $subscription->get_parent_id() ), '_cardknox_token', true );
//		}

		return $payment_method_to_display;
	}

	/**
	 * Logs
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 *
	 * @param string $message
	 */
	public function log( $message ) {
		$options = get_option( 'woocommerce_cardknox_settings' );

		if ( 'yes' === $options['logging'] ) {
			WC_Cardknox::log( $message );
		}
	}
}
