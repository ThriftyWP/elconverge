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
 * The token request class.
 *
 * @since 2.0.0
 */
class WC_Elavon_Converge_API_Token_Request extends WC_Elavon_Converge_API_Request {


	/**
	 * Updates a token used on an order with billing data from that order.
	 *
	 * @since 2.3.2
	 *
	 * @param \WC_Order $order
	 */
	public function update_token( $order ) {

		$this->transaction_type = 'ccupdatetoken';

		$data = array_merge( [
			'ssl_token' => $order->payment->token,
		], $this->get_customer_data_from_order( $order ) );

		// clean any extra special characters to avoid API issues
		$data = $this->remove_special_characters( $data );

		$this->request_data = $data;
	}


	/**
	 * Deletes a payment token.
	 *
	 * @since 2.0.0
	 *
	 * @param string $token_id the token ID to delete
	 */
	public function delete_token( $token_id ) {

		$this->transaction_type = 'ccdeletetoken';
		$this->request_data     = array(
			'ssl_token' => $token_id,
		);
	}


	/**
	 * Gets information from a stored token.
	 *
	 * @since 2.8.0
	 *
	 * @param string $token_id the token ID to query
	 */
	public function query_token( $token_id ) {

		$this->transaction_type = 'ccquerytoken';
		$this->request_data     = [
			'ssl_token' => $token_id,
		];
	}


}
