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
 * @package     WC-Elavon/Gateway
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2024, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\Elavon_Converge\Transaction_Token_Nonce_Handler;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0 as Framework;
use SkyVerge\WooCommerce\Elavon_Converge\API\Responses;

/**
 * The base gateway class.
 *
 * @since 2.0.0
 */
abstract class WC_Gateway_Elavon_Converge extends Framework\SV_WC_Payment_Gateway_Direct {


	/** the demo environment identifier */
	const ENVIRONMENT_DEMO = 'demo';

	/** @var string the production account merchant ID */
	protected $merchant_id;

	/** @var string the production account user ID */
	protected $user_id;

	/** @var string the production account PIN */
	protected $pin;

	/** @var string the demo account merchant ID */
	protected $demo_merchant_id;

	/** @var string the demo account user ID */
	protected $demo_user_id;

	/** @var string the demo account PIN */
	protected $demo_pin;

	/** @var WC_Elavon_Converge_API the API instance */
	protected $api;

	/** @var string the server IP address hosting this plugin */
	protected $server_ip = '';


	/**
	 * Constructs the gateway.
	 *
	 * @since 2.0.0
	 * @param string $id the gateway ID
	 * @param array $args the gateway args
	 */
	public function __construct( $id, $args ) {

		// set the default args shared across gateways
		$args = wp_parse_args( $args, array(
			'method_description' => __( 'Elavon Converge Payment Gateway provides a seamless and secure checkout process for your customers', 'woocommerce-gateway-elavon' ),
			'supports'           => array(),
			'environments'       => array(
				self::ENVIRONMENT_PRODUCTION => __( 'Production', 'woocommerce-gateway-elavon' ),
				self::ENVIRONMENT_DEMO       => __( 'Demo', 'woocommerce-gateway-elavon' ),
			),
			'shared_settings' => array(
				'merchant_id',
				'user_id',
				'pin',
				'demo_merchant_id',
				'demo_user_id',
				'demo_pin',
			),
		) );

		// add any gateway-specific supports
		$args['supports'] = array_unique( array_merge( $args['supports'], array(
			self::FEATURE_PRODUCTS,
			self::FEATURE_PAYMENT_FORM,
			self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
		) ) );

		parent::__construct( $id, wc_elavon_converge(), $args );

		// reset since it depends on a setting
		$this->order_button_text = $this->get_order_button_text();
	}


	/**
	 * Gets the form fields specific for this method.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_method_form_fields()
	 * @return array
	 */
	protected function get_method_form_fields() {

		return [
			'merchant_id'      => [
				'title'       => __( 'Account ID', 'woocommerce-gateway-elavon' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: Placeholders: %1$s - the site URL in parentheses, %2$s - the server IP address in parentheses */
				 	__( 'To register your site with this gateway account, call Elavon support and ask to add your URL %1$s and IP address %2$s to the allowed list and set your API user with permission to post a session token request.', 'woocommerce-gateway-elavon' ),
					'(<code>' . get_site_url() . '</code>)',
					empty( $this->get_server_ip() ) ? '' : '(<code>' . $this->get_server_ip() . '</code>)'
				),
				'desc_tip'    => __( 'Converge ID/Account ID as provided by Elavon.  This will be six digits long, and start with the number 5 or 6.', 'woocommerce-gateway-elavon' ),
				'class'       => 'environment-field production-field',
			],
			'user_id'          => [
				'title'    => __( 'User ID', 'woocommerce-gateway-elavon' ),
				'type'     => 'text',
				'desc_tip' => __( 'Converge user ID as configured on Converge', 'woocommerce-gateway-elavon' ),
				'class'    => 'environment-field production-field',
			],
			'pin'              => [
				'title'    => __( 'PIN', 'woocommerce-gateway-elavon' ),
				'type'     => 'password',
				'desc_tip' => __( 'Converge PIN as generated within Converge', 'woocommerce-gateway-elavon' ),
				'class'    => 'environment-field production-field',
			],
			'demo_merchant_id' => [
				'title'       => __( 'Demo Account ID', 'woocommerce-gateway-elavon' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: Placeholders: %1$s - the site URL in parentheses, %2$s - the server IP address in parentheses */
				 	__( 'To register your site with this gateway account, call Elavon support and ask to add your URL %1$s and IP address %2$s to the allowed list and set your API user with permission to post a session token request.', 'woocommerce-gateway-elavon' ),
					'(<code>' . get_site_url() . '</code>)',
					empty( $this->get_server_ip() ) ? '' : '(<code>' . $this->get_server_ip() . '</code>)'
				),
				'desc_tip'    => __( 'Converge ID/Account ID as provided by Elavon for your demo account.  This will be six digits long, and start with the number 5 or 6.', 'woocommerce-gateway-elavon' ),
				'class'       => 'environment-field demo-field',
			],
			'demo_user_id'     => [
				'title'    => __( 'Demo User ID', 'woocommerce-gateway-elavon' ),
				'type'     => 'text',
				'desc_tip' => __( 'Converge demo user ID as configured on Converge', 'woocommerce-gateway-elavon' ),
				'class'    => 'environment-field demo-field',
			],
			'demo_pin'         => [
				'title'    => __( 'Demo PIN', 'woocommerce-gateway-elavon' ),
				'type'     => 'password',
				'desc_tip' => __( 'Converge demo PIN as generated within Converge', 'woocommerce-gateway-elavon' ),
				'class'    => 'environment-field demo-field',
			],
		];
	}


	/**
	 * Pings the Elavon server to determine the server's actual IP address as seen by Elavon's servers.
	 *
	 * Falls back to sane defaults if the request fails.
	 *
	 * @since 2.11.3
	 */
	protected function set_server_ip() {

		// perform the request to Elavon's `myip` endpoint
		$response = wp_safe_remote_get( 'https://www.convergepay.com/hosted-payments/myip', [] );

		if ( ! is_wp_error( $response ) && ! empty( $response['body'] ) && is_string( $response['body'] ) ) {
			// set the server IP to response body if no errors encountered
			$this->server_ip = $response['body'];
		} else {
			// set a default server IP to fall back on
			$server_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname();
			if ( is_string( $server_ip ) ) {
				$this->server_ip = $server_ip;
			}
		}
	}


	/**
	 * Gets the server IP of the server hosting this plugin and stores it in a transient for 1 hour.
	 *
	 * Checks to see if the transient is set. If so, it retrieves the transient value.
	 * Otherwise, calls the {@see WC_Gateway_Elavon_Converge::set_server_ip()} method to ping the Elavon server and determine the server IP it detects.
	 *
	 * @since 2.11.3
	 *
	 * @return string the server IP address
	 */
	protected function get_server_ip() : string {

		if ( '' === $this->server_ip ) {

			// set up the transient key
			$transient_key = $this->get_plugin()->get_id() . '_server_ip';

			// get the transient, if it's set
			$this->server_ip = get_transient( $transient_key );

			// check if the transient is set
			if ( false ===  $this->server_ip ) {

				// set the server IP address
				$this->set_server_ip();

				// set the transient for one hour
				set_transient( $transient_key, $this->server_ip, HOUR_IN_SECONDS );
			}
		}

		return $this->server_ip;
	}


	/**
	 * Determines if the gateway is properly configured to perform transactions.
	 *
	 * @see Framework\SV_WC_Payment_Gateway::is_configured()
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_configured() {

		$is_configured = parent::is_configured();

		// missing configuration settings
		if ( ! $this->get_merchant_id() || ! $this->get_user_id() || ! $this->get_pin() ) {
			$is_configured = false;
		}

		return $is_configured;
	}


	/**
	 * Determines whether Checkout.js is enabled.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public function is_checkout_js_enabled() {

		return true;
	}


	/**
	 * Initializes the payment form instance.
	 *
	 * @since 2.6.5
	 *
	 * @return \WC_Elavon_Converge_Payment_Form
	 */
	public function init_payment_form_instance() {

		return new WC_Elavon_Converge_Payment_Form( $this );
	}


	/**
	 * Enables tokenization after a transaction is complete.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function tokenize_after_sale() {
		return true;
	}


	/**
	 * Gets the Checkout.js SDK URL.
	 *
	 * @since 2.8.0
	 *
	 * @return string URL
	 */
	private function get_checkout_js_sdk_url() {

		if ( $this->is_test_environment() ) {
			return 'https://demo.convergepay.com/hosted-payments/Checkout.js';
		}

		return 'https://www.convergepay.com/hosted-payments/Checkout.js';
	}


	/**
	 * Enqueues gateway assets.
	 *
	 * @since 2.6.6
	 */
	protected function enqueue_payment_form_assets() {

		if ( ! is_add_payment_method_page() && ! is_checkout_pay_page() ) {
			return;
		}

		parent::enqueue_payment_form_assets();

		wp_register_script( 'wc-elavon-checkout-js', $this->get_checkout_js_sdk_url(), [], \WC_Elavon_Converge::VERSION );

		$dependencies = [
			'sv-wc-payment-gateway-payment-form-v5_13_0',
			'wc-elavon-checkout-js',
		];

		wp_enqueue_script( 'wc-elavon-payment-form', $this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-elavon-payment-form.min.js', $dependencies, \WC_Elavon_Converge::VERSION );
	}


	/** Checkout.js methods *************************************************/


	/**
	 * Gets the Checkout.js transaction type.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order order object
	 * @param bool $tokenize_payment_method whether to tokenize the payment method
	 * @return string
	 */
	abstract protected function get_checkout_js_transaction_type( \WC_Order $order, $tokenize_payment_method = false );


	/**
	 * Builds a Checkout.js transaction request.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order order object
	 * @param string $transaction_type the transaction type
	 * @return \WC_Elavon_Converge_API_Transaction_Request
	 */
	abstract protected function build_checkout_js_transaction_request( $order, $transaction_type );


	/**
	 * Gets the Checkout.js payment data.
	 *
	 * @since 2.8.0
	 *
	 * @param int $order_id the order ID
	 * @param bool $tokenize_payment_method whether to tokenize the payment method or not
	 * @return array
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_checkout_js_payment_data( $order_id, $tokenize_payment_method ) {

		$order = $this->get_checkout_js_order( $order_id );

		if ( ! $order ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( __( 'Unknown Order ID.', 'woocommerce-gateway-elavon' ) );
		}

		$transaction_type = $this->get_checkout_js_transaction_type( $order, $tokenize_payment_method );
		$request          = $this->build_checkout_js_transaction_request( $order, $transaction_type );

		return $request->get_checkout_js_data();
	}


	/**
	 * Gets the JS args for the Checkout.js handler.
	 *
	 * The args included are:
	 *
	 * - transaction_token_nonce: security nonce for the AJAX request used to get the transaction token
	 * - checkout_js_transaction_id_field_name: name for the Checkout.js transaction ID hidden field
	 * - checkout_js_token_field_name: name for the Checkout.js token hidden field
	 *
	 * @since 2.8.0
	 *
	 * @return array
	 */
	public function get_checkout_js_handler_args() {

		$order = $this->get_checkout_js_order();

		if ( ! $order ) {
			return [];
		}

		return [
			'order_id'                              => $order->get_id(),
			'transaction_token_nonce'               => Transaction_Token_Nonce_Handler::get_nonce( $order->get_id() ),
			'order_requires_payment_upfront'        => $this->order_requires_payment_upfront( $order ),
			'checkout_js_transaction_id_field_name' => $this->get_checkout_js_transaction_id_param_name(),
			'checkout_js_token_field_name'          => $this->get_checkout_js_token_param_name(),
		];
	}


	/**
	 * Gets an order for a Checkout.js transaction.
	 *
	 * This returns the order from the Pay page or an order for an Add Payment Method transaction.
	 * An ID can be specified to get a specific order instead.
	 *
	 * @since 2.8.0
	 *
	 * @param int $order_id optional ID of the order to get
	 * @return \WC_Order|null
	 */
	protected function get_checkout_js_order( $order_id = null ) {

		if ( $order_id ) {
			return wc_get_order( $order_id ) ?: null;
		}

		if ( $order_id = $this->get_checkout_pay_page_order_id() ) {
			return wc_get_order( $order_id ) ?: null;
		}

		// get_order_for_add_payment_method() assumes there is a logged in user
		if ( is_user_logged_in() ) {
			return $this->get_order_for_add_payment_method();
		}

		return null;
	}


	/**
	 * Determines whether a non-zero payment will be processed for this order.
	 *
	 * This value is used in the frontend to decide whether we process a transaction using a
	 * saved card through Checkout.js or submit the selected payment token to be processed in the server.
	 *
	 * If Saved Card Verification is enabled, we try to process transactions with saved cards through
	 * Checkout.js, but we can only do that if the payment amount is not zero.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	protected function order_requires_payment_upfront( \WC_Order $order ) {

		if ( 0.0 === (float) $order->get_total() ) {
			return false;
		}

		// consider adding a filter and moving these verifications to the Pre-Orders integration when Checkout.js becomes the only supported method {WV 2020-11-12}
		if ( class_exists( \WC_Pre_Orders_Order::class ) && \WC_Pre_Orders_Order::order_contains_pre_order( $order->get_id() ) && \WC_Pre_Orders_Order::order_requires_payment_tokenization( $order->get_id() ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Returns true if this is a hosted type gateway.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public function is_hosted_gateway() {

		return true;
	}

	/** @inheritDoc */
	public function hasSeparatePaymentPage() : bool
	{
		return true;
	}


	/**
	 * Renders the payment fields.
	 *
	 * @since 2.8.0
	 */
	public function payment_fields() {

		// render the description of the payment gateway if the current page is the checkout page
		if ( is_checkout() && ! is_checkout_pay_page() ) {

			$description = $this->get_description();

			if ( $description ) {
				echo wpautop( wptexturize( $description ) );
			}

			return;
		}

		parent::payment_fields();
	}


	/**
	 * Outputs the content of the payment page.
	 *
	 * @internal
	 *
	 * @since 2.8.0
	 *
	 * @param int $order_id ID of the order that needs payment
	 */
	public function payment_page( $order_id ) {

		$this->render_payment_page_form( $order_id );
	}


	/**
	 * Outputs the payment form for the Pay page.
	 *
	 * @since 2.8.0
	 *
	 * @param int $order_id ID of the order that needs payment
	 */
	protected function render_payment_page_form( $order_id ) {

		?>
		<form id="order_review" method="post" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<div id="payment">
				<div class="payment_box payment_method_<?php echo esc_attr( $this->get_id() ); ?>">
					<?php $this->get_payment_form_instance()->render(); ?>
				</div>
			</div>
			<button
				type="submit"
				id="place_order"
				class="button alt"
				name="woocommerce_pay"
			><?php esc_html_e( 'Place order', 'woocommerce-gateway-elavon' ); ?></button>
			<input
				type="radio"
				name="payment_method"
				value="<?php echo esc_attr( $this->get_id() ); ?>"
				checked
			/>
			<input
				type="hidden"
				name="woocommerce_pay"
				value=""
			/>
			<?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
		</form>
		<?php
	}


	/**
	 * Validates the payment fields when processing Checkout.
	 *
	 * Overwritten to skip validation if customers are being redirected to the Pay page.
	 *
	 * @see SV_WC_Payment_Gateway_Direct::validate_fields()
	 *
	 * @since 2.8.0
	 *
	 * @return bool true if fields are valid, false otherwise
	 */
	public function validate_fields() {

		if ( $this->should_redirect_customers_to_pay_page() ) {
			return;
		}

		parent::validate_fields();
	}


	/**
	 * Handles payment processing.
	 *
	 * Redirects users to the Pay page if Checkout.js is enabled.
	 *
	 * @see SV_WC_Payment_Gateway_Direct::process_payment()
	 *
	 * @since 2.8.0
	 *
	 * @param int|string $order_id
	 * @return array associative array with members 'result' and 'redirect'
	 */
	public function process_payment( $order_id ) {

		if ( $this->should_redirect_customers_to_pay_page() ) {

			return [
				'result'   => 'success',
				'redirect' => $this->get_order_checkout_payment_url( $order_id ),
			];
		}

		return parent::process_payment( $order_id );
	}


	/**
	 * Determines whether we should redirect customers to the Pay page.
	 *
	 * Returns true unless we are already on the Pay page.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	protected function should_redirect_customers_to_pay_page() {

		return is_checkout() && ! is_checkout_pay_page();
	}


	/**
	 * Gets the URL where the customer can pay for the given order.
	 *
	 * @since 2.8.0
	 *
	 * @param int|string $order_id
	 * @return string
	 */
	protected function get_order_checkout_payment_url( $order_id ) {

		if ( ! $order = wc_get_order( $order_id ) ) {
			return '';
		}

		return $order->get_checkout_payment_url( true );
	}


	/**
	 * Gets a transaction token for Checkout.js.
	 *
	 * @since 2.8.0
	 *
	 * @param string $transaction_type one of the transaction types supported by Checkout.js
	 * @param float $amount the payment amount for the transaction
	 * @return string
	 * @throws Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_checkout_js_transaction_token( $transaction_type, $amount ) {

		$response = $this->get_api()->get_hosted_payments_transaction_token( $transaction_type, $amount );

		return $response->to_string();
	}


	/**
	 * Gets the Checkout.js transaction ID.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	protected function get_checkout_js_transaction_id() {

		return Framework\SV_WC_Helper::get_posted_value( $this->get_checkout_js_transaction_id_param_name() );
	}


	/**
	 * Determines whether a query transaction is necessary.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	protected function should_get_checkout_js_transaction_response() {

		return ! empty( $this->get_checkout_js_transaction_id() );
	}


	/**
	 * Gets the Checkout.js transaction ID request param name.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	protected function get_checkout_js_transaction_id_param_name() {

		return sprintf( 'wc-%s-checkout-js-transaction-id', $this->get_id_dasherized() );
	}


	/**
	 * Gets the payment token param name.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	protected function get_payment_token_param_name() {

		return sprintf( 'wc-%s-payment-token', $this->get_id_dasherized() );
	}


	/**
	 * Gets the Checkout.js token param name.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	protected function get_checkout_js_token_param_name() {

		return sprintf( 'wc-%s-checkout-js-token', $this->get_id_dasherized() );
	}


	/**
	 * Gets the payment token.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	protected function get_payment_token() {

		return Framework\SV_WC_Helper::get_posted_value( $this->get_payment_token_param_name() );
	}


	/**
	 * Gets the Checkout.js payment token.
	 *
	 * @since 2.8.0
	 *
	 * @return string
	 */
	protected function get_checkout_js_token() {

		return Framework\SV_WC_Helper::get_posted_value( $this->get_checkout_js_token_param_name() );
	}


	/**
	 * Gets information about the given transaction ID.
	 *
	 * @since 2.8.0
	 *
	 * @param string $transaction_id the ID of an Elavon Converge transaction.
	 * @param \WC_Order|null optional order object
	 * @return Responses\Transaction_Query
	 * @throws Framework\SV_WC_API_Exception
	 */
	public function get_checkout_js_transaction_response( $transaction_id, $order = null ) {

		return $this->get_api()->query_transaction( $transaction_id, $order );
	}


	/**
	 * Validates the Checkout.js fields.
	 *
	 * @since 2.8.0
	 *
	 * @param bool $is_valid true if the fields are valid, false otherwise
	 * @return bool true if at least a token or transaction ID is set
	 */
	protected function validate_checkout_js_fields( $is_valid ) {

		if ( empty( $this->get_payment_token() ) && empty( $this->get_checkout_js_transaction_id() ) && empty( $this->get_checkout_js_token() ) ) {

			Framework\SV_WC_Helper::wc_add_notice( esc_html__( 'Payment token or transaction ID missing', 'woocommerce-gateway-elavon' ), 'error' );

			return false;
		}

		return $is_valid;
	}


	/**
	 * Validates the transaction query response.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Responses\Transaction_Query $response the transaction query response
	 * @throws Framework\SV_WC_API_Exception
	 */
	protected function validate_checkout_js_transaction_response( WC_Order $order, Responses\Transaction_Query $response ) {

		if ( (string) $order->get_id() !== $response->get_transaction_description() ) {

			throw new Framework\SV_WC_API_Exception( __( "The order ID from the queried transaction does not match the expected order ID.", 'woocommerce-gateway-elavon' ) );
		}
	}


	/**
	 * Adds Checkout.js payment data to the order.
	 *
	 * Gateways may override this method to add gateway-specific data.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Responses\Transaction_Query $response the transaction query response
	 * @return \WC_Order $order the order object
	 */
	protected function add_checkout_js_payment_data_to_order( WC_Order $order, Responses\Transaction_Query $response ) {

		return $order;
	}


	/**
	 * Fetches the transaction and adds the relevant data to the order object
	 * prior to processing the transaction.
	 *
	 * @since 2.8.0
	 *
	 * @param \WC_Order $order the order object
	 * @return Responses\Transaction_Query
	 * @throws Framework\SV_WC_API_Exception
	 */
	protected function do_checkout_js_transaction( \WC_Order $order ) {

		$response = $this->get_checkout_js_transaction_response( $this->get_checkout_js_transaction_id(), $order );

		$this->validate_checkout_js_transaction_response( $order, $response );

		return $response;
	}


	/** Getter methods ******************************************************/


	/**
	 * Gets the order object with gateway payment and transaction data added.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway_Direct::get_order()
	 * @return \WC_Order
	 */
	public function get_order( $order_id ) {

		$order = parent::get_order( $order_id );

		// test amount when in demo mode
		if ( $this->is_test_environment() && ( $test_amount = Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-test-amount' ) ) ) {
			$order->payment_total = Framework\SV_WC_Helper::number_format( $test_amount );
		}

		return $order;
	}


	/**
	 * Gets the API class instance.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_api()
	 * @return \WC_Elavon_Converge_API
	 */
	public function get_api() {

		if ( $this->api instanceof WC_Elavon_Converge_API ) {
			return $this->api;
		}

		$path = wc_elavon_converge()->get_plugin_path() . '/src/api/';

		$files = array(

			// base
			'class-wc-elavon-converge-api',

			// requests
			'requests/abstract-wc-elavon-converge-api-request',
			'requests/abstract-wc-elavon-converge-api-transaction-request',
			'requests/class-wc-elavon-converge-api-credit-card-transaction-request',
			'requests/class-wc-elavon-converge-api-echeck-transaction-request',
			'requests/class-wc-elavon-converge-api-token-request',
			'requests/End_Day_Transaction',
			'requests/Transaction_Token',

			// responses
			'responses/abstract-wc-elavon-converge-api-response',
			'responses/abstract-wc-elavon-converge-api-transaction-response',
			'responses/class-wc-elavon-converge-api-credit-card-transaction-response',
			'responses/class-wc-elavon-converge-api-echeck-transaction-response',
			'responses/class-wc-elavon-converge-api-token-response',
			'responses/Token_Query',
			'responses/Transaction_Query',
			'responses/Transaction_Token',
		);

		foreach ( $files as $file ) {
			require_once( $path . $file . '.php' );
		}

		return $this->api = new WC_Elavon_Converge_API( $this );
	}


	/**
	 * Gets the merchant ID.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_merchant_id() {

		return $this->is_test_environment() ? $this->demo_merchant_id : $this->merchant_id;
	}


	/**
	 * Gets the user ID.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_user_id() {

		return $this->is_test_environment() ? $this->demo_user_id : $this->user_id;
	}


	/**
	 * Gets the PIN.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_pin() {

		return $this->is_test_environment() ? $this->demo_pin : $this->pin;
	}


	/**
	 * Determines if the current gateway environment is configured to 'demo'.
	 *
	 * @since 2.0.0
	 * @see Framework\SV_WC_Payment_Gateway::is_test_environment()
	 * @param string $environment_id optional. the environment ID to check, otherwise defaults to the gateway current environment
	 * @return bool
	 */
	public function is_test_environment( $environment_id = null ) {

		// if an environment is passed in, check that
		if ( ! is_null( $environment_id ) ) {
			return self::ENVIRONMENT_DEMO === $environment_id;
		}

		// otherwise default to checking the current environment
		return $this->is_environment( self::ENVIRONMENT_DEMO );
	}


}
