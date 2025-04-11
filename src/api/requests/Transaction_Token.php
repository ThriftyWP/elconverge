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

namespace SkyVerge\WooCommerce\Elavon_Converge\API\Requests;

defined( 'ABSPATH' ) or exit;

use WC_Elavon_Converge_API_Request;

/**
 * The transaction token request class.
 *
 * @since 2.8.0
 */
class Transaction_Token extends WC_Elavon_Converge_API_Request {


	/**
	 * Constructs the request.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Gateway_Elavon_Converge $gateway the gateway object associated with this request
	 * @param string $transaction_type the transaction type
	 * @param string $amount the amount
	 * @param \WC_Order|null $order the order object associated with this request, if available
	 */
	public function __construct( \WC_Gateway_Elavon_Converge $gateway, $transaction_type, $amount, \WC_Order $order = null ) {

		parent::__construct( $gateway, $order );

		$this->path             = '/transaction_token';
		$this->transaction_type = $transaction_type;
		$this->request_data     = [
			'ssl_amount' => $amount,
		];
	}


	/**
	 * Gets the request transaction data.
	 *
	 * @since 2.8.0
	 *
	 * @return array
	 */
	public function get_data() {

		$full_data = parent::get_data();

		return isset( $full_data['txn'] ) ? $full_data['txn'] : [];
	}


	/**
	 * Converts the request data to a form-encoded string instead of XML.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	public function to_string() {

		return http_build_query( $this->get_data() );
	}


	/**
	 * Converts the request data to a string safe for broadcasting with sensitive data removed.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	public function to_string_safe() {

		$data = $this->get_data();

		if ( isset( $data['ssl_pin'] ) ) {
			$data['ssl_pin'] = str_repeat( '*', strlen( $data['ssl_pin'] ) );
		}

		return print_r( $data, true );
	}


}
