[![WordPress tested 5.5](https://img.shields.io/badge/WordPress-v5.5%20tested-0073aa.svg)](https://wordpress.org/plugins/bh-wc-venmo-gateway) [![PHPCS WPCS](https://img.shields.io/badge/PHPCS-WordPress%20Coding%20Standards-8892BF.svg)](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) [![PHPUnit ](.github/coverage.svg)](https://brianhenryie.github.io/plugin_slug/)

# BH WC Venmo Gateway

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

Requires php-imap / ext-imap


https://developer.paypal.com/braintree/in-person/guides/paypal-and-venmo-qrc/
https://developer.paypal.com/docs/multiparty/checkout/pay-with-venmo/


Someone else will always have done it first:
* https://github.com/search?q=venmo%20qr&type=repositories
* https://github.com/mmqn/venmo-qr-code-generator/blob/fc4a2d8c9d3f5a79b3eacc59638ace896a74d7d2/src/App.jsx#L21


TODO:

* check venmo username is not blank at checkout
* people like payment confirmation emails. Maybe WooCommerce has a native one.

What does  /checkout/order-pay/ look like? I think when an order is on-hold that's maybe not avaiable.

`wp option delete bh-wc-venmo-gateway-last-imap-reconcile-run-time`

`wp cron event run bh_wc_venmo_gateway_check_for_payment_emails`


`wp option delete bh-wc-venmo-gateway-last-imap-reconcile-run-time; wp cron event run bh_wc_venmo_gateway_check_for_payment_emails`


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
