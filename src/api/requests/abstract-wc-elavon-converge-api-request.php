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
 * The base transaction request class.
 *
 * @since 2.0.0
 */
abstract class WC_Elavon_Converge_API_Request extends Framework\SV_WC_API_XML_Request {


	/** @var array request data */
	protected $request_data = array();

	/** @var \WC_Gateway_Elavon_Converge the gateway object associated with this request */
	protected $gateway;

	/** @var \WC_Order|null the order object associated with this request, if available */
	protected $order;

	/** @var string the transaction type */
	protected $transaction_type = '';


	/**
	 * Constructs the request.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Gateway_Elavon_Converge $gateway the gateway object associated with this request
	 * @param \WC_Order|null $order the order object associated with this request, if available
	 */
	public function __construct( WC_Gateway_Elavon_Converge $gateway, \WC_Order $order = null ) {

		$this->gateway = $gateway;
		$this->order   = $order;
	}


	/**
	 * Gets the order object associated with this request.
	 *
	 * Was previously placed in {@see WC_Elavon_Converge_API_Transaction_Request} class.
	 *
	 * @since 2.11.2
	 *
	 * @return \WC_Order|null
	 */
	public function get_order() {

		return $this->order;
	}


	/**
	 * Converts the request data into an XML string.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function to_string() {

		$string = parent::to_string();

		// strip the leading XML data to conform to their strange formatting
		return 'xmldata=' . strstr( $string, '<' . $this->get_root_element() );
	}


	/**
	 * Masks the auth details before logging the request.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function to_string_safe() {

		$string = parent::to_string_safe();

		// mask the PIN
		if ( preg_match( '/<ssl_pin>(\w+)<\/ssl_pin>/', $string, $matches ) ) {
			$string = preg_replace( '/<ssl_pin>\w+<\/ssl_pin>/', '<ssl_pin>' . str_repeat( '*', strlen( $matches[1] ) ) . '</ssl_pin>', $string );
		}

		return $string;
	}


	/**
	 * Gets the root XML element.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_root_element() {

		return 'txn';
	}


	/**
	 * Get the request transaction type.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_transaction_type() {

		return $this->transaction_type;
	}


	/**
	 * Gets the gateway object associated with this request.
	 *
	 * @since 2.0.0
	 *
	 * @return \WC_Gateway_Elavon_Converge|\WC_Gateway_Elavon_Converge_Credit_Card|\WC_Gateway_Elavon_Converge_eCheck
	 */
	protected function get_gateway() {

		return $this->gateway;
	}


	/**
	 * Gets the customer data from an order.
	 *
	 * @since 2.3.3
	 *
	 * @param \WC_Order $order order object
	 * @return array
	 */
	protected function get_customer_data_from_order( WC_Order $order ) {

		return array(
			'ssl_first_name'    => $this->str_truncate( $order->get_billing_first_name( 'edit' ), 20 ),
			'ssl_last_name'     => $this->str_truncate( $order->get_billing_last_name( 'edit' ), 30 ),
			'ssl_company'       => $this->str_truncate( $order->get_billing_company( 'edit' ), 50 ),
			'ssl_avs_address'   => $this->str_truncate( $order->get_billing_address_1( 'edit' ), 30 ),
			'ssl_address2'      => $this->str_truncate( $order->get_billing_address_2( 'edit' ), 30 ),
			'ssl_city'          => $this->str_truncate( $order->get_billing_city( 'edit' ), 30 ),
			'ssl_state'         => $this->str_truncate( $order->get_billing_state( 'edit' ), 30 ),
			'ssl_avs_zip'       => $this->str_truncate( $order->get_billing_postcode( 'edit' ), 9, '' ),
			'ssl_country'       => Framework\Country_Helper::convert_alpha_country_code( $order->get_billing_country( 'edit' ) ), // 3-char country code
			'ssl_email'         => $this->str_truncate( $order->get_billing_email( 'edit' ), 100, '' ),
			'ssl_phone'         => $this->str_truncate( preg_replace( '/\D/', '', $order->get_billing_phone( 'edit' ) ), 20, '' ),
			'ssl_cardholder_ip' => $order->get_customer_ip_address( 'edit' ),
		);
	}


	/**
	 * Truncates a string to the specified length.
	 *
	 * Normally we'd just use Framework\SV_WC_Helper::str_truncate(), but the Converge API's
	 * character limits are actually byte limits, so multibyte characters can
	 * trigger "length exceeded" errors.
	 *
	 * @since 2.3.3
	 *
	 * @param string $string string to truncate
	 * @param int $length desired length
	 * @param string $omission omission text, defaults to '...'
	 * @return string
	 */
	protected function str_truncate( $string, $length, $omission = '...' ) {

		if ( extension_loaded( 'mbstring' ) ) {

			// bail if string doesn't need to be truncated
			if ( mb_strlen( $string, '8bit' ) <= $length ) {
				return $string;
			}

			$length -= mb_strlen( $omission, '8bit' );

			$string = mb_substr( $string, 0, $length, '8bit' );

		} else {

			// bail if string doesn't need to be truncated
			if ( strlen( $string ) <= $length ) {
				return $string;
			}

			$length -= strlen( $omission );

			$string = substr( $string, 0, $length );
		}

		return $string . $omission;
	}


	/**
	 * Gets the request data.
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_data() {

		$data = array_merge( $this->get_auth_data(), parent::get_data() );

		/**
		 * Filters the API request data.
		 *
		 * @since 2.4.0
		 *
		 * @param array $data the request data
		 * @param \WC_Elavon_Converge_API_Request $request the request object
		 */
		$data = apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_request_data', $data, $this );

		return [ $this->get_root_element() => $data ];
	}


	/**
	 * Gets the request auth data.
	 *
	 * @since 2.6.8
	 *
	 * @return array
	 */
	protected function get_auth_data() {

		return [
			'ssl_transaction_type' => $this->get_transaction_type(),
			'ssl_merchant_id'      => $this->get_gateway()->get_merchant_id(),
			'ssl_user_id'          => $this->get_gateway()->get_user_id(),
			'ssl_pin'              => $this->get_gateway()->get_pin(),
			'ssl_vendor_id'        => 'scskyver',
		];
	}


	/**
	 * Removes special characters from data to avoid API issues.
	 *
	 * @since 2.4.2
	 *
	 * @param array $data data to clean
	 * @return array
	 */
	protected function remove_special_characters( $data ) {

		// clean any extra special characters to avoid API issues
		foreach ( $data as $key => $value ) {
			$data[ $key ] = str_replace( [ '&', '<', '>' ], '', $value );
		}

		return $data;
	}


}
