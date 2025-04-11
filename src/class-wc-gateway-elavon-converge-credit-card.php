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

use SkyVerge\WooCommerce\Elavon_Converge\API\Responses\Transaction_Query;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;
use SkyVerge\WooCommerce\Elavon_Converge\Blocks\Credit_Card_Checkout_Block_Integration;

/**
 * The credit card gateway class.
 *
 * @since 2.0.0
 */
class WC_Gateway_Elavon_Converge_Credit_Card extends WC_Gateway_Elavon_Converge {


	/** @var string|null if multicurrency is enabled **/
	protected $multi_currency_enabled;

	/** @var string the configured terminal currency **/
	protected $multi_currency_terminal_currency;

	/** @var Credit_Card_Checkout_Block_Integration|null */
	protected ?Credit_Card_Checkout_Block_Integration $credit_card_checkout_block = null;


	/**
	 * Constructs the gateway.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Elavon_Converge::CREDIT_CARD_GATEWAY_ID,
			array(
				'method_title' => __( 'Elavon Converge Credit Card', 'woocommerce-gateway-elavon' ),
				'supports'     => array(
					self::FEATURE_CARD_TYPES,
					self::FEATURE_CREDIT_CARD_CHARGE,
					self::FEATURE_CREDIT_CARD_AUTHORIZATION,
					self::FEATURE_CREDIT_CARD_CAPTURE,
					self::FEATURE_REFUNDS,
					self::FEATURE_VOIDS,
					self::FEATURE_TOKENIZATION,
					self::FEATURE_ADD_PAYMENT_METHOD,
					self::FEATURE_TOKEN_EDITOR,
					self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,  // @since 2.2.0
				),
				'payment_type' => self::PAYMENT_TYPE_CREDIT_CARD,
			)
		);
	}


	/**
	 * Gets the checkout block integration instance.
	 *
	 * @since 2.14.0
	 *
	 * @return Credit_Card_Checkout_Block_Integration
	 */
	public function get_checkout_block_integration_instance(): ?Framework\Payment_Gateway\Blocks\Gateway_Checkout_Block_Integration {

		if ( null === $this->credit_card_checkout_block ) {

			require_once( $this->get_plugin()->get_plugin_path() . '/src/Blocks/Credit_Card_Checkout_Block_Integration.php' );

			$this->credit_card_checkout_block = new Credit_Card_Checkout_Block_Integration( $this->get_plugin(), $this );
		}

		return $this->credit_card_checkout_block;
	}


	/**
	 * Validates the provided Card Security Code, adding user error messages as needed.
	 *
	 * Overwritten to skip CSC validation with Checkout.js is enabled,
	 * because the CSC should never be submitted for Checkout.js transactions.
	 *
	 * @since 2.8.0
	 *
	 * @param string $csc the customer-provided card security code
	 * @return bool true if the card security code is valid, false otherwise
	 */
	protected function validate_csc( $csc ) {

		return true;
	}


	/**
	 * Overrides the standard transaction processing to cover this situation:
	 *
	 * For a tokenized transaction where the billing information entered does
	 * not match the billing information stored on the token -> update the token
	 * prior to processing the transaction
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Direct::do_transaction()
	 *
	 * @since 2.3.2
	 *
	 * @param \WC_Order $order
	 * @return bool
	 * @throws Framework\SV_WC_Plugin_Exception network timeouts, etc
	 */
	protected function do_transaction( $order ) {

		// bail if not a tokenized transaction
		if ( empty( $order->payment->token ) ) {
			return parent::do_transaction( $order );
		}

		$token = $this->get_payment_tokens_handler()->get_token( $order->get_user_id(), $order->payment->token );

		if ( ! $token->billing_matches_order( $order ) ) {

			// does not match, update the existing payment profile
			$this->get_api()->update_tokenized_payment_method( $order );

			// update the token billing hash with the entered info
			$token->update_billing_hash( $order );

			// persist the token to user meta
			$this->get_payment_tokens_handler()->update_token( $order->get_user_id(), $token );
		}

		// continue processing
		return parent::do_transaction( $order );
	}


	/**
	 * Gets the form fields specific for this method.
	 *
	 * @since 2.0.0
	 * @see \WC_Gateway_Elavon_Converge::get_method_form_fields()
	 * @return array
	 */
	protected function get_method_form_fields() {

		$fields = parent::get_method_form_fields();

		$fields['multi_currency_enabled'] = [
			'title'       => __( 'Multi-Currency', 'woocommerce-gateway-elavon' ),
			'label'       => __( 'Enable Multi-Currency transactions.', 'woocommerce-gateway-elavon' ),
			'type'        => 'checkbox',
			'description' => sprintf(
				/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag */
				__( 'Visa &amp; MasterCard only. Note that you %1$smust enable%2$s Multi-Currency for your account by contacting your merchant terminal representative.', 'woocommerce-gateway-elavon' ),
				'<strong>', '</strong>'
			),
			'default' => 'no',
		];

		$woocommerce_currencies = get_woocommerce_currencies();
		$elavon_currencies      = array( 'USD', 'CAD' );

		$currency_options = array();

		foreach ( $elavon_currencies as $currency ) {
			$currency_options[ $currency ] = ! empty( $woocommerce_currencies[ $currency ] ) ? $woocommerce_currencies[ $currency ] : $currency;
		}

		$fields['multi_currency_terminal_currency'] = array(
			'title'    => __( 'Merchant Terminal Currency', 'woocommerce-gateway-elavon' ),
			'desc_tip' => __( 'The currency in which you accept settled payments.', 'woocommerce-gateway-elavon' ),
			'type'     => 'select',
			'options'  => $currency_options,
			'default'  => current( $currency_options ),
		);

		return $fields;
	}


	/**
	 * Display settings page with some additional javascript for hiding conditional fields
	 *
	 * @since 1.0.0
	 * @see WC_Settings_API::admin_options()
	 */
	public function admin_options() {

		parent::admin_options();

		?>
		<style type="text/css">.nowrap { white-space: nowrap; }</style>
		<?php

		// add inline javascript to show/hide any shared settings fields as needed
		ob_start();

		?>
		( function( $ ) {

			$( '#woocommerce_<?php echo $this->get_id(); ?>_multi_currency_enabled' ).change( function() {

				var enabled          = $( this ).is( ':checked' );
				var currency_setting = $( '#woocommerce_<?php echo $this->get_id(); ?>_multi_currency_terminal_currency' );

				if ( enabled ) {
					$( currency_setting ).closest( 'tr' ).show();
				} else {
					$( currency_setting ).closest( 'tr' ).hide();
				}

			} ).change();

		} ) ( jQuery );
		<?php

		wc_enqueue_js( ob_get_clean() );

	}


	/**
	 * Adds refund information as class members of WC_Order instance for use in refund transactions.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_order_for_refund()
	 * @return WC_Order|WP_Error the order object with refund information attached or WP_Error on failure
	 */
	protected function get_order_for_refund( $order, $amount, $reason ) {

		$order = parent::get_order_for_refund( $order, $amount, $reason );

		// check whether the charge has already been captured by this gateway
		$order->refund->captured = 'yes' === $this->get_order_meta( $order, 'charge_captured' );

		// get the payment token associated with the original order, if any
		$order->refund->token = $this->get_order_meta( $order, 'payment_token' );

		$order->refund->card_type = $this->get_order_meta( $order, 'card_type' );

		return $order;
	}


	/**
	 * Adds an order notice to held orders that require voice authorization.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway::mark_order_as_held()
	 */
	public function mark_order_as_held( $order, $message, $response = null ) {

		parent::mark_order_as_held( $order, $message, $response );

		if ( $response && 'CALL AUTH CENTER' === $response->get_status_message() ) {

			// if this was an authorization, mark as invalid for capture
			if ( $this->perform_credit_card_authorization( $order ) ) {
				$this->update_order_meta( $order, 'auth_can_be_captured', 'no' );
			}

			$order->add_order_note( __( 'Voice authorization required to complete transaction, please call your merchant account.', 'woocommerce-gateway-elavon' ) );
		}
	}


	/**
	 * Returns the Payment Tokens Handler custom class instance.
	 *
	 * @since 2.3.2
	 *
	 * @return \WC_Gateway_Elavon_Converge_Tokens_Handler
	 */
	protected function build_payment_tokens_handler() {

		return new WC_Gateway_Elavon_Converge_Tokens_Handler( $this );
	}


	/**
	 * Gets the JS payment token response.
	 *
	 * @since 2.8.0
	 *
	 * @return \WC_Elavon_Converge_API_Token_Response the response object
	 * @throws Framework\SV_WC_API_Exception
	 */
	public function get_checkout_js_payment_token_response() {

		$token_id = $this->get_checkout_js_token();

		return ! empty( $token_id ) ? $this->get_api()->get_tokenized_payment_method( $token_id ) : null;
	}


	/**
	 * Adds the payment method to the customer's account.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response $response response object
	 * @return array result with success/error message and request status (success/failure)
	 * @throws Framework\SV_WC_Plugin_Exception
	 */
	protected function do_add_payment_method_transaction( \WC_Order $order, Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response $response = null ) {

		return parent::do_add_payment_method_transaction( $order, $this->get_checkout_js_payment_token_response() );
	}


	/** Getter methods ******************************************************/


	/**
	 * Gets the enabled card types.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_card_types() {

		return $this->is_multi_currency_required() ? $this->get_multi_currency_card_types() : parent::get_card_types();
	}


	/**
	 * Gets the card types that support multi-currency.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_multi_currency_card_types() {

		return array(
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA,
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD,
		);
	}


	/**
	 * Gets the merchant terminal currency.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_multi_currency_terminal_currency() {

		return $this->multi_currency_terminal_currency;
	}


	/**
	 * Gets the payment form field defaults.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_payment_method_defaults() {

		$defaults = parent::get_payment_method_defaults();

		if ( $this->is_test_environment() ) {
			$defaults['account-number'] = '4000000000000002';
		}

		/**
		 * Filters the default payment form values.
		 *
		 * @since 2.2.0
		 *
		 * @param string[] $defaults payment form default values
		 * @param \WC_Gateway_Elavon_Converge_Credit_Card $gateway the gateway instance
		 */
		return apply_filters( 'woocommerce_elavon_credit_card_default_values', $defaults, $this );
	}


	/**
	 * Determines if the gateway is properly configured to perform transactions.
	 *
	 * @see \WC_Gateway_Elavon_Converge::is_configured()
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_configured() {

		$is_configured = parent::is_configured();

		// multi-currency support is required but not enabled
		if ( $this->is_multi_currency_required() && ! $this->is_multi_currency_enabled() ) {
			$is_configured = false;
		}

		return $is_configured;
	}


	/**
	 * Determines if multi-currency support is required.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_multi_currency_required() {

		// only for non-USD/CAD stores
		$required = $this->is_multi_currency_enabled() && $this->get_payment_currency() !== $this->get_multi_currency_terminal_currency();

		/**
		 * Filters whether multi-currency support is required.
		 *
		 * @since 2.0.0
		 * @param bool $required
		 */
		return (bool) apply_filters( 'wc_' . $this->get_id() . '_multi_currency_required', $required );
	}


	/**
	 * Determines if multi-currency support is enabled.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_multi_currency_enabled() {

		/**
		 * Filters whether multi-currency support is enabled.
		 *
		 * @since 2.0.0
		 * @param bool $enabled
		 */
		return (bool) apply_filters( 'wc_' . $this->get_id() . '_multi_currency_enabled', 'yes' === $this->multi_currency_enabled );
	}


	/**
	 * Returns true if the posted credit card fields are valid, false otherwise.
	 *
	 * @since 2.2.0
	 *
	 * @param bool $is_valid true if the fields are valid, false otherwise
	 * @return bool true if the fields are valid, false otherwise
	 */
	protected function validate_credit_card_fields( $is_valid ) {

		// TODO: can we validate in the frontend whether the card type is one of the accepted multi currency card types? {WV 2020-11-10}
		return $this->validate_checkout_js_fields( $is_valid );
	}


	/**
	 * Determines if tokenization takes place prior to transaction processing.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public function tokenize_before_sale() {

		return true;
	}


	/**
	 * Determines if tokenization takes place after a transaction request.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public function tokenize_after_sale() {

		return false;
	}


	/**
	 * Gets the Checkout.js transaction type.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order order object
	 * @param bool $tokenize_payment_method
	 * @return string
	 */
	protected function get_checkout_js_transaction_type( \WC_Order $order, $tokenize_payment_method = false ) {

		if ( $tokenize_payment_method ) {
			return \WC_Elavon_Converge_API::TRANSACTION_TYPE_TOKENIZE;
		}

		if ( $this->perform_credit_card_charge( $order ) ) {
			return \WC_Elavon_Converge_API::TRANSACTION_TYPE_CHARGE;
		}

		return \WC_Elavon_Converge_API::TRANSACTION_TYPE_AUTHORIZATION;
	}


	/*
	 * If applicable, retrieves a Checkout.js transaction response and adds the relevant information to the order prior to performing a credit card transaction.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order the order object
	 * @param SV_WC_Payment_Gateway_API_Response|null $response optional credit card transaction response
	 * @return SV_WC_Payment_Gateway_API_Response the response
	 * @throws SV_WC_Plugin_Exception network timeouts, etc.
	 */
	protected function do_credit_card_transaction( $order, $response = null ) {

		if ( $this->should_get_checkout_js_transaction_response() ) {

			$response = $this->do_checkout_js_transaction( $order );
			$order    = $this->add_checkout_js_payment_data_to_order( $order, $response );
		}

		return parent::do_credit_card_transaction( $order, $response );
	}


	/**
	 * Builds a Checkout.js transaction request.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order order object
	 * @param string $transaction_type the transaction type
	 * @return \WC_Elavon_Converge_API_Credit_Card_Transaction_Request
	 */
	protected function build_checkout_js_transaction_request( $order, $transaction_type ) {

		$api = $this->get_api();

		// add gateway payment and transaction data to order and set it as the API order
		$api->set_order( $this->get_order( $order ) );

		/** @var \WC_Elavon_Converge_API_Credit_Card_Transaction_Request */
		$request = $api->get_new_request();

		if ( WC_Elavon_Converge_API::TRANSACTION_TYPE_TOKENIZE === $transaction_type ) {
			$request->tokenize_payment_method();
		} elseif ( WC_Elavon_Converge_API::TRANSACTION_TYPE_AUTHORIZATION === $transaction_type ) {
			$request->create_authorization();
		} elseif ( WC_Elavon_Converge_API::TRANSACTION_TYPE_CHARGE === $transaction_type ) {
			$request->create_charge();
		}

		return $request;
	}


	/**
	 * Adds Checkout.js payment data to the order.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Transaction_Query $response the transaction query response
	 * @return \WC_Order $order the order object
	 */
	protected function add_checkout_js_payment_data_to_order( WC_Order $order, Transaction_Query $response ) {

		$order->payment->account_number = $response->get_last_four();
		$order->payment->card_type      = $response->get_card_type();
		$order->payment->exp_month      = $response->get_exp_month();
		$order->payment->exp_year       = substr( $response->get_exp_year(), -2 );

		return $order;
	}


}
