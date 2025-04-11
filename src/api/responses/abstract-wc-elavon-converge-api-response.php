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
 * The base response class.
 *
 * @since 2.0.0
 */
abstract class WC_Elavon_Converge_API_Response extends Framework\SV_WC_API_XML_Response {


	/** @var \WC_Elavon_Converge_API_Request the original request object */
	protected $request;


	/**
	 * Build a response object from the raw response XML.
	 *
	 * @since 2.0.2
	 * @param \WC_Elavon_Converge_API_Request $request the original request object
	 * @param string $raw_response_xml the raw response XML
	 */
	public function __construct( $request, $raw_response_xml ) {

		$this->request = $request;

		parent::__construct( $raw_response_xml );
	}


	/**
	 * Determines if the response has an error.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function has_error() {

		return ( $this->errorCode );
	}


	/**
	 * Gets the error code.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function get_error_code() {

		return $this->errorCode;
	}


	/**
	 * Gets the error message.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function get_error_message() {

		return $this->errorName . ' - ' . $this->errorMessage;
	}


	/**
	 * Gets the request object that generated this response.
	 *
	 * @since 2.0.2
	 * @return \WC_Elavon_Converge_API_Request
	 */
	protected function get_request() {

		return $this->request;
	}


}
