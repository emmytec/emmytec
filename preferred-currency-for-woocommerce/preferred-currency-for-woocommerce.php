<?php
/**
 * Plugin Name: Preferred Currency for WooCommerce
 * Description: Switch WooCommerce store currency based on each user's saved preference.
 * Version: 1.0.0
 * Author: Emmytec
 * License: GPL-2.0-or-later
 * Text Domain: preferred-currency-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Preferred_Currency_For_WooCommerce {
	const USER_META_KEY = '_preferred_woocommerce_currency';
	const COOKIE_KEY    = 'preferred_woocommerce_currency';
	const NONCE_ACTION  = 'pcfw_save_currency';

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_currency', array( $this, 'apply_preferred_currency' ), 20 );
		add_action( 'show_user_profile', array( $this, 'render_profile_currency_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_currency_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_currency_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_currency_field' ) );
		add_shortcode( 'wc_currency_switcher', array( $this, 'render_switcher_shortcode' ) );
		add_action( 'init', array( $this, 'maybe_handle_switcher_submission' ) );
	}

	/**
	 * Resolve and return the selected store currency.
	 *
	 * @param string $default_currency The WooCommerce default currency.
	 *
	 * @return string
	 */
	public function apply_preferred_currency( $default_currency ) {
		$preferred_currency = $this->get_preferred_currency();

		if ( ! $preferred_currency ) {
			return $default_currency;
		}

		$enabled_currencies = $this->get_enabled_currencies();

		if ( ! isset( $enabled_currencies[ $preferred_currency ] ) ) {
			return $default_currency;
		}

		return $preferred_currency;
	}

	/**
	 * Get preferred currency from user meta or cookie.
	 *
	 * @return string
	 */
	private function get_preferred_currency() {
		if ( is_user_logged_in() ) {
			$currency = get_user_meta( get_current_user_id(), self::USER_META_KEY, true );
			if ( is_string( $currency ) && '' !== $currency ) {
				return sanitize_text_field( $currency );
			}
		}

		if ( isset( $_COOKIE[ self::COOKIE_KEY ] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_KEY ] ) ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		}

		return '';
	}

	/**
	 * Render preferred currency field on WP profile screens.
	 *
	 * @param WP_User $user User object.
	 *
	 * @return void
	 */
	public function render_profile_currency_field( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$enabled_currencies = $this->get_enabled_currencies();
		$current            = get_user_meta( $user->ID, self::USER_META_KEY, true );
		?>
		<h2><?php esc_html_e( 'WooCommerce Currency Preference', 'preferred-currency-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="pcfw_preferred_currency"><?php esc_html_e( 'Preferred Currency', 'preferred-currency-for-woocommerce' ); ?></label></th>
				<td>
					<select name="pcfw_preferred_currency" id="pcfw_preferred_currency">
						<option value=""><?php esc_html_e( 'Use Store Default', 'preferred-currency-for-woocommerce' ); ?></option>
						<?php foreach ( $enabled_currencies as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'This currency will be used on the shop whenever this user is logged in.', 'preferred-currency-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save preferred currency from WP profile screens.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public function save_profile_currency_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST['pcfw_preferred_currency'] ) ) {
			return;
		}

		$currency_code      = sanitize_text_field( wp_unslash( $_POST['pcfw_preferred_currency'] ) );
		$enabled_currencies = $this->get_enabled_currencies();

		if ( '' === $currency_code ) {
			delete_user_meta( $user_id, self::USER_META_KEY );
			return;
		}

		if ( isset( $enabled_currencies[ $currency_code ] ) ) {
			update_user_meta( $user_id, self::USER_META_KEY, $currency_code );
		}
	}

	/**
	 * Render currency switcher form via shortcode.
	 *
	 * @return string
	 */
	public function render_switcher_shortcode() {
		$enabled_currencies = $this->get_enabled_currencies();
		$current            = $this->get_preferred_currency();

		ob_start();
		?>
		<form method="post" class="pcfw-currency-switcher">
			<label for="pcfw_switcher_currency"><?php esc_html_e( 'Choose Currency', 'preferred-currency-for-woocommerce' ); ?></label>
			<select name="pcfw_switcher_currency" id="pcfw_switcher_currency">
				<option value=""><?php esc_html_e( 'Use Store Default', 'preferred-currency-for-woocommerce' ); ?></option>
				<?php foreach ( $enabled_currencies as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php wp_nonce_field( self::NONCE_ACTION, 'pcfw_switcher_nonce' ); ?>
			<button type="submit" name="pcfw_switcher_submit" value="1"><?php esc_html_e( 'Save', 'preferred-currency-for-woocommerce' ); ?></button>
		</form>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Handle currency switcher form submit.
	 *
	 * @return void
	 */
	public function maybe_handle_switcher_submission() {
		if ( empty( $_POST['pcfw_switcher_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['pcfw_switcher_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pcfw_switcher_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		$currency_code      = isset( $_POST['pcfw_switcher_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['pcfw_switcher_currency'] ) ) : '';
		$enabled_currencies = $this->get_enabled_currencies();
		$cookie_value       = '';

		if ( '' !== $currency_code && ! isset( $enabled_currencies[ $currency_code ] ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			if ( '' === $currency_code ) {
				delete_user_meta( get_current_user_id(), self::USER_META_KEY );
			} else {
				update_user_meta( get_current_user_id(), self::USER_META_KEY, $currency_code );
			}
		}

		if ( '' !== $currency_code ) {
			$cookie_value = $currency_code;
		}

		wc_setcookie( self::COOKIE_KEY, $cookie_value, time() + MONTH_IN_SECONDS );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'shop' ) );
		exit;
	}

	/**
	 * Return enabled currencies.
	 *
	 * @return array<string,string>
	 */
	private function get_enabled_currencies() {
		$currency           = get_option( 'woocommerce_currency', 'USD' );
		$all_currencies     = get_woocommerce_currencies();
		$enabled_currencies = array( $currency => $all_currencies[ $currency ] ?? $currency );

		if ( function_exists( 'get_woocommerce_currency_symbols' ) ) {
			foreach ( $all_currencies as $code => $label ) {
				$enabled_currencies[ $code ] = $label;
			}
		}

		return $enabled_currencies;
	}
}

$pcfw_plugin = new Preferred_Currency_For_WooCommerce();
add_action( 'plugins_loaded', array( $pcfw_plugin, 'init' ) );
