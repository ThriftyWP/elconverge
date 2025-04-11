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

namespace SkyVerge\WooCommerce\Elavon_Converge\Blocks;

use SkyVerge\WooCommerce\Elavon_Converge\Blocks\Traits\Checkout_Block_Integration_Trait;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0\Payment_Gateway\Blocks\Gateway_Checkout_Block_Integration;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0\SV_WC_Payment_Gateway;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0\SV_WC_Payment_Gateway_Helper;

/**
 * Credit card checkout block integration.
 *
 * @since 2.14.0
 *
 * @property \WC_Elavon_Converge $plugin the plugin instance
 * @property \WC_Gateway_Elavon_Converge_Credit_Card $gateway the gateway instance
 */
class Credit_Card_Checkout_Block_Integration extends Gateway_Checkout_Block_Integration {


	use Checkout_Block_Integration_Trait;


	/**
	 * Adds payment method data.
	 *
	 * @internal
	 *
	 * @see Gateway_Checkout_Block_Integration::get_payment_method_data()
	 *
	 * @since 2.14.0
	 *
	 * @param array<string, mixed> $payment_method_data
	 * @param \WC_Gateway_Elavon_Converge_Credit_Card $gateway
	 * @return array<string, mixed>
	 */
	public function add_payment_method_data( array $payment_method_data, SV_WC_Payment_Gateway $gateway ) : array {

		$payment_method_data['flags'] = array_merge( $payment_method_data['flags'] ?: [], [
			'is_multi_currency_enabled'  => $gateway->is_multi_currency_enabled(),
			'is_multi_currency_required' => $gateway->is_multi_currency_required(),
		] );

		$payment_method_data['gateway'] = array_merge(
			$payment_method_data['gateway'] ?: [],
			$this->get_elavon_connection_settings(),
			[
				'multi_currency_card_types'        => array_map( [ SV_WC_Payment_Gateway_Helper::class, 'normalize_card_type' ], $gateway->get_multi_currency_card_types() ),
				'multi_currency_terminal_currency' => $gateway->get_multi_currency_terminal_currency(),
			]
		);

		return $payment_method_data;
	}


}
