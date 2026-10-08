<?php

namespace BrianHenryIE\WP_Venmo_Gateway\Development_Plugin;

class First_Run_Wizards {

	public function register_hooks() {

		add_action( 'plugins_loaded', array( $this, 'give_wp' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'woocommerce' ), 0 );
	}


	/**
	 * GiveWP
	 *
	 * `?page=give-onboarding-wizard`
	 * `admin_init`
	 *
	 * @see \Give\Onboarding\Wizard\Page::redirect()
	 * @see Hooks::addAction()
	 */
	public function give_wp(): void {
		add_filter(
			sprintf(
				'give_disable_hook-%s:%s@%s',
				'admin_init',
				'Give\Onboarding\Wizard\Page',
				'redirect'
			),
			'__return_true',
			10000
		);
	}

	/**
	 * @see https://github.com/bakerdotdev/Disable-WooCommerce-Setup-Wizard/blob/main/disable-woocommerce-setup-wizard.php
	 */
	public function woocommerce(): void {
		add_filter( 'woocommerce_enable_setup_wizard', '__return_false', 0 );
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_true', 0 );

		add_filter( 'woocommerce_task_list_hidden', '__return_true', 0 );
		add_filter(
			'woocommerce_admin_features',
			function ( $features ) {
				if ( ! is_array( $features ) ) {
					return $features;
				}
				$kill = array( 'onboarding', 'tasklist', 'homescreen', 'remote-free-extensions' );
				return array_values( array_diff( $features, $kill ) );
			},
			0
		);

		// If wc-admin tries to push users to its Home screen on first load, kill that.
		remove_action( 'admin_init', 'wc_admin_redirect_to_home', 20 );
	}

	public function gutenberg(): void {
	}
}
