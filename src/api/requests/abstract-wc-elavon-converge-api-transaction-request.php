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
 * @package     WC-Elavon/API
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2024, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;

/**
 * The base transaction request class.
 *
 * Technically, all requests to the Converge API are "transaction" requests and require their own value
 * for `ssl_transaction_type`. However, in the context of this integration a transaction request
 * specifically deals with payment transactions.
 *
 * @since 2.0.0
 */
abstract class WC_Elavon_Converge_API_Transaction_Request extends WC_Elavon_Converge_API_Request {


	/**
	 * Creates the necessary data to perform a payment transaction.
	 *
	 * This is meant to be generic enough to use with any transaction type (credit card or echeck)
	 *
	 * @since 2.0.0
	 */
	protected function create_transaction() {

		$order = $this->get_order();

		$data = array(
			'ssl_invoice_number'   => $this->str_truncate( ltrim( $order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce-gateway-elavon' ) ), 25, '' ),
			'ssl_amount'           => $order->payment_total,
			'ssl_salestax'         => $order->get_total_tax(),
		);

		$data = array_merge( $data, $this->get_customer_data_from_order( $order ) );

		// clean any extra special characters to avoid API issues
		$data = $this->remove_special_characters( $data );

		if ( isset( $order->payment->token ) ) {
			$data['ssl_token'] = $order->payment->token;
		}

		$this->request_data = $data;
	}


	/**
	 * Creates a token based on an order's payment details.
	 *
	 * @since 2.0.0
	 */
	public function tokenize_payment_method() {

		$order = $this->get_order();

		$data = [
			'ssl_first_name'  => $this->str_truncate( $order->get_billing_first_name( 'edit' ), 20 ),
			'ssl_last_name'   => $this->str_truncate( $order->get_billing_last_name( 'edit' ), 30 ),
			'ssl_avs_address' => $this->str_truncate( $order->get_billing_address_1( 'edit' ), 30 ),
			'ssl_avs_zip'     => $this->str_truncate( $order->get_billing_postcode( 'edit' ), 9, '' ),
			'ssl_add_token'   => 'Y',
		];

		// clean any extra special characters to avoid API issues
		$data = $this->remove_special_characters( $data );

		$this->request_data = $data;
	}


	/**
	 * Gets the data for a Checkout.js transaction.
	 *
	 * Returns all the data for this request object after removing the auth data.
	 *
	 * @since 2.8.0
	 *
	 * @return array
	 */
	public function get_checkout_js_data() {

		$transaction_data = $this->get_data();

		if ( ! isset( $transaction_data[ $this->get_root_element() ] ) ) {
			return [];
		}

		return array_merge(
			$this->remove_auth_data( $transaction_data[ $this->get_root_element() ] ),
			[
				'ssl_description' => $this->get_order()->get_id(),
			]
		);
	}


	/**
	 * Removes auth data from the transaction data.
	 *
	 * This method keeps the ssl_transaction_type entry that is registered as auth data but is transaction data.
	 *
	 * @since 2.8.0
	 */
	protected function remove_auth_data( $data ) {

		foreach ( array_keys( $this->get_auth_data() ) as $key ) {

			if ( 'ssl_transaction_type' !== $key ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}


}
