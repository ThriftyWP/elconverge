<?php

namespace SkyVerge\WooCommerce\Elavon_Converge;

use Automattic\WooCommerce\StoreApi\Utilities\RateLimits;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0\SV_WC_Plugin_Compatibility;
use SkyVerge\WooCommerce\PluginFramework\v5_13_0\SV_WC_Plugin_Exception;
use WC_Rate_Limiter;

class Rate_Limiter extends WC_Rate_Limiter
{
	/** @var string Cache group */
	const CACHE_GROUP = 'elavon_rate_limit';


	/**
	 * Gets the action ID, based on teh action name & current user ID or visitor IP.
	 *
	 * @since 2.14.1
	 *
	 * @param string $action_name
	 * @return string
	 */
	public static function get_action_id( string $action_name ): string {

		$action_id = "elavon_{$action_name}_";

		if ( is_user_logged_in() ) {
			$action_id .= get_current_user_id();
		} else {
			$ip_address = self::get_ip_address( true );
			$action_id .= md5( $ip_address );
		}

		return $action_id;
	}


	/**
	 * Note: this method is copied/backported from \Automattic\WooCommerce\StoreApi\Authentication as-is
	 *
	 * Get current user IP Address.
	 *
	 * X_REAL_IP and CLIENT_IP are custom implementations designed to facilitate obtaining a user's ip through proxies, load balancers etc.
	 *
	 * _FORWARDED_FOR (XFF) request header is a de-facto standard header for identifying the originating IP address of a client connecting to a web server through a proxy server.
	 * Note for X_FORWARDED_FOR, Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2.
	 * Make sure we always only send through the first IP in the list which should always be the client IP.
	 * Documentation at https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Forwarded-For
	 *
	 * Forwarded request header contains information that may be added by reverse proxy servers (load balancers, CDNs, and so on).
	 * Documentation at https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Forwarded
	 * Full RFC at https://datatracker.ietf.org/doc/html/rfc7239
	 *
	 * @since 2.14.1
	 *
	 * @param boolean $proxy_support Enables/disables proxy support.
	 * @return string
	 */
	protected static function get_ip_address( bool $proxy_support = false ): string {

		if ( ! $proxy_support ) {
			return self::validate_ip( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unresolved_ip' ) ) );
		}

		if ( array_key_exists( 'HTTP_X_REAL_IP', $_SERVER ) ) {
			return self::validate_ip( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
		}

		if ( array_key_exists( 'HTTP_CLIENT_IP', $_SERVER ) ) {
			return self::validate_ip( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) ) );
		}

		if ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $_SERVER ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			if ( is_array( $ips ) && ! empty( $ips ) ) {
				return self::validate_ip( trim( $ips[0] ) );
			}
		}

		if ( array_key_exists( 'HTTP_FORWARDED', $_SERVER ) ) {
			// Using regex instead of explode() for a smaller code footprint.
			// Expected format: Forwarded: for=192.0.2.60;proto=http;by=203.0.113.43,for="[2001:db8:cafe::17]:4711"...
			preg_match(
				'/(?<=for=)[^;,]*/i', // We catch everything on the first "for" entry, and validate later.
				sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED'] ) ),
				$matches
			);

			if ( strpos( $matches[0] ?? '', '"[' ) !== false ) { // Detect for ipv6, eg "[ipv6]:port".
				preg_match(
					'/(?<=\[).*(?=])/i', // We catch only the ipv6 and overwrite $matches.
					$matches[0],
					$matches
				);
			}

			if ( ! empty( $matches ) ) {
				return self::validate_ip( trim( $matches[0] ) );
			}
		}

		return '0.0.0.0';
	}


	/**
	 * Note: this method is copied/backported from \Automattic\WooCommerce\StoreApi\Authentication as-is
	 *
	 * Uses filter_var() to validate and return ipv4 and ipv6 addresses
	 * Will return 0.0.0.0 if the ip is not valid. This is done to group and still rate limit invalid ips.
	 *
	 * @since 2.14.1
	 *
	 * @param string $ip ipv4 or ipv6 ip string.
	 * @return string
	 */
	protected static function validate_ip( string $ip ): string {
		$ip = filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			array( FILTER_FLAG_NO_RES_RANGE, FILTER_FLAG_IPV6 )
		);

		return $ip ?: '0.0.0.0';
	}


	/**
	 * Applies rate limit to the current action.
	 *
	 * @since 2.14.1
	 * @internal
	 *
	 * @param string $action_name
	 * @return void
	 * @throws SV_WC_Plugin_Exception
	 */
	public static function apply( string $action_name ) {

		$ip_address = self::get_ip_address( true );
		$action_id  = Rate_Limiter::get_action_id( $action_name );

		/**
		 * Filters the rate limiter type to use.
		 *
		 * @since 2.14.1
		 *
		 * @param string $limiter_type 'advanced' or 'basic'. Note that 'advanced' can only be used in WC versions 7.2 and greater
		 */
		$limiter_type = apply_filters( 'woocommerce_elavon_rate_limiter_type', 'advanced' );

		// force basic limiter for older WC versions that don't support the advanced limiter
		if ( SV_WC_Plugin_Compatibility::is_wc_version_lt( '7.2.0' ) ) {
			$limiter_type = 'basic';
		}

		if ( $limiter_type === 'advanced' ) {
			self::apply_advanced_limit( $action_id, $ip_address );
		} else {
			self::apply_basic_limit( $action_id, $ip_address );
		}
	}


	/**
	 * Applies advanced rate limiting to the request.
	 *
	 * @since 2.14.1
	 *
	 * @param string $action_id
	 * @param string $ip_address
	 * @return void
	 * @throws SV_WC_Plugin_Exception
	 */
	private static function apply_advanced_limit( string $action_id, string $ip_address ) {

		$filter_options = function ( array $options ) {

			$options['enabled']       = true;
			$options['proxy_support'] = true;
			$options['limit']         = 3;
			$options['seconds']       = 45;

			/**
			 * Filters the advanced rate limit options.
			 *
			 * @since 2.14.1
			 *
			 * @param array $rate_limit_options Array of option values.
			 */
			return apply_filters( 'woocommerce_elavon_rate_limit_options', $options );
		};

		add_filter( 'woocommerce_store_api_rate_limit_options', $filter_options );

		$options = RateLimits::get_options();

		// allow merchants to disable the rate limit if needed
		if ( ! $options->enabled ) {
			return;
		}

		$retry = RateLimits::is_exceeded_retry_after( $action_id );

		if ( false !== $retry ) {
			self::handle_limit_exceeded( $retry, $ip_address );
		}

		RateLimits::update_rate_limit( $action_id );

		remove_filter( 'woocommerce_store_api_rate_limit_options', $filter_options );
	}


	/**
	 * Applies basic rate limiting to the request.
	 *
	 * @since 2.14.1
	 *
	 * @param string $action_id
	 * @param string $ip_address
	 * @return void
	 * @throws SV_WC_Plugin_Exception
	 */
	private	static function apply_basic_limit( string $action_id, string $ip_address ) {

		/**
		 * Filters the basic rate limit delay.
		 *
		 * @since 2.14.1
		 *
		 * @param int $delay The delay between requests, defaults to 15.
		 */
		$delay = (int) apply_filters( 'woocommerce_elavon_rate_limit_delay', 15 );

		if ( Rate_Limiter::retried_too_soon( $action_id ) ) {
			self::handle_limit_exceeded( $delay, $ip_address );
		}

		Rate_Limiter::set_rate_limit( $action_id, $delay );
	}


	/**
	 * Handles when the rate limit is exceeded.
	 *
	 * @since 2.14.1
	 *
	 * @param int $delay
	 * @param string $ip_address
	 * @throws SV_WC_Plugin_Exception
	 */
	private static function handle_limit_exceeded( int $delay, string $ip_address ) {

		/**
		 * Fires when the rate limit is exceeded.
		 *
		 * @since 2.14.1
		 *
		 * @param string $ip_address The IP address of the request.
		 */
		do_action( 'woocommerce_elavon_rate_limit_exceeded', $ip_address );

		$user_id = get_current_user_id();

		wc_elavon_converge()->log( sprintf( __( 'Transaction token endpoint rate limit exceeded for %s', 'woocommerce-gateway-elavon' ), implode(', ', array_filter([
			$user_id ? sprintf( __( 'User ID: %d', 'woocommerce-gateway-elavon' ), $user_id ) : null,
			sprintf( __( 'IP Address: %s', 'woocommerce-gateway-elavon' ), $ip_address )
		]))));

		$message = implode(' ', [
			__( 'You cannot request a transaction token so soon after the previous one.', 'woocommerce-gateway-elavon' ),
			sprintf(
			/* translators: %d number of seconds */
				_n(
					'Please wait for %d second.',
					'Please wait for %d seconds.',
					$delay,
					'woocommerce-gateway-elavon'
				),
				$delay
			)
		]);

		throw new SV_WC_Plugin_Exception( $message );
	}


}
