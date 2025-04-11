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

namespace SkyVerge\WooCommerce\Elavon_Converge\Blocks\Traits;

/**
 * Trait for checkout block integrations
 *
 * @since 2.14.0
 *
 * @property \WC_Gateway_Elavon_Converge_Credit_Card|\WC_Gateway_Elavon_Converge_eCheck $gateway the gateway instance
 */
trait Checkout_Block_Integration_Trait {


	/**
	 * Gets the gateway connection settings.
	 *
	 * @since 2.14.0
	 *
	 * @return array<string, string>
	 */
	protected function get_elavon_connection_settings() : array {

		return [
			'merchant_id' => $this->gateway->get_merchant_id(),
			'user_id'     => $this->gateway->get_user_id(),
			'pin'         => $this->gateway->get_pin(),
		];
	}


}
