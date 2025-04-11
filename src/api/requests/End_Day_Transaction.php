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

namespace SkyVerge\WooCommerce\Elavon_Converge\API\Requests;

defined( 'ABSPATH' ) or exit;

use WC_Elavon_Converge_API_Request;

/**
 * The end of day transaction query request class.
 *
 * @since 2.8.0
 */
class End_Day_Transaction extends WC_Elavon_Converge_API_Request {


	/**
	 * Queries a transaction.
	 *
	 * @since 2.8.0
	 *
	 * @param string $transaction_id the transaction ID
	 */
	public function query_transaction( $transaction_id ) {

		$this->transaction_type = 'txnquery';
		$this->request_data     = [
			'ssl_txn_id' => $transaction_id,
		];
	}


}
