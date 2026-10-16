<?php
if (!defined('ABSPATH')) {
    exit;
}

/*----Start PLGN-157( ACH ) Task----*/
$wc_cardknox_ach_settings = array(
    'ach_info' => array(
        'title'       => __('ACH Settings', 'woo-cardknox-gateway'),
        'type'        => 'title',
        'description' => __('Please ensure ACH is enabled on your merchant account before activating this feature.', 'woo-cardknox-gateway'),
    ),
    'ach_enabled' => array(
        'title'       => __('Enable ACH', 'woo-cardknox-gateway'),
        'label'       => __('Enable ACH', 'woo-cardknox-gateway'),
        'type'        => 'checkbox',
        'description' => __('Enable ACH (bank account) payments on checkout.', 'woo-cardknox-gateway'),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
    'ach_title' => array(
        'title'       => __('Title', 'woo-cardknox-gateway'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woo-cardknox-gateway'),
        'default'     => __('ACH', 'woo-cardknox-gateway'),
        'desc_tip'    => true,
    ),
    'ach_description' => array(
        'title'       => __('Description', 'woo-cardknox-gateway'),
        'type'        => 'text',
        'description' => __('This controls the description which the user sees during checkout.', 'woo-cardknox-gateway'),
        'default'     => __('Pay securely with your bank account (ACH).', 'woo-cardknox-gateway'),
        'desc_tip'    => true,
    ),
    'ach_reenter' => array(
        'title'       => __('Re-enter Routing or Account Number', 'woo-cardknox-gateway'),
        'label'       => __('Require customers to re-enter routing number', 'woo-cardknox-gateway'),
        'type'        => 'checkbox',
        'description' => __('When enabled, customers must confirm the routing number by entering it twice. Account number is collected securely via Sola iFields and does not require re-entry.', 'woo-cardknox-gateway'),
        'default'     => 'yes',
        'desc_tip'    => true,
    ),
);

$GLOBALS['wc_cardknox_ach_settings'] = $wc_cardknox_ach_settings;
/*----End PLGN-157( ACH ) Task----*/
