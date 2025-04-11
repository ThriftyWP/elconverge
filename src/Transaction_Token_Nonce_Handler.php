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

/**
 * A handler for generating, storing, and verifying nonces.
 *
 * These nonces are used when retrieving transaction tokens for Checkout.js.
 *
 * @see \WC_Gateway_Elavon_Converge::get_checkout_js_handler_args()
 * @see \SkyVerge\WooCommerce\Elavon_Converge\AJAX::get_transaction_token()
 *
 * @since 2.11.5
 */
class Transaction_Token_Nonce_Handler
{

	/** @var string the transaction token nonce prefix */
	const NONCE_PREFIX = 'transaction-token-';


	/**
	 * Gets the nonce for the get transaction token AJAX request.
	 *
	 * @since 2.11.5
	 *
	 * @internal
	 *
	 * @param int $order_id the order id
	 * @return string
	 */
	public static function get_nonce( int $order_id ) : string {

		$action = self::get_nonce_action( $order_id );
		$stored_nonce = self::get_stored_nonce( $action );

		if ( ! empty( $stored_nonce['value'] ) && $stored_nonce['timeout'] > time() ) {
			return $stored_nonce['value'];
		}

		$nonce = wp_generate_password( 36, false );

		self::remember_nonce( $action, $nonce );

		return $nonce;
	}


	/**
	 * Returns the transaction token nonce action for the given order ID.
	 *
	 * @since 2.11.5
	 *
	 * @param int $order_id
	 * @return string
	 */
	protected static function get_nonce_action( int $order_id ) : string {

		return self::NONCE_PREFIX . $order_id;
	}


	/**
	 * Remembers the transaction token nonce in a site transient.
	 *
	 * Instead of using set_transient, this method stores both the transient & timeout in wp_options using direct SQL
	 * queries, bypassing potential caching issues.
	 *
	 * @since 2.11.5
	 *
	 * @param string $action the nonce action
	 * @param string $nonce nonce value
	 */
	protected static function remember_nonce( string $action, string $nonce ) {
		global $wpdb;

		$transient_option  = '_transient_' . $action;
		$transient_timeout = '_transient_timeout_' . $action;

		$wpdb->replace( $wpdb->options, [ 'option_name' => $transient_option, 'option_value' => $nonce, 'autoload' => 'no' ], [ '%s', '%s', '%s'] );
		$wpdb->replace( $wpdb->options, [ 'option_name' => $transient_timeout, 'option_value' => time() + HOUR_IN_SECONDS, 'autoload' => 'no' ], [ '%s', '%d', '%s' ] );
	}


	/**
	 * Consumes & returns the transaction token nonce, if available & not expired.
	 *
	 * Parts of this method are based on get_transient and delete_transient, but uses direct SQL queries to avoid
	 * potential issues with caching.
	 *
	 * @since 2.11.5
	 *
	 * @param string $action the nonce action
	 */
	protected static function consume_nonce( string $action ) {
		global $wpdb;

		$transient_option  = '_transient_' . $action;
		$transient_timeout = '_transient_timeout_' . $action;

		// delete the nonce transient, ensuring it can only be used once
		$wpdb->delete( $wpdb->options, [ 'option_name' => $transient_option ] );
		$wpdb->delete( $wpdb->options, [ 'option_value' => $transient_timeout ] );
	}

	/**
	 * Gets the stored nonce value and timeout.
	 *
	 * @since 2.14.1
	 *
	 * @param string $action
	 * @param bool $lock Whether to lock the read for update, defaults to false.
	 * @return array{value?: string, timeout?: int}
	 */
	protected static function get_stored_nonce( string $action, bool $lock = false ) : array {
		$transient_option  = '_transient_' . $action;
		$transient_timeout = '_transient_timeout_' . $action;

		return [
			'value' => self::get_raw_option( $transient_option, $lock ),
			'timeout' => self::get_raw_option( $transient_timeout )
		];
	}


	/**
	 * Verifies & consumes the given nonce for the given order id.
	 *
	 * @since 2.11.5
	 * @internal
	 *
	 * @param int $order_id the order id
	 * @param string $nonce the nonce to be verified
	 * @return bool
	 */
	public static function verify_nonce( int $order_id, string $nonce ) : bool {
		global $wpdb;

		$action = self::get_nonce_action( $order_id );

		$wpdb->query( 'START TRANSACTION' );

		$stored_nonce = self::get_stored_nonce( $action, true );

		// bail if the nonce value is a mismatch, or the nonce has timed out
		if ( $stored_nonce['value'] !== $nonce || $stored_nonce['timeout'] <= time() ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// ensure the nonce can only be used once by consuming it
		self::consume_nonce( $action );

		$wpdb->query( 'COMMIT' );

		return true;
	}


	/** Helpers ******************************************************/


	/**
	 * Returns the raw option value using a direct SQL query.
	 *
	 * The goal of this method is to bypass all filters and caching for the given option.
	 *
	 * @since 2.11.5
	 *
	 * @param string $option the option name
	 * @return mixed
	 */
	protected static function get_raw_option( string $option, bool $lock = false ) {
		global $wpdb;

		$for_update = $lock ? 'FOR UPDATE' : '';

		return $wpdb->get_var( $wpdb->prepare( "
			SELECT option_value
			FROM {$wpdb->options}
			WHERE option_name = %s
			LIMIT 1
			$for_update
		", $option ) );
	}
}
