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
 * @package     WC-Elavon
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2024, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Elavon_Converge;

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0\SV_WC_Plugin_Exception;
use WC_Gateway_Elavon_Converge;
use WC_Order;

/**
 * AJAX handler.
 *
 * @since 2.8.0
 */
class AJAX {


	/** @var string the get transaction token AJAX action */
	const ACTION_GET_TRANSACTION_TOKEN = 'wc_elavon_vm_get_transaction_token';

	/** @var int order id */
	protected int $order_id;

	/**
	 * Constructs the handler.
	 *
	 * @since 2.8.0
	 */
	public function __construct() {

		require_once( wc_elavon_converge()->get_plugin_path() . '/src/Rate_Limiter.php' );

		$this->add_hooks();
	}


	/**
	 * Adds the necessary action and filter hooks.
	 *
	 * @since 2.8.0
	 */
	protected function add_hooks() {

		add_action( 'wp_ajax_' . self::ACTION_GET_TRANSACTION_TOKEN,        [ $this, 'get_transaction_token' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_GET_TRANSACTION_TOKEN, [ $this, 'get_transaction_token' ] );
	}


	/**
	 * Handles the AJAX action used to get a new Hosted Payments transaction token.
	 *
	 * @internal
	 *
	 * @since 2.8.0
	 */
	public function get_transaction_token() {
		try {

			Rate_Limiter::apply( 'get_transaction_token' );

			$order_id = wc_clean( Framework\SV_WC_Helper::get_requested_value( 'order_id' ) );

			// not using empty() to accept 0 as a valid Order ID (the ID can be 0 on the Add Payment Method page)
			if ( 0 === strlen( $order_id ) ) {
				throw new Framework\SV_WC_Plugin_Exception( __( 'Order ID is required.', 'woocommerce-gateway-elavon' ) );
			}

			$gatewayId = wc_clean( Framework\SV_WC_Helper::get_requested_value( 'gateway_id' ) );
			$contextChecker = new Framework\PaymentFormContextChecker($gatewayId);

			if (
				$contextChecker->currentContextRequiresTermsAndConditionsAcceptance() &&
				'on' !== Framework\SV_WC_Helper::get_requested_value( 'terms' ) ) {
				throw new Framework\SV_WC_Plugin_Exception( __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-gateway-elavon' ) );
			}

			if ( ! Transaction_Token_Nonce_Handler::verify_nonce( (int) $order_id, wc_clean( Framework\SV_WC_Helper::get_requested_value( 'security' ) ) ) ) {
				throw new Framework\SV_WC_Plugin_Exception( __( 'Invalid nonce.', 'woocommerce-gateway-elavon' ) );
			}

			$this->order_id = (int) $order_id;
			$order          = new \WC_Order( $order_id ); // we cannot use `wc_get_order()` here since we need to allow blank/non-saved orders on the Add Payment Method page

			if ( $order->has_status( 'cancelled' ) ) {

				// check if the line items (products) in order are still in stock
				$line_items = Framework\SV_WC_Helper::get_order_line_items( $order );

				foreach ( $line_items as $line_item ) {

					$product = $line_item->product;

					if ( ! $product ) {
						continue;
					}

					if ( ! $product->managing_stock() || $product->backorders_allowed() ) {
						continue;
					}

					$stock_quantity = $line_item->product->get_stock_quantity();

					if ( $stock_quantity < $line_item->quantity ) {
						throw new Framework\SV_WC_Plugin_Exception( __( 'Some or all of the items in your cart are now out of stock. Please update your cart and try again.', 'woocommerce-gateway-elavon' ) );
					}
				}

				$order->update_status( 'pending-payment' );

				add_filter( 'woocommerce_cancel_unpaid_order', [ $this, 'exclude_cancel_unpaid_order' ], 10, 2 );
			}

			/** @var $gateway WC_Gateway_Elavon_Converge */
			$gateway                 = wc_elavon_converge()->get_gateway( $gatewayId );
			$tokenize_payment_method = wc_string_to_bool( wc_clean( Framework\SV_WC_Helper::get_requested_value( 'tokenize_payment_method' ) ) );
			$test_amount             = wc_clean( Framework\SV_WC_Helper::get_requested_value( 'test_amount' ) );

			$payment_data = $gateway->get_checkout_js_payment_data( $order_id, $tokenize_payment_method );

			if ( $gateway->is_test_environment() && strlen( $test_amount ) > 0 ) {
				$payment_data['ssl_amount'] = $test_amount;
			}

			$transaction_token = $gateway->get_checkout_js_transaction_token( $payment_data['ssl_transaction_type'], isset( $payment_data['ssl_amount'] ) ? $payment_data['ssl_amount'] : 0 );

			wp_send_json_success( [
				'payment_data'      => $payment_data,
				'transaction_token' => $transaction_token,
			] );

		} catch ( Framework\SV_WC_Plugin_Exception $exception ) {

			wp_send_json_error( $exception->getMessage() );
		}

		remove_filter( 'woocommerce_cancel_unpaid_order', [ $this, 'exclude_cancel_unpaid_order' ] );
	}


	/**
	 * Exclude specific order id from cancellation due to timeout.
	 *
	 * @internal
	 *
	 * @since 2.11.6
	 *
	 * @param string $checkout_order_get_created_via
	 * @param WC_Order $order
	 */
	public function exclude_cancel_unpaid_order( $checkout_order_get_created_via, $order ) {

		if ( $order instanceof WC_Order && $order->get_id() === $this->order_id ) {
			return false;
		}

		return $checkout_order_get_created_via;
	}
}
