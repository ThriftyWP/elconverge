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
 * The token response class.
 *
 * @since 2.0.0
 */
class WC_Elavon_Converge_API_Token_Response extends WC_Elavon_Converge_API_Credit_Card_Transaction_Response implements Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response {


	/**
	 * Determines if the transaction was approved.
	 *
	 * @since 2.0.3
	 * @return bool
	 */
	public function transaction_approved() {

		$approved = parent::transaction_approved() && 'SUCCESS' === $this->get_token_response();

		// if generating a new payment token, check it was added to the vault successfully
		// apparently all other status codes can be a-okay regardless :(
		if ( 'GETTOKEN' === $this->get_transaction_type() ) {
			$approved = $approved && $this->token_added();
		}

		return $approved;
	}


	/**
	 * Gets the response message.
	 *
	 * @since 2.0.3
	 * @return string
	 */
	public function get_status_message() {

		$message = $this->get_token_response();

		// if attempting to add a token and it failed, use that error message
		if ( 'GETTOKEN' === $this->get_transaction_type() && ! $this->token_added() ) {
			$message = $this->get_add_token_response();
		}

		return $message;
	}


	/**
	 * Gets the payment tokenization response.
	 *
	 * @since 2.0.3
	 * @return string
	 */
	public function get_token_response() {

		return $this->ssl_token_response;
	}


	/**
	 * Gets the "add to card manager" tokenization response.
	 *
	 * This supplements the `\WC_Elavon_Converge_API_Token_Response::get_token_response()`
	 * since the tokenization can be successful but the "add" action can fail
	 * due to merchant account misconfiguration, etc...
	 *
	 * @since 2.0.3
	 * @return string
	 */
	public function get_add_token_response() {

		return $this->ssl_add_token_response;
	}


	/**
	 * Determines if the token was successfully added for new token requests.
	 *
	 * @since 2.0.3
	 * @return bool
	 */
	public function token_added() {

		return in_array( $this->get_add_token_response(), array( 'Card Added', 'Card Updated' ), true );
	}


	/**
	 * Gets the transaction type.
	 *
	 * @since 2.0.3
	 * @return string
	 */
	public function get_transaction_type() {

		return $this->ssl_transaction_type;
	}


}
