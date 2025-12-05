=== Sola Payment Gateway for WooCommerce ===
Contributors: dlehren
Tags: credit card, gateway, cardknox, woocommerce
Requires at least: 6.5
Tested up to: 6.7.1
Requires PHP: 5.6.0
Stable tag: trunk
License: GNU GPL 3.0

Accept payments with the Sola gateway.

== Description ==

Adds the ability to accept credit cards and handles sending the information to the Sola gateway for credit card processing using the Sola API.

See this link for information about the Sola gateway: https://www.cardknox.com, and this one for the API information: https://kb.cardknox.com/api/

The plugin supports the following transaction types: authorize only, capture, voiding, and refunding.

It supports storing cards on file as well using tokenization and works with the WooCommerce Subscriptions plugin (https://woocommerce.com/products/woocommerce-subscriptions) for recurring payments.

== Installation ==
Copy this folder into the plugins directory and make sure the folder is named woocommerce-gateway-cardknox.

Go to the admin panel to configure the setting and enable the plugin.
