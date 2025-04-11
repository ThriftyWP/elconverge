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

namespace SkyVerge\WooCommerce\Elavon_Converge\API\Responses;

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;

/**
 * The response for the Token Query request.
 *
 * @since 2.8.0
 */
class Token_Query extends \WC_Elavon_Converge_API_Token_Response {


	/**
	 * Gets the successfully authorized credit card's card type.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	public function get_card_type() {

		// if ssl_card_short_description doesn't have a value we will use ssl_card_type
		$card_type = $this->ssl_card_short_description ?: $this->ssl_card_type;

		// if ssl_card_type is used above, we don't want to display CREDITCARD
		if ( 'CREDITCARD' !== $card_type ) {
			return strtolower( $card_type );
		}

		return '';
	}


	/**
	 * Gets the masked account number of the successfully tokenized credit card.
	 *
	 * The first and last four digits are not masked.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	public function get_account_number() {

		return (string) $this->ssl_account_number;
	}


	/**
	 * Gets last four digits of the successfully authorized credit card.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	public function get_last_four() {

		return substr( $this->get_account_number(), -4 );
	}


	/**
	 * Gets the data necessary to build a payment token from the response.
	 *
	 * Overwritten to avoid trying to get the account number from the order.
	 *
	 * @since 2.8.0
	 *
	 * @return array
	 */
	protected function get_payment_token_data() {

		$data = [
			'type'      => 'credit_card',
			'last_four' => $this->get_last_four(),
			'card_type' => $this->get_card_type(),
			'exp_month' => $this->get_exp_month(),
			'exp_year'  => $this->get_exp_year(),
		];

		// set the card type or account number if the card type wasn't returned
		if ( $card_type = $this->get_card_type() ) {
			$data['card_type'] = $card_type;
		} else {
			$data['account_number'] = $this->get_account_number();
		}

		return $data;
	}


}
