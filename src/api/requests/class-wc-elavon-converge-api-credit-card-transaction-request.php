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
 * The credit card transaction request class.
 *
 * @since 2.0.0
 */
class WC_Elavon_Converge_API_Credit_Card_Transaction_Request extends WC_Elavon_Converge_API_Transaction_Request {


	/**
	 * Creates a card authorization transaction.
	 *
	 * @since 2.0.0
	 */
	public function create_authorization() {

		$this->transaction_type = \WC_Elavon_Converge_API::TRANSACTION_TYPE_AUTHORIZATION;

		$this->create_transaction();
	}


	/**
	 * Creates a card charge transaction.
	 *
	 * @since 2.0.0
	 */
	public function create_charge() {

		$this->transaction_type = \WC_Elavon_Converge_API::TRANSACTION_TYPE_CHARGE;

		$this->create_transaction();
	}


	/**
	 * Creates a card transaction.
	 *
	 * @since 2.0.0
	 */
	protected function create_transaction() {

		parent::create_transaction();

		$customer_code = preg_replace( '/[^a-zA-Z0-9]/', '', $this->get_order()->get_order_number() );
		$customer_code = Framework\SV_WC_Helper::str_truncate( $customer_code, 17, '' );

		// even though Elavon says this is optional, apparently it's secretly required, so keep this
		$this->request_data['ssl_customer_code'] = $customer_code;

		// add card details when the card is not saved
		if ( ! isset( $this->request_data['ssl_token'] ) && isset( $this->get_order()->payment->account_number ) ) {
			$this->request_data['ssl_card_number']        = $this->get_order()->payment->account_number;
			$this->request_data['ssl_exp_date']           = $this->get_order()->payment->exp_month . $this->get_order()->payment->exp_year;
		}

		// add the CSC number, if available
		if ( isset( $this->get_order()->payment->csc ) ) {
			$this->request_data['ssl_cvv2cvc2_indicator'] = '1';
			$this->request_data['ssl_cvv2cvc2']           = $this->get_order()->payment->csc;
		} else {
			$this->request_data['ssl_cvv2cvc2_indicator'] = '0';
		}

		// if enabled, add the order currency for multi-currency conversion
		if ( $this->get_gateway()->is_multi_currency_required() ) {
			$this->request_data['ssl_transaction_currency'] = $this->get_order()->get_currency();
		}
	}


	/**
	 * Captures a previously authorized transaction.
	 *
	 * @since 2.0.0
	 */
	public function create_capture() {

		$this->transaction_type = 'cccomplete';

		$this->request_data['ssl_txn_id']        = $this->get_order()->capture->trans_id;
		$this->request_data['ssl_amount']        = $this->get_order()->capture->amount;
		$this->request_data['ssl_cardholder_ip'] = $this->get_order()->get_customer_ip_address( 'edit' );
	}


	/**
	 * Refunds a transaction.
	 *
	 * @since 2.0.0
	 *
	 * @throws Framework\SV_WC_Payment_Gateway_Exception if refunding a multi-currency order with no associated card token
	 */
	public function create_refund() {

		// if this was a multi-currency order, use cccredit transaction type
		if ( $this->is_multi_currency() ) {

			if ( ! $this->get_order()->refund->token ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( __( 'Multi-currency orders can only be refunded from WooCommerce if the customer paid with a stored card.', 'woocommerce-gateway-elavon' ) );
			}

			$card_type = Framework\SV_WC_Payment_Gateway_Helper::normalize_card_type( $this->get_order()->refund->card_type );

			// only certain card types are allowed for multi-currency transactions
			if ( ! in_array( $card_type, $this->get_gateway()->get_multi_currency_card_types() ) ) {

				$card_type_names = array_map( 'Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name', $this->get_gateway()->get_multi_currency_card_types() );

				throw new Framework\SV_WC_Payment_Gateway_Exception( sprintf(
					/** translators: Placeholders: %s - a list of credit card type names */
					__( 'Multi-currency orders can only be refunded from WooCommerce if the customer paid with a %s.', 'woocommerce-gateway-elavon' ),
					implode( ' or ', $card_type_names )
				) );
			}

			$this->transaction_type = 'cccredit';

			$this->request_data['ssl_token']                = $this->get_order()->refund->token;
			$this->request_data['ssl_transaction_currency'] = $this->get_order()->get_currency();

		} else {

			$this->transaction_type = 'ccreturn';

			$this->request_data['ssl_txn_id']  = $this->get_order()->refund->trans_id;
		}

		$this->request_data['ssl_amount' ]       = $this->get_order()->refund->amount;
		$this->request_data['ssl_card_present']  = 'N';
		$this->request_data['ssl_cardholder_ip'] = $this->get_order()->get_customer_ip_address( 'edit' );
	}


	/**
	 * Voids a transaction.
	 *
	 * @since 2.0.0
	 *
	 * @throws Framework\SV_WC_Payment_Gateway_Exception if voiding a multi-currency order
	 */
	public function create_void() {

		if ( $this->is_multi_currency() ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( __( 'Multi-currency orders cannot be voided from WooCommerce.', 'woocommerce-gateway-elavon' ) );
		}

		$this->transaction_type = $this->get_order()->refund->captured ? 'ccvoid' : 'ccdelete';

		$this->request_data['ssl_txn_id']        = $this->get_order()->refund->trans_id;
		$this->request_data['ssl_cardholder_ip'] = $this->get_order()->get_customer_ip_address( 'edit' );
	}


	/**
	 * Creates a token based on an order's payment details.
	 *
	 * @since 2.0.0
	 */
	public function tokenize_payment_method() {

		parent::tokenize_payment_method();

		$this->transaction_type = \WC_Elavon_Converge_API::TRANSACTION_TYPE_TOKENIZE;

		if ( isset( $this->get_order()->payment->account_number ) ) {
			$this->request_data['ssl_card_number'] = $this->get_order()->payment->account_number;
			$this->request_data['ssl_exp_date']    = $this->get_order()->payment->exp_month . $this->get_order()->payment->exp_year;
		}

		$this->request_data['ssl_cardholder_ip'] = $this->get_order()->get_customer_ip_address( 'edit' ) ? : \WC_Geolocation::get_ip_address();
	}


	/**
	 * Determines if the request is multi-currency.
	 *
	 * @since 2.1.1
	 *
	 * @return bool
	 */
	protected function is_multi_currency() {

		return $this->get_order() && $this->get_gateway()->is_multi_currency_enabled() && $this->get_order()->get_currency() !== $this->get_gateway()->get_multi_currency_terminal_currency();
	}


	/**
	 * Masks the credit card details before logging the request.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function to_string_safe() {

		$string = parent::to_string_safe();

		// mask the card number
		if ( preg_match( '/<ssl_card_number>(\d+)<\/ssl_card_number>/', $string, $matches ) && strlen( $matches[1] ) > 4 ) {
			$string = preg_replace( '/<ssl_card_number>\d+<\/ssl_card_number>/', '<ssl_card_number>' . substr( $matches[1], 0, 1 ) . str_repeat( '*', strlen( $matches[1] ) - 5 ) . substr( $matches[1], -4 ) . '</ssl_card_number>', $string );
		}

		// mask the CSC
		$string = preg_replace( '/<ssl_cvv2cvc2>\d+<\/ssl_cvv2cvc2>/', '<ssl_cvv2cvc2>***</ssl_cvv2cvc2>', $string );

		return $string;
	}


	/**
	 * Gets the request auth data.
	 *
	 * @since 2.6.8
	 *
	 * @return array
	 */
	protected function get_auth_data() {

		$auth_data = parent::get_auth_data();

		// Elavon has recommended not to send the ssl_vendor_id param in the gettoken requests at all
		if ( \WC_Elavon_Converge_API::TRANSACTION_TYPE_TOKENIZE === $this->transaction_type ) {
			unset( $auth_data['ssl_vendor_id'] );
		}

		return $auth_data;
	}


}
