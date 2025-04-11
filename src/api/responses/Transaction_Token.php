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
 * The transaction token response class.
 *
 * @since 2.8.0
 */
class Transaction_Token implements Framework\SV_WC_API_Response {


	/** @var string Hosted Payments session token */
	private $token;


	/**
	 * Builds a response object from the raw response data.
	 *
	 * @since 2.8.0
	 *
	 * @param \SkyVerge\WooCommerce\Elavon_Converge\API\Requests\Transaction_Token $request the original request object
	 * @param string $raw_response_data the raw response data
	 */
	public function __construct( \SkyVerge\WooCommerce\Elavon_Converge\API\Requests\Transaction_Token $request, $raw_response_data ) {

		$this->token = $raw_response_data;
	}


	/**
	 * Returns the string representation of this request.
	 *
	 * @since 2.8.0
	 *
	 * @return string the request
	 */
	public function to_string() {

		return $this->token;
	}


	/**
	 * Returns the string representation of this request with any and all
	 * sensitive elements masked or removed.
	 *
	 * @since 2.8.0
	 *
	 * @return string the request, safe for logging/displaying
	 */
	public function to_string_safe() {

		return $this->to_string();
	}


	/**
	 * Determines if there is an error in the response body.
	 *
	 * Returns false because this endpoint does not include error
	 * information in the returned data.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public function has_error() {

		return false;
	}


}
