# Options and Metadata

All option, meta and transient keys the plugin stores. The plugin slug is `juiced-venmo-gateway-pro` and the WooCommerce gateway id is `venmo`.

## Plugin's own code (`includes/`)

### Options (`wp_options`)

| Key | Where it's set |
|---|---|
| `juiced_venmo_gateway_pro_activated_time` | `includes/includes/class-activator.php`: a list of activation times mapped to the plugin version. Its last entry times the setup notice. Deleted on uninstall. |
| `juiced_venmo_gateway_pro_installed_version` | `upgrades/class-upgrader.php`: the version whose upgrades have last run. It's checked on every `plugins_loaded` and on activation. When it's absent, the highest version in the activation times is used instead. Deleted on uninstall. |
| `juiced_venmo_gateway_pro_log_level` | Written when the WooCommerce gateway settings or the GiveWP settings are saved (`includes/integrations/woocommerce/class-venmo-gateway.php:211`, `includes/integrations/givewp/class-gateway-settings.php:126`) |
| `woocommerce_venmo_settings` | WooCommerce's own settings array for the gateway. It holds `enabled`, `title`, `description`, `store_venmo_username` and `log_level`. |

### GiveWP options (stored inside GiveWP's `give_settings`)

- `venmo_store_username`
- `venmo_log_level`

### WooCommerce order meta

- `_customer_venmo_username`
- `_destination_account_venmo_username`
- `_customer_venmo_display_name`

### GiveWP donation meta

- `_customer_venmo_username`
- `_destination_account_venmo_username`
- `_customer_venmo_display_name`
- `_venmo_transaction_id`
- `_venmo_payment_date`

### User meta

- `_customer_venmo_username` (`includes/integrations/woocommerce/class-venmo-gateway.php:287, 347`)

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

Disabled: email attachments are not saved (`Settings::get_private_uploads_directory_name()` returns `null`), so it stores nothing.

## Renamed in 4.3.0

`upgrades/class-v430.php`, run by `upgrades/class-upgrader.php`, moves data saved under the old `bh-wp-venmo-gateway` names when a site updates. Reads and writes of the old option names are redirected to the new names.

| Old | New |
|---|---|
| `bh_wp_venmo_gateway_activated_time` | `juiced_venmo_gateway_pro_activated_time` (merged) |
| `bh_wp_venmo_gateway_log_level` | `juiced_venmo_gateway_pro_log_level` |
| `bh-wp-venmo-gateway-recent-error-data` | `juiced-venmo-gateway-pro-recent-error-data` |
| `bh-wp-venmo-gateway-last-logs-view-time` | `juiced-venmo-gateway-pro-last-logs-view-time` |
| `wptrt_notice_dismissed_bh-wp-venmo-gateway-recent-error` | `wptrt_notice_dismissed_juiced-venmo-gateway-pro-recent-error` |
| `bh_wp_oer_bh_wp_venmo_gateway_has_unpaid_orders` | `bh_wp_oer_juiced_venmo_gateway_pro_has_unpaid_orders` |
| `_customer-venmo-username` (order, donation, user meta) | `_customer_venmo_username` |
| `_destination-account-venmo-username` | `_destination_account_venmo_username` |
| `_customer-venmo-display-name` | `_customer_venmo_display_name` |
| `_venmo-transaction-id` | `_venmo_transaction_id` |
| `_venmo-payment-date` | `_venmo_payment_date` |
| `bh_wp_venmo_gateway_*` cron hooks | `juiced_venmo_gateway_pro_*` |

The logger's old `delete_logs_bh-wp-venmo-gateway` cron, the private-uploads crons and the private-uploads dismissed-notice option are deleted.

## Known issues

1. **`uninstall.php` cleans up almost nothing.** It doesn't remove the log level, the gateway settings, the logger or reconcile options, or any of the custom post types and their meta.
