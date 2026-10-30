<?php
if (!defined('ABSPATH')) {
    exit;
}


$wc_cardknox_ach_settings = array(
    'ach_info' => array(
        'title'       => __('ACH Settings', 'woo-cardknox-gateway'),
        'type'        => 'title',
        'description' => __('Please ensure ACH is enabled on your Sola Account before activating this feature.', 'woo-cardknox-gateway'),
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
);

$GLOBALS['wc_cardknox_ach_settings'] = $wc_cardknox_ach_settings;

