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
if (!defined('ABSPATH')) {
    exit;
}

global $woocommerce;
$countries_obj   = new WC_Countries();
$countries   = $countries_obj->__get('countries');
$wc_cardknox_settings = array(
    'enabled' => array(
        'title'       => __('Enable/Disable', 'woocommerce-gateway-cardknox'),
        'label'       => __('Enable Cardknox', 'woocommerce-gateway-cardknox'),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no',
    ),
    'title' => array(
        'title'       => __('Title', 'woocommerce-gateway-cardknox'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-cardknox'),
        'default'     => __('Credit Card', 'woocommerce-gateway-cardknox'),
        'desc_tip'    => true,
    ),
    'description' => array(
        'title'       => __('Description', 'woocommerce-gateway-cardknox'),
        'type'        => 'text',
        'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-cardknox'),
        'default'     => __('Pay with your credit card.', 'woocommerce-gateway-cardknox'),
        'desc_tip'    => true,
    ),
    'token_key' => array(
        'title'       => __('Cardknox Token Key', 'woocommerce-gateway-cardknox'),
        'type'        => 'text',
        'description' => __('Get your iFields key from your Cardknox account.', 'woocommerce-gateway-cardknox'),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'transaction_key' => array(
        'title'       => __('Cardknox Transaction Key', 'woocommerce-gateway-cardknox'),
        'type'        => 'text',
        'description' => __('Get your API keys from your cardknox account.', 'woocommerce-gateway-cardknox'),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'capture' => array(
        'title'       => __('Capture', 'woocommerce-gateway-cardknox'),
        'label'       => __('Capture charge immediately', 'woocommerce-gateway-cardknox'),
        'type'        => 'checkbox',
        'id'       => 'cardknox_capture',
        'description' => __('Whether or not to immediately capture the transaction. When unchecked, the transaction will need to be captured later.', 'woocommerce-gateway-cardknox'),
        'default'     => 'yes',
        'desc_tip'    => true,
    ),
    'auth_only_order_status' => array(
        'title'       => __('Authorize Only Order Status', 'woocommerce-gateway-cardknox'),
        'type'     => 'select',
        'class'    => 'wc-enhanced-select',
        'id'       => 'cardknox_authonly_status',
        'description' => __('Configures what the order status should be changed to after a authorize only transaction', 'woocommerce-gateway-cardknox'),
        'default'     => 'on-hold',
        'desc_tip'    => true,
        'options'  => array(
            'on-hold'        => __('Set order status to on-hold when payment is authorized', 'woocommerce-gateway-cardknox'),
            'processing' => __('set order status to processing when payment is authorized', 'woocommerce-gateway-cardknox'),
        ),
    ),
    'saved_cards' => array(
        'title'       => __('Saved Cards', 'woocommerce-gateway-cardknox'),
        'label'       => __('Enable Payment via Saved Cards', 'woocommerce-gateway-cardknox'),
        'type'        => 'checkbox',
        'description' => __('If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Cardknox servers, not on your store.', 'woocommerce-gateway-cardknox'),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
    'logging' => array(
        'title'       => __('Logging', 'woocommerce-gateway-cardknox'),
        'label'       => __('Log debug messages', 'woocommerce-gateway-cardknox'),
        'type'        => 'checkbox',
        'description' => __('Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-cardknox'),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
    'bgcolor' => array(
        'title'       => __('Background color', 'woocommerce-gateway-cardknox'),
        'type'        => 'text',
        'description' => __('Background color for card number and cvv. Default #F2F2F2', 'woocommerce-gateway-cardknox'),
        'default'     => '#F2F2F2',
        'desc_tip'    => true,
        'class'       => 'colorpick',
        'css'         => 'width: 6em'
    ),
    'applicable_countries' => array(
        'title'       => __('Payment From Applicable Countries', 'woocommerce-gateway-cardknox'),
        'type'        => 'select',
        'options'     => array(
            '0'       => __('All Allowed Countries', 'woocommerce-gateway-cardknox'),
            '1'       => __('Specific Countries', 'woocommerce-gateway-cardknox'),
        ),
    ),
    'specific_countries' => array(
        'title'          => __('Payment From Specific Countries', 'woocommerce-gateway-cardknox'),
        'type'           => 'multiselect',
        'options'        => $countries
    ),
    'enable-3ds' => array(
        'title'       => __('Enable 3DS', 'woocommerce-gateway-cardknox'),
        'label'       => __('Enable 3DS', 'woocommerce-gateway-cardknox'),
        'type'        => 'checkbox',
        'description' => __('Enable 3DS', 'woocommerce-gateway-cardknox'),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
    '3ds-env' => array(
        'title'       => __('3DS environment', 'woocommerce-gateway-cardknox'),
        'type'     => 'select',
        'class'    => 'wc-enhanced-select',
        'id'       => '3ds-env',
        'description' => __('To set up the environment for processing a 3DS authentication transaction', 'woocommerce-gateway-cardknox'),
        'default'     => 'staging',
        'desc_tip'    => true,
        'options'  => array(
            'staging'    => __('Staging', 'woocommerce-gateway-cardknox'),
            'production' => __('Production', 'woocommerce-gateway-cardknox'),
        ),
    ),
);

$GLOBALS["wc_cardknox_settings"] = $wc_cardknox_settings;
