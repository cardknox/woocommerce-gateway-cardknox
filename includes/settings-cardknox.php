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
        'title'       => __('Enable/Disable', 'woo-cardknox-gateway'),
        'label'       => __('Enable Sola Payments', 'woo-cardknox-gateway'),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no',
    ),
    'title' => array(
        'title'       => __('Title', 'woo-cardknox-gateway'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woo-cardknox-gateway'),
        'default'     => __('Credit Card', 'woo-cardknox-gateway'),
        'desc_tip'    => true,
    ),
    'description' => array(
        'title'       => __('Description', 'woo-cardknox-gateway'),
        'type'        => 'text',
        'description' => __('This controls the description which the user sees during checkout.', 'woo-cardknox-gateway'),
        'default'     => __('Pay with your credit card.', 'woo-cardknox-gateway'),
        'desc_tip'    => true,
    ),
    'transaction_key' => array(
        'title'       => __('Sola API Key (Transaction Key)', 'woo-cardknox-gateway'),
        'type'        => 'text',
        'description' => __('Get your API keys from The Sola Portal.', 'woo-cardknox-gateway'),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'token_key' => array(
        'title'       => __('Sola iFields Key (Token Key)', 'woo-cardknox-gateway'),
        'type'        => 'text',
        'description' => __('Get your iFields key from The Sola Portal.', 'woo-cardknox-gateway'),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'capture' => array(
        'title'       => __('Capture', 'woo-cardknox-gateway'),
        'label'       => __('Capture charge immediately', 'woo-cardknox-gateway'),
        'type'        => 'checkbox',
        'id'       => 'cardknox_capture',
        'description' => __('Whether or not to immediately capture the transaction. When unchecked, the transaction will need to be captured later.', 'woo-cardknox-gateway'),
        'default'     => 'yes',
        'desc_tip'    => true,
    ),
    'auth_only_order_status' => array(
        'title'       => __('Authorize Only Order Status', 'woo-cardknox-gateway'),
        'type'     => 'select',
        'class'    => 'wc-enhanced-select',
        'id'       => 'cardknox_authonly_status',
        'description' => __('Configures what the order status should be changed to after a authorize only transaction', 'woo-cardknox-gateway'),
        'default'     => 'on-hold',
        'desc_tip'    => true,
        'options'  => array(
            'on-hold'        => __('Set order status to on-hold when payment is authorized', 'woo-cardknox-gateway'),
            'processing' => __('set order status to processing when payment is authorized', 'woo-cardknox-gateway'),
        ),
    ),
    'saved_cards' => array(
        'title'       => __('Saved Cards', 'woo-cardknox-gateway'),
        'label'       => __('Enable Payment via Saved Cards', 'woo-cardknox-gateway'),
        'type'        => 'checkbox',
        'description' => __('If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Sola servers, not on your store.', 'woo-cardknox-gateway'),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
    'logging' => array(
        'title'       => __('Logging', 'woo-cardknox-gateway'),
        'label'       => __('Log debug messages', 'woo-cardknox-gateway'),
        'type'        => 'checkbox',
        'description' => __('Save debug messages to the WooCommerce System Status log.', 'woo-cardknox-gateway'),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
    'bgcolor' => array(
        'title'       => __('Background color', 'woo-cardknox-gateway'),
        'type'        => 'text',
        'description' => __('Background color for card number and cvv. Default #F2F2F2', 'woo-cardknox-gateway'),
        'default'     => '#F2F2F2',
        'desc_tip'    => true,
        'class'       => 'colorpick',
        'css'         => 'width: 6em'
    ),
    'applicable_countries' => array(
        'title'       => __('Payment From Applicable Countries', 'woo-cardknox-gateway'),
        'type'        => 'select',
        'options'     => array(
            '0'       => __('All Allowed Countries', 'woo-cardknox-gateway'),
            '1'       => __('Specific Countries', 'woo-cardknox-gateway'),
        ),
    ),
    'specific_countries' => array(
        'title'          => __('Payment From Specific Countries', 'woo-cardknox-gateway'),
        'type'           => 'multiselect',
        'options'        => $countries
    ),
    'enable-3ds' => array(
        'title'       => __('Enable 3DS', 'woo-cardknox-gateway'),
        'label'       => __('Enable 3DS', 'woo-cardknox-gateway'),
        'type'        => 'checkbox',
        'description' => __('Enable 3DS', 'woo-cardknox-gateway'),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
    '3ds-env' => array(
        'title'       => __('3DS environment', 'woo-cardknox-gateway'),
        'type'     => 'select',
        'class'    => 'wc-enhanced-select',
        'id'       => '3ds-env',
        'description' => __('To set up the environment for processing a 3DS authentication transaction', 'woo-cardknox-gateway'),
        'default'     => 'staging',
        'desc_tip'    => true,
        'options'  => array(
            'staging'    => __('Staging', 'woo-cardknox-gateway'),
            'production' => __('Production', 'woo-cardknox-gateway'),
        ),
    ),
);

$GLOBALS["wc_cardknox_settings"] = $wc_cardknox_settings;
