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
 * The base transaction response class.
 *
 * @since 2.0.0
 */
abstract class WC_Elavon_Converge_API_Transaction_Response extends WC_Elavon_Converge_API_Response implements Framework\SV_WC_Payment_Gateway_API_Response {


	/**
	 * Determines if the transaction was successful.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function transaction_approved() {

		return '0' === $this->get_status_code();
	}


	/**
	 * Determines if the transaction was held.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function transaction_held() {

		return 'CALL AUTH CENTER' === $this->get_status_message();
	}


	/**
	 * Gets the response status message.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_status_message() {

		return $this->ssl_result_message;
	}


	/**
	 * Gets the response status code.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_status_code() {

		return $this->ssl_result;
	}


	/**
	 * Gets the response transaction ID.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_transaction_id() {

		return $this->ssl_txn_id;
	}


	/**
	 * Gets the response transaction auth code.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_authorization_code() {

		return $this->ssl_approval_code;
	}


	/**
	 * Builds the payment token if provided.
	 *
	 * @since 2.0.0
	 * @return \WC_Gateway_Elavon_Converge_Token
	 */
	public function get_payment_token() {

		$token = null;

		if ( $token_id = $this->ssl_token ) {
			$token = new \WC_Gateway_Elavon_Converge_Token( $token_id, $this->get_payment_token_data() );
		}

		return $token;
	}


	/**
	 * Gets the data necessary to build a payment token from the response.
	 *
	 * Extending transaction classes can override this to return data based on their payment type.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_payment_token_data() {

		return array();
	}


	/**
	 * Gets the customer-facing result message.
	 *
	 * @see Framework\SV_WC_Payment_Gateway_API_Response_Message_Helper
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $message_id optional message ID to get a message for
	 * @return string
	 */
	public function get_user_message( $message_id = null ) {

		$helper = new Framework\SV_WC_Payment_Gateway_API_Response_Message_Helper;

		if ( null !== $message_id ) {
			return $helper->get_user_message( $message_id );
		}

		switch ( $this->get_status_message() ) {

			case 'DECLINED CVV2':
				$message_id = 'csc_mismatch';
			break;

			case 'DECLINED':
			case 'DECLINED T4':
				$message_id = 'decline';
			break;

			case 'EXPIRED CARD':
				$message_id = 'card_expired';
			break;

			case 'INVALID CARD':
			case 'INVALID CAVV':
				$message_id = 'card_declined';
			break;

			case 'INVLD R/T NBR':
				$message_id = 'bank_aba_invalid';
			break;

			default :
				$message_id = null;
			break;
		}

		return $helper->get_user_message( $message_id );
	}


}
