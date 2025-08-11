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
    public function get_payment_method_script_handles() {
        $script_path = '/blocks/build/index.js';
        $script_url = WC_CARDKNOX_PLUGIN_URL . $script_path;
        $script_asset_path = WC_CARDKNOX_PLUGIN_PATH . '/blocks/build/index.asset.php';
        
        $script_asset = file_exists( $script_asset_path )
            ? require( $script_asset_path )
            : array(
                'dependencies' => array(
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ),
                'version' => WC_CARDKNOX_VERSION
            );

        // Ensure the iFields SDK is loaded before our script to avoid race conditions
        $deps = isset($script_asset['dependencies']) && is_array($script_asset['dependencies'])
            ? $script_asset['dependencies']
            : array();
        if (!in_array('cardknox-ifields', $deps, true)) {
            $deps[] = 'cardknox-ifields';
        }

        wp_register_script(
            'wc-cardknox-blocks',
            $script_url,
            $deps,
            $script_asset['version'],
            true
        );

        wp_set_script_translations( 'wc-cardknox-blocks', 'woocommerce-gateway-cardknox' );

        // Load iFields SDK before our script
        wp_register_script(
            'cardknox-ifields',
            'https://cdn.cardknox.com/ifields/3.0.2503.2101/ifields.min.js',
            array(),
            '3.0.2503.2101',
            false // Load in header to ensure it's available
        );
        
        // Enqueue iFields first
        wp_enqueue_script( 'cardknox-ifields' );

        return array( 'wc-cardknox-blocks' );
    }

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
            $saved_cards[] = array(
                'token_id' => $token->get_id(),
                'card_type' => $token->get_card_type(),
                'last4' => $token->get_last4(),
                'exp_month' => $token->get_expiry_month(),
                'exp_year' => $token->get_expiry_year(),
            );
        }

        return $saved_cards;
    }
}