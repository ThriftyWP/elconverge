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
use SkyVerge\WooCommerce\Elavon_Converge\Blocks\Electronic_Check_Checkout_Block_Integration;

/**
 * The eCheck gateway class.
 *
 * @since 2.0.0
 */
class WC_Gateway_Elavon_Converge_eCheck extends WC_Gateway_Elavon_Converge {


	/** @var string the authorization terms */
	protected $authorization_terms = '';

	/** @var Electronic_Check_Checkout_Block_Integration|null */
	protected ?Electronic_Check_Checkout_Block_Integration $electronic_check_checkout_block_integration = null;


	/**
	 * Constructs the gateway.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Elavon_Converge::ECHECK_GATEWAY_ID,
			array(
				'method_title' => __( 'Elavon Converge eCheck', 'woocommerce-gateway-elavon' ),
				'payment_type' => self::PAYMENT_TYPE_ECHECK,
			)
		);
	}


	/**
	 * Gets the checkout block integration instance.
	 *
	 * @since 2.14.0
	 *
	 * @return Electronic_Check_Checkout_Block_Integration
	 */
	public function get_checkout_block_integration_instance(): ?Framework\Payment_Gateway\Blocks\Gateway_Checkout_Block_Integration {

		if ( null === $this->electronic_check_checkout_block_integration ) {

			require_once( $this->get_plugin()->get_plugin_path() . '/src/Blocks/Electronic_Check_Checkout_Block_Integration.php' );

			$this->electronic_check_checkout_block_integration = new Electronic_Check_Checkout_Block_Integration( $this->get_plugin(), $this );
		}

		return $this->electronic_check_checkout_block_integration;
	}


	/**
	 * Validate payment form fields.
	 *
	 * @since 2.0.0
	 * @see WC_Payment_Gateway::validate_fields()
	 * @return bool
	 */
	public function validate_fields() {

		$is_valid = parent::validate_fields();

		if ( 'business' === Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-account-type' ) && ! wc_get_order( absint( get_query_var( 'order-pay' ) ) )->get_billing_company( 'edit' ) ) {

			Framework\SV_WC_Helper::wc_add_notice( sprintf(
				/** translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag */
				__( '%1$sBilling Company%2$s is required for business eChecks', 'woocommerce-gateway-elavon' ),
				'<strong>',
				'</strong>'
			), 'error' );

			$is_valid = false;
		}

		return $is_valid;
	}


	/** Getter methods ******************************************************/


	/**
	 * Gets the settings fields specific to this gateway.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_method_form_fields() {

		$fields = array(
			'authorization_terms' => array(
				'title'       => esc_html__( 'Authorization Terms', 'woocommerce-gateway-elavon' ),
				'type'        => 'textarea',
				'desc_tip'    => esc_html__( 'Payment authorization terms that the customer will see and agree to during checkout.', 'woocommerce-gateway-elavon' ),
				/* translators: Placeholders: %s - the {order_total} placeholder */
				'description' => sprintf( __( 'Use the %s tag to display the order total.', 'woocommerce-gateway-elavon' ), '<code>{order_total}</code>' ),
				'default'     => $this->get_default_authorization_terms(),
			),
		);

		return array_merge( $fields, parent::get_method_form_fields() );
	}


	/**
	 * Initializes the payment form instance.
	 *
	 * @since 2.6.5
	 *
	 * @return \WC_Elavon_Converge_eCheck_Payment_Form
	 */
	public function init_payment_form_instance() {

		return new WC_Elavon_Converge_eCheck_Payment_Form( $this );
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
			$defaults['account-number'] = '8675309';
			$defaults['routing-number'] = '123456789';
		}

		return $defaults;
	}


	/**
	 * Gets the authorization terms message.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_authorization_terms() {

		/**
		 * Filters the authorization terms message.
		 *
		 * @since 2.0.0
		 * @param string $message the message
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_authorization_terms', $this->authorization_terms );
	}


	/**
	 * Gets the default authorization terms message.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_default_authorization_terms() {

		$merchant_name = get_bloginfo( 'name' );

		return 'I authorize ' . $merchant_name . ' to use information above to initiate an electronic fund transfer from my account or to process the payment as a check transaction or bank drawn draft from my account for the amount of {order_total}. If my payment is returned due to insufficient funds, I authorize ' . $merchant_name . ' to make a one-time electronic funds transfer or to use a bank draft drawn from my account to collect a fee as allowed by state law.';
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

		return WC_Elavon_Converge_API::TRANSACTION_TYPE_ECHECK;
	}


	/**
	 * Builds a Checkout.js transaction request.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order order object
	 * @param string $transaction_type the transaction type
	 * @return \WC_Elavon_Converge_API_eCheck_Transaction_Request
	 */
	protected function build_checkout_js_transaction_request( $order, $transaction_type ) {

		// TODO this probably needs to be updated if we introduce support for Checkout.js in echecks {FN 2020-11-06}
		return new \WC_Elavon_Converge_API_eCheck_Transaction_Request( $this, $order );
	}


}
