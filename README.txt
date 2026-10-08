=== Venmo Gateway ===
Contributors: BrianHenryIE, JuicedPlugins
Donate link: https://JuicedPlugins.com/
Tags: venmo, payment-gateway, woocommerce, givewp
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 4.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept payment via Venmo.

== Description ==

Enables Venmo payments which are then reconciled via email receipts.

== Installation ==

Install the .zip file via plugins.php.

== Frequently Asked Questions ==

= Do I need a Venmo Business acccount? =

Refer to the Venmo Terms of Service. This plugin was written prior to the Venmo API being available.

== Changelog ==

= 4.3.0 =

September 2026

* Add: the transaction id on the WooCommerce admin order screen links to the transaction on venmo.com.
* Fix: a reconciled order's meta is prefixed with the gateway id (`venmo_note`, `venmo_transaction_id`, `venmo_transaction_url`), and the transaction url is recorded once rather than as `transaction_id_href` twice and `transaction_url`.
* Add: shared log level setting, configurable on the WooCommerce and GiveWP gateway settings pages, with a link to the logs page.
