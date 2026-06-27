#!/bin/bash

PLUGIN_SLUG=$1;
# Print the script name.
echo "Running " $(basename "$0") " for " $PLUGIN_SLUG;

mkdir /var/www/html/wp-content/uploads || true;
chmod a+w /var/www/html/wp-content/uploads;

echo "wp plugin activate --all"
wp plugin activate --all

# Install jq to manipulate json (optional output of WP CLI commands)
if command -v jq &> /dev/null; then
	echo "jq is already installed."
else
	echo "jq not found, installing..."
	sudo apk add jq
fi

echo "Set up pretty permalinks for REST API."
wp rewrite structure /%year%/%monthnum%/%postname%/ --hard;

# Set up WooCommerce pages.
if ! wp option get woocommerce_shop_page_id 2>/dev/null | grep -q '^[1-9]'; then
  echo "Creating WooCommerce pages..."
  wp wc --user=1 tool run install_pages 2>/dev/null || true
fi

# Enable Venmo gateway with a test username.
echo "Configuring Venmo payment gateway..."
wp option set woocommerce_venmo_settings '{"enabled":"yes","title":"Venmo","description":"Pay with Venmo","store_venmo_username":"testvendor"}' --format=json 2>/dev/null || true

# Create a simple product for checkout testing.
if ! wp post list --post_type=product --field=post_title 2>/dev/null | grep -q "Test Product"; then
  echo "Creating test product..."
  wp wc product create --user=1 --name="Test Product" --regular_price="19.99" --status=publish 2>/dev/null || true
fi

echo "Installing and activating the WordPress Importer plugin..."
wp plugin install wordpress-importer --activate

echo "Importing WooCommerce sample products..."
wp option get sample_products_installed
if [ $? -ne 0 ]; then
    echo "Importing sample products..."
    wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip
    wp option add sample_products_installed 1
else
    echo "Sample products already imported."
fi

# Trying to disable "Welcome to Woo!" – "Skip guided setup"
# wp-admin/admin.php?page=wc-admin&path=%2Fsetup-wizard
wp user meta update 1 wp_persisted_preferences '{"core":{"isComplementaryAreaVisible":true},"core\/edit-post":{"welcomeGuide":false},"_modified":"2026-01-29T22:58:19.661Z"}' --format=json

wp option set woocommerce_coming_soon no

# ── GiveWP ─────────────────────────────────────────────────────────────────

echo "Configuring GiveWP Venmo gateway..."

# Disable test mode — Venmo payments work the same in test or live mode.
wp give test-mode off 2>/dev/null || true

# Enable the Venmo gateway for legacy (v2) forms.
wp option patch update give_settings gateways '{"venmo":"1"}' --format=json 2>/dev/null || \
  wp option patch insert give_settings gateways '{"venmo":"1"}' --format=json 2>/dev/null || true

# Enable the Venmo gateway for modern (v3) forms — uses a separate setting.
wp option patch update give_settings gateways_v3 '{"venmo":"1"}' --format=json 2>/dev/null || \
  wp option patch insert give_settings gateways_v3 '{"venmo":"1"}' --format=json 2>/dev/null || true

# Set store Venmo username once (hardcoded in tests as 'testvendor').
wp option patch update give_settings venmo_store_username 'testvendor' 2>/dev/null || \
  wp option patch insert give_settings venmo_store_username 'testvendor' 2>/dev/null || true

# Create GiveWP demo pages (gives/history/etc.) for manual developer browsing.
wp give test-demonstration-page 2>/dev/null || true

# Create legacy (v2) form and /donate/ page.
if ! wp post list --post_type=page --post_status=publish --field=post_name 2>/dev/null | grep -q "^donate$"; then
  echo "Creating GiveWP legacy donation form..."
  LEGACY_FORM_ID=$(wp post create \
    --post_type=give_forms \
    --post_title="Test Donation Form (Legacy)" \
    --post_status=publish \
    --porcelain 2>/dev/null)
  if [ -n "$LEGACY_FORM_ID" ]; then
    wp post meta set "$LEGACY_FORM_ID" _give_price_option        'set'
    wp post meta set "$LEGACY_FORM_ID" _give_set_price           '25.00'
    wp post meta set "$LEGACY_FORM_ID" _give_goal_option         'disabled'
    wp post meta set "$LEGACY_FORM_ID" _give_display_style       'onpage'
    wp post meta set "$LEGACY_FORM_ID" _give_show_register_form  'none'
    wp post create \
      --post_type=page --post_title="Donate" --post_name="donate" --post_status=publish \
      --post_content="<!-- wp:shortcode -->[give_form id=\"$LEGACY_FORM_ID\"]<!-- /wp:shortcode -->" \
      2>/dev/null || true
    echo "Legacy form ID: $LEGACY_FORM_ID"
  fi
fi

# Create modern (v3/Sequoia) form and /donate-v3/ page.
if ! wp post list --post_type=page --post_status=publish --field=post_name 2>/dev/null | grep -q "^donate-v3$"; then
  echo "Creating GiveWP Sequoia (v3) donation form..."
  V3_FORM_ID=$(wp eval '
    $formId = wp_insert_post([
        "post_type"   => "give_forms",
        "post_title"  => "Test Donation Form (v3)",
        "post_status" => "publish",
    ]);
    if (!$formId || is_wp_error($formId)) { exit(1); }
    $blocksJson   = file_get_contents(GIVE_PLUGIN_DIR . "src/FormBuilder/resources/js/form-builder/src/blocks.json");
    $settingsJson = json_encode([
        "formTitle"          => "Test Donation Form (v3)",
        "enableDonationGoal" => false,
        "goalAmount"         => 500,
        "enableAutoClose"    => false,
        "registration"       => "none",
        "goalType"           => "amount",
        "designId"           => "classic",
        "showHeading"        => true,
        "showDescription"    => true,
        "heading"            => "Support Our Cause",
        "description"        => "Help our organization by donating today!",
        "formStatus"         => "publish",
    ]);
    give()->form_meta->update_meta($formId, "formBuilderSettings", $settingsJson);
    give()->form_meta->update_meta($formId, "formBuilderFields",   $blocksJson);
    echo $formId;
  ' 2>/dev/null)
  if [ -n "$V3_FORM_ID" ]; then
    wp post create \
      --post_type=page --post_title="Donate V3" --post_name="donate-v3" --post_status=publish \
      --post_content="[give_form id=\"$V3_FORM_ID\"]" \
      2>/dev/null || true
    echo "V3 form ID: $V3_FORM_ID"
  fi
fi
