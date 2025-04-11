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

use WC_Elavon_Converge_API_Credit_Card_Transaction_Response;

/**
 * The transaction query response class.
 *
 * @since 2.8.0
 */
class Transaction_Query extends WC_Elavon_Converge_API_Credit_Card_Transaction_Response {


	/** @var string approval status message */
	const STATUS_APPROVAL = 'APPROVAL';

	/** @var string success status message */
	const STATUS_SUCCESS = 'SUCCESS';

	/** @var string success status message */
	const STATUS_APPROVAL_FRENCH = 'APPROBAT';

	/**
	 * Determines if the transaction was successful.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public function transaction_approved() {

		return ! empty( $this->get_authorization_code() ) && in_array( $this->get_status_message(), [ self::STATUS_APPROVAL, self::STATUS_SUCCESS, self::STATUS_APPROVAL_FRENCH ], true );
	}


	/**
	 * Gets the payment type.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	public function get_payment_type() {

		return 'ELECTRONICCHECK' === $this->ssl_card_type
			? \WC_Gateway_Elavon_Converge::PAYMENT_TYPE_ECHECK
			: \WC_Gateway_Elavon_Converge::PAYMENT_TYPE_CREDIT_CARD;
	}


	/**
	 * Gets the value of the merchant-defined transaction description.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	public function get_transaction_description() {

		return is_string( $this->ssl_description ) ? $this->ssl_description : '';
	}


}
