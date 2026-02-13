=== Sola Payment Gateway for WooCommerce ===
Contributors: dlehren
Tags: credit card, woocommerce, payment gateway, apple pay, google pay
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 5.6.0
Stable tag: trunk
License: GNU GPL 3.0

Accept payments with the Sola gateway.

== Description ==

**Sola Payment Gateway for WooCommerce**
**Formerly Cardknox**

Power your WooCommerce store with a smarter, more scalable way to accept payments. The Sola gateway brings enterprise-grade technology to merchants of all sizes, supporting cards, mobile wallets, and recurring payments with built-in PCI compliance and white-glove support.

Key Features

* Mobile Wallets: Accept Apple Pay and Google Pay for faster checkouts
* 3D Secure: SCA-ready authentication for frictionless security
* Tokenization & Card Storage: Enable secure, repeat purchases
* Flexible Transaction Options: Authorize & capture, refund, and void
* Subscriptions-Ready: Fully compatible with WooCommerce Subscriptions
* Hosted iFields: Offload PCI scope with secure, embedded card fields
* WooCommerce Native: Manage transactions directly from your dashboard

Whether you're selling one-time products or subscriptions, Sola’s configurable gateway helps reduce declines, optimize costs, and elevate your checkout experience—without the complexity.

Experience payments that scale with your business.
Visit [Sola](https://solapayments.com) to learn more.

== Changelog ==

= 1.2.84 =

- Added domain accessibility verification during Apple Pay certificate upload.
- Updated tags and plugin metadata for WordPress 6.9 compatibility.
- Fixed session expiration error.

= 1.2.83 =

- Package updates.
- Updated iFields latest version 3.3.2601.2901

= 1.2.82 =

- Updated iFields to version 3.1.2508.1401.
- Fixed deprecated warnings, standardized gateway settings, improved quick checkout behavior and script loading for smoother performance.
- Standardized error messages for full translation readiness and consistent formatting.

= 1.2.81 =

- Added compatibility with WooCommerce Block Editor Checkout.
- Updated all Cardknox references to Sola.
- Added compatibility with WooCommerce High-Performance Order Storage (HPOS).
- Fixed critical get_billing_country error.
- Limited expiration year input to two digits on checkout.

= 1.2.73 =

- Updated iFields latest version 3.0.2503.2101

= 1.2.72 =

- Apple Pay Default Enable - No.
- Google Pay Default Enable - No.

= 1.2.71 =

- Security Updates.

= 1.2.70 =

- Fixed Orderdetail Page

= 1.2.69 =

- Testing the functionality with Woo Subscription's latestversion(Version 6.7.0)

= 1.2.68 =

- Added 3D Secure integration to the checkout for added security
- Added the card brand logos to the Credit Card tender
- Added Digital Wallet (Apple Pay & Google Pay) quick checkout option to cart page
- Added tabs for Apple Pay and Google Pay on Admin Settings
- Fixed issues with Woo Subscription
- Updated tax to be sent to the Gateway
- Fixed issue with white space on Expiry
- Updated iFields latest version 2.15.2405.1601
- Add Tax parameter to Gateway request

= 1.0.16 =

- Add support for GooglePay
- Add support for Apple Pay
- Security updates
- Fix for expiration field issue
- Updated iFields version

= 1.0.14 =

- Added SDK Name and Version
- Update transaction lookup URL to new portal

= 1.0.13 =

- Updated line endings
- Added validation for required settings
- Error saving card should not cause transaction to fail
- Validate payment info

== Installation ==

Copy this folder into the plugins directory and make sure the folder is named woocommerce-gateway-cardknox.

Go to the admin panel to configure the setting and enable the plugin.
