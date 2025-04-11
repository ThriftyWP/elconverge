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
 * The eCheck transaction request class.
 *
 * @since 2.0.0
 */
class WC_Elavon_Converge_API_eCheck_Transaction_Request extends WC_Elavon_Converge_API_Transaction_Request {


	/**
	 * Creates an eCheck transaction.
	 *
	 * @since 2.0.0
	 */
	public function create_debit() {

		$this->transaction_type = \WC_Elavon_Converge_API::TRANSACTION_TYPE_ECHECK;

		$this->create_transaction();

		$this->request_data['ssl_bank_account_number'] = $this->get_order()->payment->account_number;
		$this->request_data['ssl_aba_number']          = $this->get_order()->payment->routing_number;
		$this->request_data['ssl_check_number']        = $this->get_order()->payment->check_number;

		$this->request_data['ssl_agree'] = 1;

		$this->request_data['ssl_bank_account_type'] = (int) ( 'business' === $this->get_order()->payment->account_type );
	}


	/**
	 * Masks the credit card details before logging the request.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function to_string_safe() {

		$string = parent::to_string_safe();

		// mask the account number
		if ( preg_match( '/<ssl_bank_account_number>(\d+)<\/ssl_bank_account_number>/', $string, $matches ) && strlen( $matches[1] ) > 4 ) {
			$string = preg_replace( '/<ssl_bank_account_number>\d+<\/ssl_bank_account_number>/', '<ssl_bank_account_number>' . substr( $matches[1], 0, 1 ) . str_repeat( '*', strlen( $matches[1] ) - 5 ) . substr( $matches[1], -4 ) . '</ssl_bank_account_number>', $string );
		}

		// mask the routing number
		if ( preg_match( '/<ssl_aba_number>(\d+)<\/ssl_aba_number>/', $string, $matches ) && strlen( $matches[1] ) > 4 ) {
			$string = preg_replace( '/<ssl_aba_number>\d+<\/ssl_aba_number>/', '<ssl_aba_number>' . substr( $matches[1], 0, 1 ) . str_repeat( '*', strlen( $matches[1] ) - 5 ) . substr( $matches[1], -4 ) . '</ssl_aba_number>', $string );
		}

		return $string;
	}


}
