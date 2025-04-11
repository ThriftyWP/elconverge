<?php
/**
 * WooCommerce Elavon Converge
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Elavon Converge to newer
 * versions in the future. If you wish to customize WooCommerce Elavon Converge for your
 * needs please refer to http://docs.woocommerce.com/document/elavon-vm-payment-gateway/
 *
 * @package     WC-Elavon/Gateway
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2024, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;

/**
 * Elavon payment form class.
 *
 * @since 2.0.0
 *
 * @method \WC_Gateway_Elavon_Converge get_gateway()
 */
class WC_Elavon_Converge_Payment_Form extends Framework\SV_WC_Payment_Gateway_Payment_Form {


	/**
	 * Gets default credit card form fields.
	 *
	 * @since 2.8.0
	 *
	 * @return array credit card form fields
	 */
	protected function get_credit_card_fields() {

		$credit_card_fields = parent::get_credit_card_fields();

		// removes card fields from payment form
		unset(
			$credit_card_fields['card-number']['name'],
			$credit_card_fields['card-expiry']['name'],
			$credit_card_fields['card-csc']['name']
		);

		return $credit_card_fields;
	}


	/**
	 * Gets the JS handler class name.
	 *
	 * @since 2.6.6
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Elavon_Payment_Form_Handler';
	}


	/**
	 * Gets the JS args for the payment form handler.
	 *
	 * @since 2.8.0
	 *
	 * @return array
	 */
	protected function get_js_handler_args() {

		$args = array_merge_recursive( parent::get_js_handler_args(), [
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'debug_mode' => $this->get_gateway()->debug_log(),
			'i18n'       => [
				'general_error' => __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-gateway-elavon' ),
				'terms_and_conditions_error' => __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-gateway-elavon' ),
			],
		] );

		$args = array_merge_recursive( $args, $this->get_gateway()->get_checkout_js_handler_args() );

		$args['is_checkout_js_enabled'] = true;
		$args['log_event_script_nonce'] = wp_create_nonce( 'wc-' . $this->get_id_dasherized() . '-log-script-event' );

		$args['is_terms_and_conditions_enabled'] = wc_terms_and_conditions_checkbox_enabled();

		return $args;
	}


	/**
	 * Render a test amount input field that can be used to override the order total
	 * when using the gateway in demo mode. The order total can then be set to
	 * various amounts to simulate various authorization/settlement responses
	 *
	 * @since 2.0.0
	 */
	public function render_payment_form_description() {

		parent::render_payment_form_description();

		if ( $this->get_gateway()->is_test_environment() && ! is_add_payment_method_page() ) {

			$id = 'wc-' . $this->get_gateway()->get_id_dasherized() . '-test-amount';

			?>
			<p class="form-row">
				<label for="<?php echo sanitize_html_class( $id ); ?>"><?php esc_html_e( 'Test Amount', 'woocommerce-gateway-elavon' ); ?></label>
				<input type="text" id="<?php echo sanitize_html_class( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" />
				<span style="font-size: 10px; display: inline-block;" class="description"><?php esc_html_e( 'Enter a test amount to trigger a specific error response, or leave blank to use the order total.', 'woocommerce-gateway-elavon' ); ?></span>
			</p>
			<?php
		}
	}


	/**
	 * Renders the payment fields (e.g. account number, expiry, etc).
	 *
	 * Overwrites the framework method to render additional Checkout.js fields.
	 *
	 * @since 2.8.0
	 */
	public function render_payment_fields() {

		parent::render_payment_fields();

		$this->render_checkout_js_fields();
	}


	/**
	 * Renders the Checkout.js hidden fields.
	 *
	 * @since 2.8.0
	 */
	protected function render_checkout_js_fields() {

		$transaction_id_field = 'wc-' . $this->get_gateway()->get_id_dasherized() . '-checkout-js-transaction-id';
		$token_field          = 'wc-' . $this->get_gateway()->get_id_dasherized() . '-checkout-js-token';

		?>
			<input type="hidden"
			       id="<?php echo sanitize_html_class( $transaction_id_field ); ?>"
			       name="<?php echo esc_attr( $transaction_id_field ); ?>" />

			<input type="hidden"
			       id="<?php echo sanitize_html_class( $token_field ); ?>"
			       name="<?php echo esc_attr( $token_field ); ?>" />
		<?php
	}


}
