<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Cardknox_ApplePay_Blocks_Support extends AbstractPaymentMethodType {

    // Must match your WC gateway id (cardknox-applepay) and the JS method name
    protected $name = 'cardknox-applepay';

    protected $settings = [];

    public function initialize() {
        // Apple Pay settings live here (not in woocommerce_cardknox_settings)
        $this->settings = get_option('woocommerce_cardknox-applepay_settings', []);
    }

    public function is_active() {
        return ! empty($this->settings['applepay_enabled']) && 'yes' === $this->settings['applepay_enabled'];
    }

    public function get_payment_method_script_handles() {
        $handle     = 'wc-cardknox-blocks';
        $base_dir   = plugin_dir_path(__FILE__) . '../blocks/build/';
        $asset_file = $base_dir . 'index.asset.php';
        $script_url = plugins_url('../blocks/build/index.js', __FILE__);

        $asset = file_exists($asset_file)
            ? include $asset_file
            : [
                'dependencies' => [ 'wp-element', 'wc-blocks-registry', 'wc-blocks-checkout' ],
                'version'      => file_exists($base_dir . 'index.js') ? filemtime($base_dir . 'index.js') : time(),
            ];

        wp_register_script($handle, $script_url, $asset['dependencies'], $asset['version'], true);

        // Provide settings to the Blocks JS (very lightweight)
        $data = $this->get_payment_method_data();
        wp_add_inline_script($handle, 'window.WCCardknoxApplePayBlocks = ' . wp_json_encode($data) . ';', 'before');

        return [ $handle ];
    }

    public function get_payment_method_data() {
        $merchant_identifier = $this->settings['applepay_merchant_identifier'] ?? '';

        // Best-effort totals for the Apple sheet; server still validates
        $amount = 0.0;
        if (function_exists('WC') && WC()->cart) {
            $amount = floatval(WC()->cart->total);
        }

        $country = (function_exists('WC') && WC()->countries) ? WC()->countries->get_base_country() : 'US';

        $supported = $this->settings['applepay_supported_networks'] ?? 'visa,masterCard,amex,discover';
        $supported = array_map('trim', explode(',', $supported));

        return [
            'merchantId'           => $merchant_identifier,
            'merchant_identifier'  => $merchant_identifier, // alternate key for safety
            'displayName'          => $this->settings['applepay_title'] ?? get_bloginfo('name'),
            'countryCode'          => $country,
            'currencyCode'         => get_woocommerce_currency(),
            'supportedNetworks'    => $supported,
            'totalLabel'           => $this->settings['applepay_title'] ?? get_bloginfo('name'),
            'amount'               => number_format($amount, 2, '.', ''),
        ];
    }
}