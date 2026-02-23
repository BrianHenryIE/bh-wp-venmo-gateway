[![WordPress tested 5.5](https://img.shields.io/badge/WordPress-v5.5%20tested-0073aa.svg)](https://wordpress.org/plugins/bh-wc-venmo-gateway) [![PHPCS WPCS](https://img.shields.io/badge/PHPCS-WordPress%20Coding%20Standards-8892BF.svg)](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) [![PHPUnit ](.github/coverage.svg)](https://brianhenryie.github.io/plugin_slug/)

# BH WC Venmo Gateway

https://help.venmo.com/cs/articles/personal-qr-codes-on-venmo-faq-vhel316

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

TODO:

* check venmo username is not blank at checkout
* people like payment confirmation emails. Maybe WooCommerce has a native one.

`wp option delete bh-wc-venmo-gateway-last-imap-reconcile-run-time`

`wp cron event run bh_wc_venmo_gateway_check_for_payment_emails`


`wp option delete bh-wc-venmo-gateway-last-imap-reconcile-run-time; wp cron event run bh_wc_venmo_gateway_check_for_payment_emails`



# Acknowledgements
