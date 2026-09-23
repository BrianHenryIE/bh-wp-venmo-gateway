# Changelog

### 4.3.0 September 2026

* Add: WooCommerce shop managers and GiveWP managers can add, edit, check and remove the email accounts checked for payment emails; the logs page remains administrator-only.
* Add: shared log level setting, configurable on the WooCommerce and GiveWP gateway settings pages, with a link to the logs page.

### 4.2.1 September 2026

* Add: WooCommerce shop managers and GiveWP managers can view and process payment emails and view unreconciled orders; email accounts and logs remain administrator-only.
* Add: link from the WooCommerce gateway settings page to the orders list filtered to pending and on-hold Venmo orders.
* Add: "Unreconciled orders" admin page listing orders and donations awaiting a Venmo payment email (from bh-wp-order-email-reconcile), linked from plugins.php.
* Update: bh-wp-order-email-reconcile – each processed email's extraction result is saved and shown on the email; reconciled emails are kept and link to their order.
* Fix: update bh-wp-logger and bh-wp-private-uploads to fix huge log files and undismissable notices.

### 4.2.0 August 2026

* Add: POSTing email via REST

### 4.1.0 – July 2026

* Add Give WP support

### 4.0.0 – April 2026

* Rename to BH WP Venmo Gateway

### 3.2.0 – April 2026

* Save and autofill customer Venmo username
* Display customer and store Venmo usernames on Thank You page and in emails
* Improve "required*" explantation: "This is required to reconcile payments."
* Attempt to use inline PNGs/JPGs in emails (unfortunately Gmail doesn't display inline images)

### 3.1.3

* Fix: title not displaying in admin
* Fix: QR code in email

### 3.1.2

* Use `venmo://` URL for QR code 

### 3.1.1

* Use different URL for QR code vs anchor link 

### 3.1.0

* Add QR code to admin order UI.
* Hide the word Venmo/i at the checkout – use the image, but allow customising the text.

### 3.0.1

* Fix: QR code opens 

### 3.0.0

* Add blocks checkout support

~v15 now.

### 2.2.0

Order notes now say who payment was directed to, with link to Venmo profile.



### 2.1.0

* Updated IMAP Reconcile to use order ids found in transaction notes.
* PHPCS, PHPStan, PhpUnit improvements.



