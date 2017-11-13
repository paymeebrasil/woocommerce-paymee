<?php
/**
 * Admin options screen.
 *
 * @package WooCommerce_PayMee/Admin/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<h3><?php echo esc_html( $this->method_title ); ?></h3>

<?php
	if ( 'yes' == $this->get_option( 'enabled' ) ) {
		if ( ! $this->using_supported_currency() && ! class_exists( 'woocommerce_wpml' ) ) {
			include dirname( __FILE__ ) . '/html-notice-currency-not-supported.php';
		}

		if ( '' === $this->get_api_key() ) {
			include dirname( __FILE__ ) . '/html-notice-api-key-missing.php';
		}

		if ( '' === $this->get_api_token() ) {
			include dirname( __FILE__ ) . '/html-notice-api-token-missing.php';
		}
	}
?>

<?php echo wpautop( $this->method_description ); ?>

<table class="form-table">
	<?php $this->generate_settings_html(); ?>
</table>
