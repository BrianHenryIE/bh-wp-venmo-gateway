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



wp option set woocommerce_coming_soon no
