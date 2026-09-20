[![WordPress tested 5.5](https://img.shields.io/badge/WordPress-v5.5%20tested-0073aa.svg)](https://wordpress.org/plugins/bh-wp-venmo-gateway) [![PHPCS WPCS](https://img.shields.io/badge/PHPCS-WordPress%20Coding%20Standards-8892BF.svg)](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) [![PHPUnit ](.github/coverage.svg)](https://brianhenryie.github.io/plugin_slug/)

# BH WP Venmo Gateway

https://help.venmo.com/cs/articles/personal-qr-codes-on-venmo-faq-vhel316

// https://www.reddit.com/r/venmo/comments/1bfvx71/anyone_else_notice_that_venmo_deep_links_are_no/
venmo://paycharge?txn=pay&recipients=~MYUSERNAME~&note=~PRE-FILLEDCOMMENT~&amount=~PREFILLEDAMOUNT~

https://account.venmo.com/pay?recipients=%40friend-username&amount=25.50&note=For%20lunch&txn=charge

PAY:
https://account.venmo.com/pay?audience=[AUDIENCE]&amount=[AMOUNT]&note=[NOTES]&recipients=%2C[USERNAME]&txn=pay

REQUEST:
https://account.venmo.com/pay?audience=[AUDIENCE]&amount=[AMOUNT]&note=[NOTES]&recipients=%2C[USERNAME]&txn=charge

VARIABLES:
[AUDIENCE] = Can be either: "private, friends, public"
[AMOUNT] = Needs to be in format "00.00"
[NOTES] = Needs to be in HTML "%20 for spaces"
[USERNAME] = Username to pay/request no @ sign.

EXAMPLE (Request $2.00 from '@Username with note "Note here":
https://account.venmo.com/pay?audience=private&amount=2.00&note=Note%20here&recipients=%2CUsername&txn=charge




https://developer.paypal.com/braintree/in-person/guides/paypal-and-venmo-qrc/
https://developer.paypal.com/docs/multiparty/checkout/pay-with-venmo/


Someone else will always have done it first:
* https://github.com/search?q=venmo%20qr&type=repositories
* https://github.com/mmqn/venmo-qr-code-generator/blob/fc4a2d8c9d3f5a79b3eacc59638ace896a74d7d2/src/App.jsx#L21



[![Deploy to Cloudflare](https://deploy.workers.cloudflare.com/button)](https://deploy.workers.cloudflare.com/?url=https://github.com/BrianHenryIE/bh-wp-mailboxes-cloudflare-worker)


TODO:

* check venmo username is not blank at checkout
* people like payment confirmation emails. Maybe WooCommerce has a native one.

What does  /checkout/order-pay/ look like? I think when an order is on-hold that's maybe not avaiable.

`wp option delete bh-wp-venmo-gateway-last-imap-reconcile-run-time`

`wp cron event run bh_wp_venmo_gateway_check_for_payment_emails`


`wp option delete bh-wp-venmo-gateway-last-imap-reconcile-run-time; wp cron event run bh_wp_venmo_gateway_check_for_payment_emails`


## Roles and permissions

Everything in the plugin's WooCommerce and GiveWP settings is gated by those plugins' own capabilities. The payment
emails (the mailbox provided by [bh-wp-mailboxes](https://github.com/BrianHenryIE/bh-wp-mailboxes)) are
administrator-only by default; the plugin lowers that for the roles that process payments:

| Screen / action | Administrator | WooCommerce Shop manager | GiveWP Manager |
|---|---|---|---|
| Emails list, single email, mark read/unread, delete on server, change status | ✓ | ✓ (`manage_woocommerce`) | ✓ (`manage_give_settings`) |
| Extraction result on an email; email log notes | ✓ | ✓ | ✓ |
| Unreconciled orders page | ✓ | ✓ | ✓ |
| Email accounts: add/edit, credentials, "Check now" | ✓ (`manage_options`) | – | – |
| Logs page | ✓ (`manage_options`) | – | – |
| WooCommerce gateway settings | ✓ | ✓ | – |
| GiveWP gateway settings, "Mark paid" on a donation | ✓ | – | ✓ (`edit_give_payments`) |

The GiveWP counterpart of WooCommerce's Shop manager is the **GiveWP Manager** (`give_manager`) role. GiveWP's
Accountant (`give_accountant`) can mark donations paid (`edit_give_payments`) but does not get the emails, since it
lacks `manage_give_settings`; grant it via the filter below if wanted.

The mapping lives in `Admin\Capabilities`, which answers bh-wp-mailboxes' `bh_wp_mailboxes_required_capability` filter
for the emails post type only, so account management stays with administrators. To change it, add a later filter:

```php
add_filter( 'bh_wp_mailboxes_required_capability', function ( string $required, string $capability, string $post_type ): string {
	return 'venmo_payment_emails' === $post_type ? 'edit_give_payments' : $required;
}, 20, 3 );
```

The REST ingress (the Cloudflare worker delivering emails) authenticates with an application password; that user
needs the same capability, i.e. administrator, shop manager or GiveWP manager.

## Venmo Transaction Fees

https://help.venmo.com/cs/articles/business-profile-transaction-fees-vhel221

> The seller transaction fee is a standard rate of 1.9% + $0.10 of the payment total

Plugin: [Payment Gateway Based Fees and Discounts for WooCommerce](https://wordpress.org/plugins/checkout-fees-for-woocommerce/)

`wp plugin install checkout-fees-for-woocommerce`

`wp-admin/admin.php?page=wc-settings&tab=alg_checkout_fees&section=pgbf-venmo`

```
wp option update alg_gateways_fees_enabled_venmo "yes"

wp option update alg_gateways_fees_text_venmo "Venmo fixed fee"
wp option update alg_gateways_fees_type_venmo "fixed"
wp option update alg_gateways_fees_value_venmo "0.10"

wp option update alg_gateways_fees_text_2_venmo "Venmo percentage fee"
wp option update alg_gateways_fees_type_2_venmo "percent"
wp option update alg_gateways_fees_value_2_venmo "1.9"
```

# Acknowledgements
