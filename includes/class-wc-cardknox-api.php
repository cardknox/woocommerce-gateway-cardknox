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
 * WC_Cardknox_API class.
 *
 * Communicates with Cardknox API.
 */
class WC_Cardknox_API {

	/**
	 * Cardknox API Endpoint
	 */
	const ENDPOINT = 'https://x1.cardknox.com/gateway';

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $transaction_key = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_transaction_key( $transaction_key ) {
		self::$transaction_key = $transaction_key;
	}

	/**
	 * Get transaction key.
	 * @return string
	 */
	public static function get_transaction_key() {
		if ( ! self::$transaction_key ) {
			$options = get_option( 'woocommerce_cardknox_settings' );
			self::set_transaction_key($options['transaction_key']);
		}
		return self::$transaction_key;
	}

	/**
	 * Send the request to Cardknox's API
	 *
	 * @param array $request
	 * @param string $api
	 * @return array|WP_Error
	 */
	public static function request( $request, $method = 'POST' ) {
		$request['xKey'] =  self::get_transaction_key();
		$request['xVersion'] =  '4.5.5';
		$request['xSoftwareVersion'] =  '1.0.0';
		$request['xSoftwareName'] =  'Wordpress_WooCommerce '. WC()->version;
		self::log( " request: " . print_r( $request, true ) );
		$response = wp_safe_remote_post(
			self::ENDPOINT,
			array(
				'method'        => $method,
				'body'       => apply_filters( 'woocommerce_cardknox_request_body', $request ),
				'timeout'    => 70
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'cardknox_error', __( 'There was a problem connecting to the payment gateway.', 'woocommerce-gateway-cardknox' ) );
		}

		$parsed_response = [];
		parse_str($response['body'], $parsed_response);
		self::log( " reponse: " . print_r( $parsed_response, true ) );

		// Handle response
		if ( ! empty( $parsed_response['xError'] ) ) {
			if ( ! empty( $parsed_response['xErrorCode'] ) ) {
				$code = $parsed_response['xErrorCode'];
			} else {
				$code = 'cardknox_error';
			}
			return new WP_Error( $code, $parsed_response['xError'], 'woocommerce-gateway-cardknox' );
		} else {
			return $parsed_response;
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
	public static function log( $message ) {
		$options = get_option( 'woocommerce_cardknox_settings' );

		if ( 'yes' === $options['logging'] ) {
			WC_Cardknox::log( $message );
		}
	}
}
