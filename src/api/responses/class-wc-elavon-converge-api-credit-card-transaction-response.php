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
 * The credit card transaction response.
 *
 * @since 2.0.0
 */
class WC_Elavon_Converge_API_Credit_Card_Transaction_Response extends WC_Elavon_Converge_API_Transaction_Response implements Framework\SV_WC_Payment_Gateway_API_Authorization_Response {


	/**
	 * Gets the result of the AVS check.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_avs_result() {

		return $this->ssl_avs_response;
	}


	/**
	 * Determines if the AVS check was a match.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function avs_match() {

		return in_array( $this->get_avs_result(), array( 'D', 'M', 'X', 'Y', ) );
	}


	/**
	 * Gets the result of the CSC check.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_csc_result() {

		return $this->ssl_cvv2_response;
	}


	/**
	 * Determines if the CSC check was successful.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function csc_match() {

		return 'M' === $this->get_csc_result();
	}


	/**
	 * Get successfully authorized credit card's card type.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_card_type() {

		return strtolower( $this->ssl_card_short_description );
	}


	/**
	 * Get last four digits of the successfully authorized credit card.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_last_four() {

		return substr( $this->ssl_card_number, -4 );
	}


	/**
	 * Get expiration month of the successfully authorized credit card.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_exp_month() {

		return substr( $this->ssl_exp_date, 0, 2 );
	}


	/**
	 * Get expiration year of the successfully authorized credit card.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_exp_year() {

		return substr( $this->ssl_exp_date, -2 );
	}


	/**
	 * Gets the payment type: 'credit-card', 'echeck', etc...
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	public function get_payment_type() {

		return 'credit-card';
	}


	/**
	 * Gets the data necessary to build a payment token from the response.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_payment_token_data() {

		$data = array(
			'type'      => 'credit_card',
			'last_four' => $this->get_last_four(),
			'card_type' => $this->get_card_type(),
			'exp_month' => $this->get_exp_month(),
			'exp_year'  => $this->get_exp_year(),
		);

		// set the card type or account number if the card type wasn't returned
		if ( $card_type = $this->get_card_type() ) {
			$data['card_type'] = $card_type;
		} else {
			$data['account_number'] = $this->get_request()->get_order()->payment->account_number;
		}

		return $data;
	}


}
