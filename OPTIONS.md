# Options and Metadata

All option, meta and transient keys the plugin stores. The plugin slug is `juiced-venmo-gateway-pro` and the WooCommerce gateway id is `venmo`.

## Plugin's own code (`includes/`)

### Options (`wp_options`)

| Key | Where it's set |
|---|---|
| `juiced_venmo_gateway_pro_activated_time` | `includes/includes/class-activator.php:28`: a list of activation times mapped to the plugin version |
| `juiced_venmo_gateway_pro_last_activated_time` | Only read (`includes/admin/class-admin.php:65`) and deleted (`uninstall.php:33`). Nothing ever writes it. |
| `juiced_venmo_gateway_pro_log_level` | Written when the WooCommerce gateway settings or the GiveWP settings are saved (`includes/integrations/woocommerce/class-venmo-gateway.php:211`, `includes/integrations/givewp/class-gateway-settings.php:126`) |
| `woocommerce_venmo_settings` | WooCommerce's own settings array for the gateway. It holds `enabled`, `title`, `description`, `store_venmo_username` and `log_level`. |

### GiveWP options (stored inside GiveWP's `give_settings`)

- `venmo_store_username`
- `venmo_log_level`

### WooCommerce order meta

- `_customer-venmo-username`
- `_destination-account-venmo-username`
- `_customer-venmo-display-name`

### GiveWP donation meta

- `_customer-venmo-username`
- `_destination-account-venmo-username`
- `_customer-venmo-display-name`
- `_venmo-transaction-id`
- `_venmo-payment-date`

### User meta

- `_customer-venmo-username` (`includes/integrations/woocommerce/class-venmo-gateway.php:287, 347`)

## Bundled libraries (`vendor-prefixed/brianhenryie/`)

### bh-wp-logger

Options:

- `juiced-venmo-gateway-pro-recent-error-data`
- `juiced-venmo-gateway-pro-last-logs-view-time`
- `wptrt_notice_dismissed_juiced-venmo-gateway-pro-recent-error`

Transients:

- `juiced-venmo-gateway-pro-last-log-time`
- `juiced-venmo-gateway-pro-logged-{level}-{md5}`
- `log_deprecated_function_{fn}_juiced-venmo-gateway-pro`
- `log_deprecated_argument_{fn}_juiced-venmo-gateway-pro`
- `log_doing_it_wrong_run_{fn}_juiced-venmo-gateway-pro`
- `log_deprecated_hook_run_{hook}_juiced-venmo-gateway-pro`

The logger's trait would also read `juiced-venmo-gateway-pro_log_level`, but `Settings::get_log_level()` overrides it.

### bh-wp-order-email-reconcile

Option:

- `bh_wp_oer_juiced_venmo_gateway_pro_has_unpaid_orders` (stored with autoload off)

Order meta:

- `bh_wp_oer_email_message_id`
- `bh_wp_oer_email_post_id`
- `venmo_transaction_url`
- `venmo_{note-name}`: in practice `venmo_note` and `venmo_transaction_id`

Email post meta:

- `bh_wp_oer_extraction`
- `bh_wp_oer_reconciled_order_id`
- `bh_wp_oer_reconciled_order_integration`

### bh-wp-mailboxes

Post meta on the "Venmo Email Accounts" custom post type:

- `connection_type_class`
- `email_address`
- `display_name`
- `from_address_regex_filter`
- `body_identifier_regex_filter`
- `after_download_remote_email_action`
- `delete_local_emails_after_n_days`
- `total_emails_downloaded_count`
- `total_emails_saved_count`
- `last_checked_time`
- `last_successful_login_time`
- `last_failed_login_time`

Post meta on the "Venmo Payment Emails" custom post type:

- `attachment_ids`
- `from_address`
- `is_remote_read`
- `is_remote_deleted`
- `remote_uid`
- `remote_folder`
- `remote_uid_validity`
- `in_reply_to` (one row per referenced id)
- `references` (one row per referenced id)

User meta:

- `bh_wp_mailboxes_auth_failure_dismissed_{notice-id}`

### bh-wp-private-uploads

Used for email attachments; post type `juiced__email_attach`.

- Transient: `bh_wp_private_uploads_juiced__email_attach_is_private`
- Option: `wptrt_notice_dismissed_juiced--email-attach-private-uploads-url-is-public`

## Known issues

1. **Activation time keys don't match.** The activator writes `juiced_venmo_gateway_pro_activated_time`, but the admin code reads and uninstall deletes `juiced_venmo_gateway_pro_last_activated_time`. So the admin always falls back to `time()`, and the real option is left behind after uninstall.
2. **`uninstall.php` cleans up almost nothing.** It doesn't remove the log level, the gateway settings, the logger or reconcile options, or any of the custom post types and their meta.
