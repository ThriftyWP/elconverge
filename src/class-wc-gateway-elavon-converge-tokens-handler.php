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

use SkyVerge\WooCommerce\Elavon_Converge\API\Responses\Token_Query;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;

/**
 * Handles the payment tokenization related functionality.
 *
 * @since 2.3.2
 */
class WC_Gateway_Elavon_Converge_Tokens_Handler extends Framework\SV_WC_Payment_Gateway_Payment_Tokens_Handler {


	/**
	 * Tokenizes the current payment method and adds the standard transaction data to the order post record.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order order object
	 * @param Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response|null $response payment token API response
	 * @param string $environment_id optional environment ID, defaults to the current environment
	 * @return \WC_Order order object
	 * @throws Framework\SV_WC_Plugin_Exception on transaction failure
	 */
	public function create_token( \WC_Order $order, $response = null, $environment_id = null ) {

		$token_response = $this->get_gateway()->get_checkout_js_payment_token_response();

		if ( $token_response ) {

			$response = $token_response;

			$order = $this->add_checkout_js_payment_token_data_to_order( $order, $response );
		}

		return parent::create_token( $order, $response, $environment_id );
	}


	/**
	 * Adds Checkout.js payment token data to the order.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Token_Query $response the token query response
	 * @return \WC_Order
	 */
	protected function add_checkout_js_payment_token_data_to_order( \WC_Order $order, Token_Query $response ) {

		$order->payment->account_number = $response->get_account_number();
		$order->payment->exp_month      = $response->get_exp_month();
		$order->payment->exp_year       = substr( $response->get_exp_year(), -2 );

		return $order;
	}


	/**
	 * Returns a custom payment token class instance.
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Payment_Tokens_Handler::build_token()
	 *
	 * @since 2.3.2
	 *
	 * @param string $token payment token
	 * @param \WC_Payment_Token|array $data {
	 *     Payment token data.
	 *
	 *     @type bool   $default   Optional. Indicates this is the default payment token
	 *     @type string $type      Payment type. Either 'credit_card' or 'check'
	 *     @type string $last_four Last four digits of account number
	 *     @type string $card_type Credit card type (`visa`, `mc`, `amex`, `disc`, `diners`, `jcb`) or `echeck`
	 *     @type string $exp_month Optional. Expiration month (credit card only)
	 *     @type string $exp_year  Optional. Expiration year (credit card only)
	 * }
	 * @return \WC_Gateway_Elavon_Converge_Token
	 */
	public function build_token( $token, $data ) {

		return new WC_Gateway_Elavon_Converge_Token( $token, $data );
	}


	/**
	 * Overrides the default remove_token to allow smarter handling of token errors.
	 *
	 * This is copy-pasted from Framework\SV_WC_Payment_Gateway_Payment_Tokens_Handler
	 * in framework v5, and should be removed/refactored when upgrading this plugin to v5.
	 * TODO: Remove/refactor this function when upgrading to framework v5 {JB 2018-06-02}
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Payment_Tokens_Handler::remove_token()
	 *
	 * @since 2.3.2
	 *
	 * @param int $user_id user identifier
	 * @param Framework\SV_WC_Payment_Gateway_Payment_Token|string $token the payment token to delete
	 * @param string $environment_id optional environment id, defaults to plugin current environment
	 * @return bool|int false if not deleted, updated user meta ID if deleted
	 */
	public function remove_token( $user_id, $token, $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment_id();
		}

		// unknown token?
		if ( ! $this->user_has_token( $user_id, $token, $environment_id ) ) {
			return false;
		}

		// get the payment token object as needed
		if ( ! is_object( $token ) ) {
			$token = $this->get_token( $user_id, $token, $environment_id );
		}

		// for direct gateways that allow it, attempt to delete the token from the endpoint
		if ( $this->get_gateway()->get_api()->supports_remove_tokenized_payment_method() ) {

			try {

				$response = $this->get_gateway()->get_api()->remove_tokenized_payment_method( $token->get_id(), $this->get_gateway()->get_customer_id( $user_id, array( 'environment_id' => $environment_id ) ) );

				if ( ! $response->transaction_approved() ) {
					return false;
				}

			} catch ( Framework\SV_WC_Plugin_Exception $e ) {

				if ( $this->get_gateway()->debug_log() ) {
					$this->get_gateway()->get_plugin()->log( $e->getMessage(), $this->get_gateway()->get_id() );
				}

				// TODO: Refactor to be able to use should_delete_token() instead of relying on the exception code when upgrading to FW v5 {JB 2018-06-03}
				if ( 5085 !== $e->getCode() ) {
					return false;
				}
			}
		}

		return $this->delete_token( $user_id, $token );
	}


	/**
	 * Adds the billing_hash attribute to the list of attributes to merge between local and remote tokens.
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Payment_Tokens_Handler::get_merge_attributes()
	 *
	 * @since 2.3.2
	 *
	 * @return array merge attributes
	 */
	protected function get_merge_attributes() {

		return array_merge( parent::get_merge_attributes(), array( 'billing_hash' ) );
	}


	/**
	 * Helper to get the billing data from an order for use in a token.
	 *
	 * @since 2.3.2
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	public static function get_billing_token_data_from_order( WC_Order $order ) {

		return array(
			'ssl_first_name'  => $order->get_billing_first_name( 'edit' ),
			'ssl_last_name'   => $order->get_billing_last_name( 'edit' ),
			'ssl_company'     => $order->get_billing_company( 'edit' ),
			'ssl_avs_address' => $order->get_billing_address_1( 'edit' ),
			'ssl_address2'    => $order->get_billing_address_2( 'edit' ),
			'ssl_city'        => $order->get_billing_city( 'edit' ),
			'ssl_state'       => $order->get_billing_state( 'edit' ),
			'ssl_country'     => $order->get_billing_country( 'edit' ),
			'ssl_avs_zip'     => $order->get_billing_postcode( 'edit' ),
			'ssl_phone'       => $order->get_billing_phone( 'edit' ),
			'ssl_email'       => $order->get_billing_email( 'edit' ),
		);
	}


}
