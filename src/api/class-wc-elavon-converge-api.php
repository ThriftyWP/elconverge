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

use SkyVerge\WooCommerce\Elavon_Converge\API\Requests;
use SkyVerge\WooCommerce\Elavon_Converge\API\Responses;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;

/**
 * The Converge API class.
 *
 * @since 2.0.0
 */
class WC_Elavon_Converge_API extends Framework\SV_WC_API_Base implements Framework\SV_WC_Payment_Gateway_API {


	/** @var string Checkout.js tokenize transaction type */
	const TRANSACTION_TYPE_TOKENIZE = 'ccgettoken';

	/** @var string Checkout.js charge transaction type */
	const TRANSACTION_TYPE_CHARGE = 'ccsale';

	/** @var string Checkout.js authorization transaction type */
	const TRANSACTION_TYPE_AUTHORIZATION = 'ccauthonly';

	/** @var string Checkout.js e-check transaction type */
	const TRANSACTION_TYPE_ECHECK = 'ecspurchase';

	/** @var \WC_Gateway_Elavon_Converge the gateway object */
	protected $gateway;

	/** @var \WC_Order the order object */
	protected $order;


	/**
	 * Constructs the base API.
	 *
	 * @since 2.0.0
	 * @param \WC_Gateway_Elavon_Converge $gateway the gateway object
	 */
	public function __construct( WC_Gateway_Elavon_Converge $gateway ) {

		$this->gateway = $gateway;

		$this->request_uri = $this->get_api_url();
		$this->set_request_header( 'Referer', get_site_url() );

		// force IPV4 for all API requests
		add_action( 'http_api_curl', [ $this, 'set_ip_version' ], 10, 3 );
	}


	/**
	 * Gets the URL for Elavon Converge API requests.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	private function get_api_url() {

		if ( $this->get_gateway()->is_test_environment() ) {
			return 'https://api.demo.convergepay.com/VirtualMerchantDemo/processxml.do';
		}

		return 'https://api.convergepay.com/VirtualMerchant/processxml.do';
	}


	/**
	 * Force IPV4 requests, due to support issues.
	 *
	 * @internal
	 *
	 * @since 2.6.9
	 *
	 * @param resource $handle the cURL handle returned by curl_init() (passed by reference)
	 * @param array $r the HTTP request arguments
	 * @param $url string the request URL
	 */
	public function set_ip_version( $handle, $r, $url ) {

		if ( false !== strpos( $url, 'convergepay' ) && defined( 'CURLOPT_IPRESOLVE' ) && defined( 'CURL_IPRESOLVE_V4' ) ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}
	}


	/**
	 * Performs a credit card authorization for a given order.
	 *
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @return Framework\SV_WC_Payment_Gateway_API_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function credit_card_authorization( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->create_authorization();

		return $this->perform_request( $request );
	}


	/**
	 * Performs a credit card charge for a given order.
	 *
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @return Framework\SV_WC_Payment_Gateway_API_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function credit_card_charge( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->create_charge();

		return $this->perform_request( $request );
	}


	/**
	 * Performs a credit card capture for a given authorized order.
	 *
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @return Framework\SV_WC_Payment_Gateway_API_Response credit card charge response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function credit_card_capture( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->create_capture();

		return $this->perform_request( $request );
	}


	/**
	 * Performs an eCheck debit (ACH transaction) for a given order.
	 *
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @return Framework\SV_WC_Payment_Gateway_API_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function check_debit( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->create_debit();

		return $this->perform_request( $request );
	}


	/**
	 * Performs a refund for a given order.
	 *
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @return Framework\SV_WC_Payment_Gateway_API_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function refund( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->create_refund();

		return $this->perform_request( $request );
	}


	/**
	 * Performs a void for a given order.
	 *
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @return Framework\SV_WC_Payment_Gateway_API_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function void( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->create_void();

		return $this->perform_request( $request );
	}


	/**
	 * Queries a transaction.
	 *
	 * @since 2.8.0
	 *
	 * @param string $transaction_id transaction ID
	 * @param \WC_Order|null $order optional order object
	 * @return Responses\Transaction_Query
	 * @throws Framework\SV_WC_API_Exception
	 */
	public function query_transaction( $transaction_id, $order = null ) {

		// set the order when passed manually, for example via currency-based credential swapping snippets
		if ( $order ) {
			$this->order = $order;
		}

		$request = new Requests\End_Day_Transaction( $this->get_gateway(), $this->get_order() );

		$request->query_transaction( $transaction_id );

		$this->set_response_handler( Responses\Transaction_Query::class );

		return $this->perform_request( $request );
	}


	/**
	 * Creates a payment token for a given order.
	 *
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @return Framework\SV_WC_Payment_Gateway_API_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function tokenize_payment_method( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->tokenize_payment_method();

		// this method uses the Credit Card request object but the Converge API
		// returns a standard token response
		$this->set_response_handler( 'WC_Elavon_Converge_API_Token_Response' );

		return $this->perform_request( $request );
	}


	/**
	 * Updates the tokenized payment method on an order with new data from that order.
	 *
	 * @since 2.3.2
	 *
	 * @param \WC_Order $order
	 * @return \WC_Elavon_Converge_API_Token_Response the response object
	 * @throws Framework\SV_WC_API_Exception
	 */
	public function update_tokenized_payment_method( WC_Order $order ) {

		$this->order = $order;
		$request     = $this->get_new_request( array(
			'type' => 'token',
		) );

		$request->update_token( $order );

		return $this->perform_request( $request );
	}


	/**
	 * Removes a tokenized payment method.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::supports_remove_tokenized_payment_method()
	 * @param string $token the payment method token
	 * @param string $customer_id the unique customer ID
	 * @return Framework\SV_WC_Payment_Gateway_API_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function remove_tokenized_payment_method( $token, $customer_id ) {

		$request = $this->get_new_request( array(
			'type' => 'token',
		) );

		$request->delete_token( $token );

		return $this->perform_request( $request );
	}


	/**
	 * Gets a transaction token for Hosted Payments.
	 *
	 * @since 2.8.0
	 *
	 * @param string $transaction_type one of the transaction types supported by Checkout.js
	 * @param float $amount the payment amount for the transaction
	 * @return Responses\Transaction_Token
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_hosted_payments_transaction_token( $transaction_type, $amount ) {

		$request = new Requests\Transaction_Token( $this->get_gateway(), $transaction_type, $amount, $this->get_order() );

		$this->request_uri = $this->get_hosted_payments_url();

		$this->set_response_handler( Responses\Transaction_Token::class );

		$response = $this->perform_request( $request );

		// change the URL for future requests
		$this->request_uri = $this->get_api_url();

		return $response;
	}


	/**
	 * Gets the URL for the Hosted Payments transaction token endpoint.
	 *
	 * See Checkout.js > Session Token Request (title) in Elavon Converge Integration Guide.
	 *
	 * @see https://developer.elavon.com/#/api/eb6e9106-0172-4305-bc5a-b3ebe832f823.rcosoomi/versions/5180a9f2-741b-439c-bced-5c84a822f39b.rcosoomi/documents/?converge-integration-guide/book/integration_methods/checkoutjs.html#session-token-request
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	private function get_hosted_payments_url() {

		if ( $this->get_gateway()->is_test_environment() ) {
			return 'https://api.demo.convergepay.com/hosted-payments';
		}

		return 'https://api.convergepay.com/hosted-payments';
	}


	/**
	 * Enables "remove tokenized payment method" support.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::remove_tokenized_payment_method()
	 * @return bool
	 */
	public function supports_remove_tokenized_payment_method() {

		return true;
	}


	/**
	 * Determines if this API supports updating tokenized payment methods.
	 *
	 * @see SV_WC_Payment_Gateway_API::update_tokenized_payment_method()
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public function supports_update_tokenized_payment_method() {

		return true;
	}


	/**
	 * Gets all tokenized payment methods for the customer.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::supports_get_tokenized_payment_methods()
	 * @param string $customer_id the unique customer ID
	 * @return Framework\SV_WC_API_Get_Tokenized_Payment_Methods_Response the response object
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_tokenized_payment_methods( $customer_id ) { }


	/**
	 * Enables support for "get tokenized payment methods".
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::get_tokenized_payment_methods()
	 * @return bool
	 */
	public function supports_get_tokenized_payment_methods() {

		return false;
	}


	/**
	 * Validates the response before parsing.
	 *
	 * @since 2.8.0
	 *
	 * @throws Framework\SV_WC_API_Exception
	 */
	protected function do_pre_parse_response_validation() {

		parent::do_pre_parse_response_validation();

		if ( $this->request instanceof Requests\Transaction_Token ) {

			if ( 200 !== $this->response_code ) {

				throw new Framework\SV_WC_API_Exception( __( "An error occurred while trying to get a transaction token for Checkout.js. Please make sure you have registered your server's IP address with Elavon and that the configured User ID belongs to a user with permission to request Hosted Payments transaction tokens.", 'woocommerce-gateway-elavon' ) );
			}
		}
	}


	/**
	 * Validates the response after parsing.
	 *
	 * @since 2.0.0
	 *
	 * @throws Framework\SV_WC_API_Exception
	 */
	protected function do_post_parse_response_validation() {

		parent::do_post_parse_response_validation();

		$response = $this->get_response();

		if ( $response->has_error() ) {
			throw new Framework\SV_WC_API_Exception( '[' . $response->get_error_code() . '] ' . $response->get_error_message(), (int) $response->get_error_code() );
		}

		// on occasion, we have seen transactions marked successful but containing invalid data
		if ( $response instanceof WC_Elavon_Converge_API_Credit_Card_Transaction_Response ) {

			$avs_avs_result = $response->get_avs_result();
			$transaction_id = $response->get_transaction_id();
			$approval_code  = $response->get_authorization_code();

			// @TODO should we provide a different error message here? {unfulvio 2021-06-09}
			if ( 'X' === $avs_avs_result && '123456' === $approval_code && '00000000-0000-0000-0000-00000000000' === $transaction_id ) {
				throw new Framework\SV_WC_API_Exception( '[' . $response->get_status_code() . '] ' . $response->get_user_message( 'error' ), (int) $response->get_status_code() );
			}
		}
	}


	/**
	 * Sets the order object used in API requests.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function set_order( \WC_Order $order ) {

		$this->order = $order;
	}


	/**
	 * Gets a new request object.
	 *
	 * @since 2.0.0
	 * @param array $args {
	 *     Optional. The request type arguments.
	 *
	 *     @type string $type         The desired request type
	 *     @type string $payment_type The payment type, if a transaction request
	 * }
	 * @return Framework\SV_WC_API_Request
	 * @throws Framework\SV_WC_API_Exception
	 */
	public function get_new_request( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'type'         => 'transaction',
			'payment_type' => $this->get_gateway()->get_payment_type(),
		) );

		switch ( $args['type'] ) {

			case 'transaction':
				$request = $this->get_new_transaction_request( $args['payment_type'] );
			break;

			case 'token':

				$request = new WC_Elavon_Converge_API_Token_Request( $this->get_gateway(), $this->get_order() );

				$this->set_response_handler( 'WC_Elavon_Converge_API_Token_Response' );

			break;

			default:
				throw new Framework\SV_WC_API_Exception( 'Invalid request type' );
		}

		return $request;
	}


	/**
	 * Gets a new transaction request object.
	 *
	 * @since 2.0.0
	 * @param string $payment_type Optional. The request payment type, either 'credit-card' or 'echeck'
	 * @return Framework\SV_WC_API_Request
	 * @throws Framework\SV_WC_API_Exception
	 */
	protected function get_new_transaction_request( $payment_type = '' ) {

		// an order is required for transaction requests
		if ( ! $this->get_order() ) {
			throw new Framework\SV_WC_API_Exception( 'Order is missing or invalid' );
		}

		switch ( $payment_type ) {

			// credit card transactions
			case 'credit-card':
				$request_class  = 'WC_Elavon_Converge_API_Credit_Card_Transaction_Request';
				$response_class = 'WC_Elavon_Converge_API_Credit_Card_Transaction_Response';
			break;

			// echeck transactions
			case 'echeck':
				$request_class  = 'WC_Elavon_Converge_API_eCheck_Transaction_Request';
				$response_class = 'WC_Elavon_Converge_API_eCheck_Transaction_Response';
			break;

			default:
				throw new Framework\SV_WC_API_Exception( 'Invalid payment type' );
		}

		$this->set_response_handler( $response_class );

		return new $request_class( $this->get_gateway(), $this->get_order() );
	}


	/**
	 * Gets the parsed response object for the request.
	 *
	 * Overridden primarily to provide the request object to the response
	 * classes, as the Converge API does not provide a card type in some cases.
	 *
	 * @since 2.0.2
	 * @see Framework\SV_WC_API_Base::get_parsed_response()
	 * @param string $raw_response_body
	 * @return \WC_Elavon_Converge_API_Response
	 */
	protected function get_parsed_response( $raw_response_body ) {

		$handler_class = $this->get_response_handler();

		return new $handler_class( $this->get_request(), $raw_response_body );
	}


	/**
	 * Get the ID for the API.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_api_id() {

		return $this->get_gateway()->get_id();
	}


	/**
	 * Gets the order object associated with the request, if any.
	 *
	 * @since 2.0.0
	 * @return \WC_Order|null
	 */
	public function get_order() {

		return $this->order;
	}


	/**
	 * Gets the gateway object associated with this API.
	 *
	 * @since 2.0.0
	 * @return \WC_Gateway_Elavon_Converge
	 */
	protected function get_gateway() {

		return $this->gateway;
	}


	/**
	 * Gets the plugin class instance associated with this API.
	 *
	 * @since 2.0.0
	 * @return Framework\SV_WC_Plugin
	 */
	protected function get_plugin() {

		return wc_elavon_converge();
	}


	/**
	 * Gets data for the given tokenized payment method.
	 *
	 * @since 2.8.0
	 *
	 * @param string $token_id the token ID to query
	 * @return \WC_Elavon_Converge_API_Token_Response the response object
	 * @throws Framework\SV_WC_API_Exception
	 */
	public function get_tokenized_payment_method( $token_id ) {

		$request = new \WC_Elavon_Converge_API_Token_Request( $this->get_gateway(), $this->get_order() );

		$request->query_token( $token_id );

		$this->set_response_handler( Responses\Token_Query::class );

		return $this->perform_request( $request );
	}


}
