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

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\Elavon_Converge\AJAX;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;

/**
 * The main class for the Elavon Converge Payment Gateway.
 *
 * This class handles all the non-gateway tasks such as verifying dependencies are met, loading the text domain, etc.
 *
 * @since 1.2.0
 */
class WC_Elavon_Converge extends Framework\SV_WC_Payment_Gateway_Plugin {


	/** version number */
	const VERSION = '2.14.3';

	/** @var WC_Elavon_Converge single instance of this plugin */
	protected static $instance;

	/** @var AJAX AJAX handler */
	protected $ajax;

	/** plugin id */
	const PLUGIN_ID = 'elavon_vm';

	/** string class name to load as gateway, DEPRECATED as of 2.0.0 */
	const GATEWAY_CLASS_NAME = 'WC_Gateway_Elavon_Converge_Credit_Card';

	/** string credit card gateway class name */
	const CREDIT_CARD_GATEWAY_CLASS_NAME = 'WC_Gateway_Elavon_Converge_Credit_Card';

	/** string credit card gateway ID */
	const CREDIT_CARD_GATEWAY_ID = 'elavon_converge_credit_card';

	/** string echeck gateway class name */
	const ECHECK_GATEWAY_CLASS_NAME = 'WC_Gateway_Elavon_Converge_eCheck';

	/** string echeck gateway ID */
	const ECHECK_GATEWAY_ID = 'elavon_converge_echeck';


	/**
	 * Initializes the plugin.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			[
				'text_domain'        => 'woocommerce-gateway-elavon',
				'gateways'           => [
					self::CREDIT_CARD_GATEWAY_ID => self::CREDIT_CARD_GATEWAY_CLASS_NAME,
					self::ECHECK_GATEWAY_ID      => self::ECHECK_GATEWAY_CLASS_NAME,
				],
				'supported_features' => [
					'hpos'   => true,
					'blocks' => [
						'cart'     => true,
						'checkout' => true,
					],
				],
				'supports'           => [
					self::FEATURE_CAPTURE_CHARGE,
					self::FEATURE_MY_PAYMENT_METHODS,
				],
				'require_ssl'        => true,
				'dependencies'       => [
					'php_extensions' => [ 'simplexml', 'xmlwriter', 'dom' ],
				],
			]
		);

		// Load the gateway
		$this->includes();
	}


	/**
	 * Determine if TLS v1.2 is required for this plugin's API requests.
	 *
	 * @since 2.6.8
	 *
	 * @return bool
	 */
	public function require_tls_1_2() {

		return true;
	}


	/**
	 * Gets the deprecated/removed hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_deprecated_hooks() {

		$hooks = array(
			'woocommerce_elavon_vm_icon' => array(
				'version'     => '2.0.0',
				'removed'     => true,
				'replacement' => 'wc_' . self::CREDIT_CARD_GATEWAY_ID . '_icon',
				'map'         => true,
			),
			'woocommerce_elavon_card_types' => array(
				'version'     => '2.0.0',
				'removed'     => true,
				'replacement' => 'wc_' . self::CREDIT_CARD_GATEWAY_ID . '_available_card_types',
				'map'         => true,
			),
			'wc_payment_gateway_elavon_vm_request_xml' => array(
				'version'     => '2.0.0',
				'removed'     => true,
				'replacement' => 'wc_' . self::CREDIT_CARD_GATEWAY_ID . '_request_data',
			),
		);

		return $hooks;
	}


	/**
	 * Loads the necessary files.
	 *
	 * @since 2.0.0
	 */
	public function includes() {

		// api
		require_once( $this->get_plugin_path() . '/src/api/class-wc-elavon-converge-api.php' );

		// transaction token nonce handler
		require_once( $this->get_plugin_path() . '/src/Transaction_Token_Nonce_Handler.php' );

		// gateway classes
		require_once( $this->get_plugin_path() . '/src/abstract-wc-gateway-elavon-converge.php' );
		require_once( $this->get_plugin_path() . '/src/class-wc-gateway-elavon-converge-credit-card.php' );
		require_once( $this->get_plugin_path() . '/src/class-wc-gateway-elavon-converge-echeck.php' );
		require_once( $this->get_plugin_path() . '/src/class-wc-gateway-elavon-converge-token.php' );
		require_once( $this->get_plugin_path() . '/src/class-wc-gateway-elavon-converge-tokens-handler.php' );

		// payment forms
		require_once( $this->get_plugin_path() . '/src/payment-forms/class-wc-elavon-converge-payment-form.php' );
		require_once( $this->get_plugin_path() . '/src/payment-forms/class-wc-elavon-converge-echeck-payment-form.php' );

		// handlers
		require_once( $this->get_plugin_path() . '/src/AJAX.php' );

		// blocks
		require_once( $this->get_plugin_path() . '/src/Blocks/Traits/Checkout_Block_Integration_Trait.php' );
	}


	/**
	 * Initializes the plugin.
	 *
	 * @since 2.8.0
	 */
	public function init_plugin() {

		if ( wp_doing_ajax() ) {
			$this->ajax = new AJAX();
		}
	}


	/**
	 * Gets the My Payment Methods handler instance.
	 *
	 * @since 2.6.6
	 *
	 * @return \SkyVerge\WooCommerce\Elavon_Converge\My_Payment_Methods
	 */
	protected function get_my_payment_methods_instance() {

		require_once( $this->get_plugin_path() . '/src/My_Payment_Methods.php' );

		return new \SkyVerge\WooCommerce\Elavon_Converge\My_Payment_Methods( $this );
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 1.2.0
	 *
	 * @see Framework\SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {

		return 'https://docs.woocommerce.com/document/elavon-vm-payment-gateway/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.6.0
	 *
	 * @see Framework\SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {

		return 'https://woocommerce.com/my-account/marketplace-ticket-form/';
	}


	/**
	 * Gets the plugin sales page URL.
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	public function get_sales_page_url() {

		return 'https://woocommerce.com/products/elavon-vm-payment-gateway/';
	}


	/**
	 * Displays admin notices for new users.
	 *
	 * @since 2.0.0
	 *
	 * @see Framework\SV_WC_Plugin::add_admin_notices()
	 */
	public function add_admin_notices() {

		// show any dependency notices
		parent::add_admin_notices();

		// install notice
		if ( 'wc-settings' === Framework\SV_WC_Helper::get_requested_value( 'page' ) || Framework\SV_WC_Helper::is_current_screen( 'plugins' ) ) {

			$configured  = false;
			$notice      = '';
			$dismissible = true;

			foreach ( $this->get_gateways() as $gateway ) {

				if ( get_option( 'woocommerce_' . $gateway->get_id() . '_settings', false ) ) {

					$configured = true;
					break;
				}
			}

			// if no gateways are configured, display a "config it" prompt
			if ( ! $configured ) {

				$notice = sprintf(
					/* translators: Placeholders: %1$s - <strong> tag, %2$s - the plugin name, %3$s - </strong> tag, %4$s - <a> tag, %5$s - </a> tag */
					__( '%1$s%2$s is almost ready!%3$s To get started, please ​%4$sconnect to Elavon Converge%5$s.', 'woocommerce-gateway-elavon' ),
					'<strong>',
					$this->get_plugin_name(),
					'</strong>',
					'<a href="' . esc_url( $this->get_settings_url() ) . '">',
					'</a>'
				);

				$dismissible = false;

			// otherwise, just a prompt to read the docs will do on our settings/plugins screen
			} elseif ( $this->is_plugin_settings() || Framework\SV_WC_Helper::is_current_screen( 'plugins' ) ) {

				$notice = sprintf(
					/* translators: Placeholders: %1$s - <strong> tag, %2$s - the plugin name, %3$s - </strong> tag, %4$s - <a> tag, %5$s - </a> tag */
					__( '%1$sThanks for installing %2$s!%3$s Need help? %4$sRead the documentation%5$s.', 'woocommerce-gateway-elavon' ),
					'<strong>',
					$this->get_plugin_name(),
					'</strong>',
					'<a href="' . esc_url( $this->get_documentation_url() ) . '" target="_blank">',
					'</a>'
				);
			}

			if ( $notice ) {

				$this->get_admin_notice_handler()->add_admin_notice( $notice, 'wc-elavon-welcome', array(
					'always_show_on_settings' => false,
					'dismissible'             => $dismissible,
					'notice_class'            => 'updated'
				) );
			}

			/** @var \WC_Gateway_Elavon_Converge_Credit_Card $credit_card_gateway */
			$credit_card_gateway = $this->get_gateway( self::CREDIT_CARD_GATEWAY_ID );

			// display a warning if multi-currency is required but not confirmed
			if ( $credit_card_gateway->is_enabled() && $credit_card_gateway->is_multi_currency_required() && ! $credit_card_gateway->is_multi_currency_enabled() ) {

				if ( $this->is_plugin_settings() ) {

					$notice = sprintf(
						/* translators: Placeholders: %s - the payment gateway name */
						__( '%s is inactive because your store\'s currency requires Multi-Currency. Please confirm that Multi-Currency is enabled for your account.', 'woocommerce-gateway-elavon' ),
						'<strong>' . $credit_card_gateway->get_method_title() . '</strong>'
					);

				} else {

					$notice = sprintf(
						/* translators: Placeholders: %1$s - the payment gateway name, %2$s - opening <a> tag, %3$s - closing </a> tag */
						__( '%1$s is inactive because your store\'s currency requires Multi-Currency. Please confirm that Multi-Currency is enabled for your account %2$sin the gateway settings%3$s.', 'woocommerce-gateway-elavon' ),
						'<strong>' . $credit_card_gateway->get_method_title() . '</strong>',
						'<a href="' . esc_url( $this->get_settings_url() ) . '">', '</a>'
					);
				}

				$this->get_admin_notice_handler()->add_admin_notice( $notice, 'wc-elavon-multi-currency-required', array(
					'always_show_on_settings' => true,
					'dismissible'             => false,
					'notice_class'            => 'error',
				) );
			}
		}

		$this->maybe_add_checkout_js_admin_notice();
	}


	/**
	 * Determines whether to add an admin notice if the merchant had previously disabled Checkout.js.
	 *
	 * @since 2.8.0
	 */
	private function maybe_add_checkout_js_admin_notice() {

		$settings = $this->get_gateway_settings( self::CREDIT_CARD_GATEWAY_ID );

		// skip if gateway is disabled
		if ( ! isset( $settings['enabled'] ) || ! wc_string_to_bool( $settings['enabled'] ) ) {
			return;
		}

		// show the notice if Checkout.js was disabled explicitly
		if ( isset( $settings['enable_checkout_js'] ) && ! wc_string_to_bool( $settings['enable_checkout_js'] ) ) {
			$this->add_checkout_js_admin_notice();
		}
	}


	/**
	 * Adds an admin notice concerning Checkout.js being required.
	 *
	 * @since 2.8.0
	 */
	private function add_checkout_js_admin_notice() {

		$this->get_admin_notice_handler()->add_admin_notice(
			sprintf(
				__( '%1$sHeads up!%2$s Elavon\'s %3$sCheckout.js%4$s feature is now active on your site to make your checkout more secure. %5$sClick here%6$s for instructions on setting up Checkout.js and please %7$scontact support%8$s with any questions or concerns.', 'woocommerce-gateway-elavon' ),
				'<strong>', '</strong>',
				'<a href="https://developer.elavon.com/na/docs/converge/1.0.0/integration-guide/integration_methods/checkoutjs.md" target="_blank">', '</a>',
				'<a href="https://docs.woocommerce.com/document/elavon-vm-payment-gateway/#setup-ip-address" target="_blank">', '</a>',
				'<a href="https://woocommerce.com/my-account/create-a-ticket/" target="_blank">', '</a>'
			),
			'checkout-js-required', [
			'dismissible'             => true,
			'always_show_on_settings' => false,
			'notice_class'            => 'updated',
		] );
	}


	/**
	 * Gets the main Elavon instance.
	 *
	 * @see wc_elavon_converge()
	 *
	 * @since 1.3.0
	 *
	 * @return WC_Elavon_Converge
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Returns the ajax handler.
	 *
	 * @since 2.8.0
	 *
	 * @return AJAX
	 */
	public function get_ajax_handler() {

		return $this->ajax;
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.2.0
	 *
	 * @see Framework\SV_WC_Payment_Gateway::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {

		return __( 'WooCommerce Elavon Converge', 'woocommerce-gateway-elavon' );
	}


	/**
	 * Gets the "Configure Credit Cards" or "Configure eCheck" plugin action links that go
	 * directly to the gateway settings page.
	 *
	 * @since 2.0.0
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Plugin::get_settings_url()
	 * @param string $gateway_id the gateway ID
	 * @return string
	 */
	public function get_settings_link( $gateway_id = null ) {

		if ( self::ECHECK_GATEWAY_ID === $gateway_id ) {
			$label = __( 'Configure eChecks', 'woocommerce-gateway-elavon' );
		} else {
			$label = __( 'Configure Credit Cards', 'woocommerce-gateway-elavon' );
		}

		return sprintf( '<a href="%s">%s</a>',
			$this->get_settings_url( $gateway_id ),
			$label
		);
	}


	/**
	 * Initializes the lifecycle handler.
	 *
	 * @since 2.4.0
	 */
	protected function init_lifecycle_handler() {

		require_once( $this->get_plugin_path() . '/src/Lifecycle.php' );

		$this->lifecycle_handler = new \SkyVerge\WooCommerce\Elavon_Converge\Lifecycle( $this );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.2.0
	 *
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {

		return __FILE__;
	}


}


/**
 * Returns the One True Instance of Elavon Converge.
 *
 * @since 2.4.0
 *
 * @return WC_Elavon_Converge
 */
function wc_elavon_converge() {

	return WC_Elavon_Converge::instance();
}
