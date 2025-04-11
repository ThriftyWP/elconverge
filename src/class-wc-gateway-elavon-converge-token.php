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

/**
 * Elavon Converge Token class
 *
 * Adds logic to keep track of a billing hash on the token in order to help
 * determine when we should update the remote token with new data.
 *
 * @since 2.3.2
 */
class WC_Gateway_Elavon_Converge_Token extends Framework\SV_WC_Payment_Gateway_Payment_Token {


	/** @var string hash of the billing information for this payment profile */
	protected $billing_hash;


	/**
	 * Constructs the token.
	 *
	 * @since 2.3.2
	 *
	 * @param string $token_id payment token
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
	 */
	public function __construct( $token_id, $data ) {

		if ( ! $data instanceof \WC_Payment_Token ) {
			if ( empty( $data['billing_hash'] ) ) {
				$data['billing_hash'] = $this->calculate_billing_hash( $data );
			} else {
				$this->set_billing_hash( $data['billing_hash'] );
			}
			if ( ! empty( $data['exp_year'] ) && strlen( $data['exp_year'] ) < 4 ) {
				$data['exp_year'] = '20' . $data['exp_year'];
			}
		}

		parent::__construct( $token_id, $data );
	}


	/**
	 * Checks if the billing info on this token matches that of the given order.
	 *
	 * @since 2.3.2
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	public function billing_matches_order( WC_Order $order ) {

		$billing = WC_Gateway_Elavon_Converge_Tokens_Handler::get_billing_token_data_from_order( $order );

		return md5( json_encode( $billing ) ) === $this->get_billing_hash();
	}


	/**
	 * Updates the billing hash with data from an order.
	 *
	 * @since 2.3.2
	 *
	 * @param \WC_Order $order
	 */
	public function update_billing_hash( WC_Order $order ) {

		$this->data['billing_hash'] = $this->calculate_billing_hash( $order );
	}


	/**
	 * Calculates the billing hash from an order or from token data.
	 *
	 * The hashed value is the md5 hash of a JSON-encoded array in format:
	 *
	 * {
	 *   'ssl_first_name'  => 'first name',
	 *   'ssl_last_name'   => 'last name',
	 *   'ssl_company'     => 'company',
	 *   'ssl_avs_address' => 'address1',
	 *   'ssl_address2'    => 'address2',
	 *   'ssl_city'        => 'city',
	 *   'ssl_state'       => 'state',
	 *   'ssl_country'     => 'country',
	 *   'ssl_avs_zip'     => 'postcode',
	 *   'ssl_phone'       => 'phone',
	 *   'ssl_email'       => 'email',
	 * }
	 *
	 * @since 2.3.2
	 *
	 * @param \WC_Order|array $data order or array with token_data key
	 * @return null|string null if billing info is blank, hash string otherwise
	 */
	protected function calculate_billing_hash( $data ) {

		$billing_data = array();

		if ( $data && $data instanceof WC_Order ) {

			$billing_data = WC_Gateway_Elavon_Converge_Tokens_Handler::get_billing_token_data_from_order( $data );

		} elseif ( is_array( $data ) && isset( $data['ssl_first_name'] ) ) {

			$billing_keys = array(
				'ssl_first_name',
				'ssl_last_name',
				'ssl_company',
				'ssl_avs_address',
				'ssl_address2',
				'ssl_city',
				'ssl_state',
				'ssl_country',
				'ssl_avs_zip',
				'ssl_phone',
				'ssl_email',
			);

			foreach( $billing_keys as $key ) {

				$billing_data[ $key ] = isset( $data['token_data'][ $key ] ) ? $data['token_data'][ $key ] : '';
			}
		}

		return $this->billing_hash = ( ! empty( $billing_data ) ? md5( json_encode( $billing_data ) ) : null );
	}


	/**
	 * Returns the billing hash.
	 *
	 * @since 2.3.2
	 *
	 * @return string billing hash
	 */
	public function get_billing_hash() {

		return $this->billing_hash;
	}


	/**
	 * Sets the billing hash.
	 *
	 * @since 2.3.2
	 *
	 * @param string $hash billing hash
	 */
	public function set_billing_hash( $hash ) {

		$this->billing_hash = $hash;
	}
}
