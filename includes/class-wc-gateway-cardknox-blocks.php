<?php
/**
 * File: includes/class-wc-gateway-cardknox-blocks.php
 * Cardknox Blocks Support
 *
 * @package WC_Gateway_Cardknox
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Cardknox payment method integration for WooCommerce Blocks
 */
final class WC_Gateway_Cardknox_Blocks_Support extends AbstractPaymentMethodType {
    /**
     * Payment method name
     *
     * @var string
     */
    protected $name = 'cardknox';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_cardknox_settings', array() );
    }

    /**
     * Returns if this payment method should be active.
     *
     * @return boolean
     */
    public function is_active() {
        $gateway = WC()->payment_gateways->payment_gateways()['cardknox'] ?? null;
        return $gateway && 'yes' === $gateway->enabled;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    
     /*----Start PLGN-186----*/
     public function get_payment_method_script_handles() {
        $script_path = '/blocks/build/index.js';
        $script_url  = WC_CARDKNOX_PLUGIN_URL . $script_path;
        $asset_php   = WC_CARDKNOX_PLUGIN_PATH . '/blocks/build/index.asset.php';
        $script_js   = WC_CARDKNOX_PLUGIN_PATH . $script_path;
    
        // Default / fallback
        $loaded = array(
            'dependencies' => array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            'version' => file_exists( $script_js )
                ? filemtime( $script_js )
                : ( defined( 'WC_CARDKNOX_VERSION' ) ? WC_CARDKNOX_VERSION : time() ),
        );
    
        // Try to load asset file (no include_once)
        if ( file_exists( $asset_php ) ) {
            $asset = include $asset_php; // 
    
            if ( is_array( $asset ) ) {
                if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
                    $loaded['dependencies'] = $asset['dependencies'];
                }
                if ( isset( $asset['version'] ) ) {
                    $loaded['version'] = $asset['version'];
                }
            }
        }
    
        $deps = $loaded['dependencies'];
        if ( ! in_array( 'cardknox-ifields', $deps, true ) ) {
            $deps[] = 'cardknox-ifields';
        }
    
        wp_register_script( 'cardknox-ifields', CARDKNOX_IFIELDS_URL, array(), '3.0.2503.2101', false );
        wp_enqueue_script( 'cardknox-ifields' );
    
        wp_register_script(
            'wc-cardknox-blocks',
            $script_url,
            $deps,
            $loaded['version'],
            true
        );
    
        wp_set_script_translations( 'wc-cardknox-blocks', 'woocommerce-gateway-cardknox' );
    
        return array( 'wc-cardknox-blocks' );
    }       
     /*----End   PLGN-186----*/

    /**
     * Returns an array of data to be used by the block on the frontend.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $gateway = WC()->payment_gateways->payment_gateways()['cardknox'] ?? null;
        
        if ( ! $gateway ) {
            return array();
        }

        // Get the iFields key - check multiple possible option names
        $ifields_key = '';
        if ( method_exists( $gateway, 'get_option' ) ) {
            $ifields_key = $gateway->get_option( 'ifields_key' );
            if ( empty( $ifields_key ) ) {
                $ifields_key = $gateway->get_option( 'ifields_public_key' );
            }
            if ( empty( $ifields_key ) ) {
                // Try to get from API settings
                $ifields_key = $gateway->get_option( 'public_key' );
            }
            if ( empty( $ifields_key ) ) {
                // Fallback to main publishable key used by classic checkout
                $ifields_key = $gateway->get_option( 'token_key' );
            }
        }

        return array(
            'title' => $gateway->get_option( 'title' ),
            'description' => $gateway->get_option( 'description' ),
            'supports' => array_filter( $gateway->supports, array( $gateway, 'supports' ) ),
            'showSaveOption' => $gateway->get_option( 'saved_cards' ) === 'yes',
            'iFieldsKey' => $ifields_key,
            'softwareName' => get_bloginfo( 'name' ),
            'softwareVersion' => WC_CARDKNOX_VERSION,
            'testMode' => $gateway->get_option( 'testmode' ) === 'yes',
            'savedCards' => $this->get_saved_cards(),
        );
    }

    /**
     * Get saved payment methods for the current customer
     *
     * @return array
     */
    private function get_saved_cards() {
        if ( ! is_user_logged_in() ) {
            return array();
        }

        $tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'cardknox' );
        $saved_cards = array();

        foreach ( $tokens as $token ) {
            $masked = $token->get_meta( 'cardknox_masked', true );
            if ( empty( $masked ) ) {
                $masked = $this->derive_masked_from_brand_and_last4( $token->get_card_type(), $token->get_last4() );
                if ( $masked ) {
                    $token->update_meta_data( 'cardknox_masked', $masked );
                    $token->save();
                }
            }
            $saved_cards[] = array(
                'token_id' => $token->get_id(),
                'card_type' => $token->get_card_type(),
                'last4' => $token->get_last4(),
                'exp_month' => $token->get_expiry_month(),
                'exp_year' => $token->get_expiry_year(),
                'masked' => $masked,
            );
        }

        return $saved_cards;
    }

    /**
     * Derive Cardknox-style masked number like 4xxxxxxxxxxx1111 from brand + last4.
     */
    private function derive_masked_from_brand_and_last4( $brand, $last4 ) {
        $brand = strtolower( trim( (string) $brand ) );
        $last4 = preg_replace( '/\D+/', '', (string) $last4 );
        if ( strlen( $last4 ) !== 4 ) {
            return '';
        }
        $len_by_brand = array(
            'amex' => 15,
            'american express' => 15,
            'visa' => 16,
            'mastercard' => 16,
            'discover' => 16,
            'diners' => 16,
            'diners club' => 16,
            'jcb' => 16,
        );
        $first_digit = array(
            'amex' => '3',
            'american express' => '3',
            'visa' => '4',
            'mastercard' => '5',
            'discover' => '6',
            'diners' => '3',
            'diners club' => '3',
            'jcb' => '3',
        );
        $length = isset( $len_by_brand[ $brand ] ) ? $len_by_brand[ $brand ] : 16;
        $first  = isset( $first_digit[ $brand ] ) ? $first_digit[ $brand ] : '4';
        $num_xs = max( 0, $length - 1 - 4 );
        return $first . str_repeat( 'x', $num_xs ) . $last4;
    }
}