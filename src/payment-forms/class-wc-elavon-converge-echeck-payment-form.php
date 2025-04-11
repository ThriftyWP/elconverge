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
 * eCheck payment form class.
 *
 * @since 2.0.0
 */
class WC_Elavon_Converge_eCheck_Payment_Form extends WC_Elavon_Converge_Payment_Form {


	/**
	 * Constructs the class.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway_Payment_Form::__construct
	 */
	public function __construct( $gateway ) {

		parent::__construct( $gateway );

		// displays the authorization terms required prior to processing ACH transactions
		add_action( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_end',   array( $this, 'render_authorization_terms' ) );
	}


	/**
	 * Gets the eCheck form fields.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_echeck_fields() {

		$fields = parent::get_echeck_fields();

		if ( isset( $fields['account-type'] ) ) {

			$fields['account-type']['options'] = array(
				'personal' => esc_html_x( 'Personal', 'account type', 'woocommerce-gateway-elavon' ),
				'business' => esc_html_x( 'Business', 'account type', 'woocommerce-gateway-elavon' ),
			);
		}

		/**
		 * Filters the eCheck gateway form fields.
		 *
		 * @since 2.0.0
		 * @param array $fields in the format supported by woocommerce_form_fields()
		 * @param Framework\SV_WC_Payment_Gateway_Payment_Form $form the payment form instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_fields', $fields, $this );
	}


	/**
	 * Displays the authorization terms required prior to processing ACH transactions.
	 *
	 * @since 2.0.0
	 */
	public function render_authorization_terms() {

		if ( is_checkout_pay_page() && $order = wc_get_order( $this->get_gateway()->get_checkout_pay_page_order_id() ) ) {
			$order_total = $order->get_total();
		} else {
			$order_total = WC()->cart->total;
		}

		$terms = str_replace( '{order_total}', wc_price( $order_total ), $this->get_gateway()->get_authorization_terms() );

		/**
		 * Filters the eCheck gateway authorization terms.
		 *
		 * @since 2.0.0
		 * @param string $terms
		 */
		$terms = apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_terms', $terms );

		echo '<div class="wc-' . sanitize_html_class( $this->get_gateway()->get_id_dasherized() ) . '-terms">';
			echo '<p>' . wp_kses_post( $terms ) . '</p>';
		echo '</div>';
	}


}
