jQuery( function( $ ) {

	'use strict';

	/**
	 * Elavon Credit Card Form Handler class.
	 *
	 * @since 2.8.0-dev-2
	 */
	window.WC_Elavon_Payment_Form_Handler = class WC_Elavon_Payment_Form_Handler extends SV_WC_Payment_Form_Handler_v5_13_0 {


		/**
		 * Initializes the payment form handler.
		 *
		 * @since 2.8.0
		 */
		constructor( args ) {

			super( args );

			this.ajaxurl                = args.ajaxurl;
			this.debug_mode             = args.debug_mode;

			this.order_id                       = args.order_id;
			this.transaction_token_nonce        = args.transaction_token_nonce !== undefined ? args.transaction_token_nonce : '';
			this.order_requires_payment_upfront = args.order_requires_payment_upfront;
			this.log_event_script_nonce         = args.log_event_script_nonce;

			this.checkout_js_transaction_id_field_name = args.checkout_js_transaction_id_field_name;
			this.checkout_js_token_field_name          = args.checkout_js_token_field_name;

			this.is_terms_and_conditions_enabled = args.is_terms_and_conditions_enabled && this.form.find('#terms').length > 0;

			this.i18n = args.i18n;
		}


		/**
		 * Issues an AJAX request to get a token and returns a promise.
		 *
		 * @since 2.8.0
		 */
		get_transaction_token() {

			return new Promise( ( resolve, reject ) => {

				$.ajax( this.ajaxurl, { data: this.get_transaction_token_data() } ).then(
					( response ) => {

						if ( response.success ) {
							resolve( response.data );
						} else if ( response.data ) {
							reject( [ response.data ] );
						} else {
							reject( [ this.i18n.general_error ] );
						}
					},
					( jqXHR, textStatus, errorThrown ) => {

						console.error( '[Checkout.js]', textStatus, errorThrown );

						reject( [ this.i18n.general_error ] );
					}
				);
			} );
		}


		/**
		 * Gets the data for the Get Transaction Token AJAX request.
		 *
		 * @since 2.8.0
		 *
		 * @return {Object}
		 */
		get_transaction_token_data() {

			let data = {
				action: `wc_${this.plugin_id}_get_transaction_token`,
				security: this.transaction_token_nonce,
				gateway_id: this.id,
				order_id: this.order_id,
				tokenize_payment_method: this.should_tokenize_payment_method(),
				test_amount: this.get_test_amount(),
			};

			if ( this.is_terms_and_conditions_enabled ) {
				data.terms = this.validate_terms_and_conditions_checkbox() ? 'on' : '';
			}

			return data;
		}


		/**
		 * Gets the test amount.
		 *
		 * @since 2.8.0
		 *
		 * @return {string|null}
		 */
		get_test_amount() {

			let value = $( `#wc-${this.id_dasherized}-test-amount` ).val();

			return ( value && value.length ) ? value : null;
		}


		/**
		 * Gets the payment data.
		 *
		 * @since 2.8.0
		 *
		 * @param {object} response The response object
		 * @returns {object}
		 */
		get_payment_data( response ) {

			return {
				... response.payment_data,
				... this.get_payment_method_data(),
				ssl_txn_auth_token: response.transaction_token
			}
		}


		/**
		 * Gets the payment data for the selected payment method (credit card or token).
		 *
		 * @since 2.8.0
		 *
		 * @returns {object}
		 */
		get_payment_method_data() {

			let payment_method_data = {};

			if ( this.get_selected_saved_payment_method() ) {

				payment_method_data = {
					ssl_token: this.get_selected_saved_payment_method(),
				}

			} else {

				payment_method_data = {
					ssl_card_number: this.get_card_number(),
					ssl_exp_date:    this.get_credit_card_exp_date(),
				}
			}

			return {
				... payment_method_data,
				ssl_cvv2cvc2:           this.get_credit_card_csc(),
				ssl_cvv2cvc2_indicator: this.get_credit_card_csc_indicator(),
			}
		}


		/**
		 * Gets the credit card number.
		 *
		 * @since 2.8.0
		 *
		 * @returns {string}
		 */
		get_card_number() {

			let $field = $( `#wc-${this.id_dasherized}-account-number` );

			return $field.val() ? $field.val().replace( /\s/g, '' ) : '';
		}


		/**
		 * Gets the credit card expiry date.
		 *
		 * @since 2.8.0
		 *
		 * @returns {string}
		 */
		get_credit_card_exp_date() {

			let $field = $( `#wc-${this.id_dasherized}-expiry` );

			if ( ! $field.val() ) {
				return '';
			}

			let expiry = $.payment.cardExpiryVal( $field.val() );
			let month  = ( '0' + expiry.month.toString() ).slice( -2 );
			let year   = expiry.year.toString().slice( -2 );

			return month + year
		}


		/**
		 * Gets the credit card security code.
		 *
		 * @since 2.8.0
		 *
		 * @returns {string}
		 */
		get_credit_card_csc() {

			let $field = $( `#wc-${this.id_dasherized}-csc` );

			return $field.val() ? $field.val() : '';
		}


		/**
		 * Gets a value that indicates whether the credit card security code is available.
		 *
		 * Returns 1 if the security is available or 0 otherwise.
		 *
		 * @since 2.8.0
		 *
		 * @returns {int}
		 */
		get_credit_card_csc_indicator() {

			return this.get_credit_card_csc() ? 1 : 0;
		}


		/**
		 * Renders transaction token errors and unblocks the UI.
		 *
		 * @since 2.8.0
		 *
		 * @param {Array} errors A list of errors
		 */
		on_transaction_token_error( errors ) {

			this.render_errors( errors );
			this.unblock_ui();
		}


		/**
		 * Prepares and invokes a payment transaction.
		 *
		 * @since 2.8.0
		 *
		 * @param {object} response The response object
		 */
		on_transaction_token_success( response ) {

			const paymentData = this.get_payment_data( response );

			const callback = {
				onApproval: ( response ) => this.on_transaction_completed( response ),
				onDeclined: ( response ) => this.on_transaction_declined( response ),
				onError: ( error ) => this.on_transaction_error( error )
			};

			ConvergeEmbeddedPayment.pay( paymentData, callback );
		}


		/**
		 * Determines whether the payment method should be tokenized.
		 *
		 * @since 2.8.0
		 *
		 * @returns {boolean}
		 */
		should_tokenize_payment_method() {

			// no need to tokenize payment method if already using saved payment method
			if ( this.get_selected_saved_payment_method() ) {
				return false;
			}

			let $field = $( `#wc-${this.id_dasherized}-tokenize-payment-method` );

			if ( 'checkbox' === $field.prop( 'type' ) ) {
				return $field.prop( 'checked' );
			}

			if ( 'hidden' === $field.prop( 'type' ) ) {
				return !! $field.val();
			}

			return false; // field not found or has unexpected type
		}


		validate_terms_and_conditions_checkbox() {

			if ( this.is_terms_and_conditions_enabled && ! $('#terms').prop( 'checked' ) ) {
				this.render_errors( [ this.i18n.terms_and_conditions_error ] );
				setTimeout( () => this.unblock_ui(), 250 );
				return false;
			}

			return true;
		}


		/**
		 * Validates card data.
		 *
		 * @since 2.8.0
		 */
		validate_card_data() {

			if ( ! this.validate_terms_and_conditions_checkbox() ) {
				return false;
			}

			if ( this.should_use_saved_payment_method() ) {
				return super.validate_card_data();
			}

			// bypass validation if we already have a Checkout.js transaction ID or token
			// this allows the form to submit normally after a completed transaction
			if ( this.is_checkout_js_transaction_complete() ) {
				return true;
			}

			if ( super.validate_card_data() ) {
				this.start_transaction();
			}

			// always return false to prevent form submission
			// the form will be submitted automatically once the Checkout.js transaction is completed
			return false;
		}


		/**
		 * Determines whether the transaction should be processed on the server using a saved payment method.
		 *
		 * @since 2.8.0
		 *
		 * @returns {boolean}
		 */
		should_use_saved_payment_method() {

			/**
			 * Orders that don't require payment upfront cannot be processed on the frontend, even if a saved
			 * payment token was selected and the card security code is required for saved cards.
			 *
			 * Since no authorization or charge is made at first, we should send the selected payment token
			 * to the server to be associated with the order for future payments.
			 */
			if ( this.should_process_payment_upfront() && this.csc_required_for_tokens ) {
				return false;
			}

			return !! this.get_selected_saved_payment_method();
		}


		/**
		 * Determines whether a non-zero payment will be processed for this order.
		 *
		 * @since 2.8.0
		 *
		 * @returns {boolean}
		 */
		should_process_payment_upfront() {

			let test_amount = this.get_test_amount();

			if ( test_amount && test_amount.length > 0 ) {
				return !! parseFloat( test_amount );
			}

			return this.order_requires_payment_upfront;
		}


		/**
		 * Gets the selected saved payment method.
		 *
		 * @since 2.8.0
		 *
		 * @returns {string}
		 */
		get_selected_saved_payment_method() {

			return $( `[name="wc-${this.id_dasherized}-payment-token"]:checked` ).val() || '';
		}


		/**
		 * Determines whether a Checkout.js transaction was already processed.
		 *
		 * @since 2.8.0
		 *
		 * @return {boolean}
		 */
		is_checkout_js_transaction_complete() {

			if ( $( `[name=${this.checkout_js_transaction_id_field_name}]` ).val() ) {
				return true;
			}

			if ( $( `[name=${this.checkout_js_token_field_name}]` ).val() ) {
				return true;
			}

			return false;
		}


		/**
		 * Starts a payment transaction.
		 *
		 * @since 2.8.0
		 */
		start_transaction() {

			if ( 'undefined' === typeof ConvergeEmbeddedPayment ) {

				this.render_errors( [ this.i18n.general_error ] );

				console.error( '[Checkout.js] ConvergeEmbeddedPayment is not defined.' );

				return;
			}

			this.get_transaction_token()
				.then(
					( response ) => this.on_transaction_token_success( response ),
					( errors ) => this.on_transaction_token_error( errors )
				)
		}


		/**
		 * Renders transaction errors and logs the error message.
		 *
		 * @since 2.8.0
		 *
		 * @param {string} error The error message from the response
		 */
		on_transaction_error( error ) {

			this.render_errors( [ error ] );

			this.log_error_response( {
				name:    '[Checkout.js] Transaction error',
				message:  error
			} );

			this.unblock_ui();
		}


		/**
		 * Submits the payment form if a transaction ID or token are present.
		 *
		 * Invoked when a transaction is completed.
		 *
		 * @since 2.8.0
		 *
		 * @param {object} response The response object
		 */
		on_transaction_completed( response ) {

			if ( response.ssl_txn_id ) {
				$( `[name=${this.checkout_js_transaction_id_field_name}]` ).val( response.ssl_txn_id );
			}

			if ( 'GETTOKEN' === response.ssl_transaction_type && response.ssl_token ) {
				$( `[name=${this.checkout_js_token_field_name}]` ).val( response.ssl_token );
			}

			if ( response.ssl_txn_id || response.ssl_token ) {
				this.form.submit();
			}
		}


		/**
		 * Handles declined transactions.
		 *
		 * If the transaction includes an API error it handles it as a transaction error.
		 * If the transaction includes a regular API response, it submits the data to the server for additional processing.
		 *
		 * @since 2.8.0
		 *
		 * @param {object} response The response object
		 */
		on_transaction_declined( response ) {

			// handle completed transactions that return an API error response
			if ( response.errorCode ) {
				return this.on_transaction_error( `[${response.errorCode}] ${response.errorName}: ${response.errorMessage}` );
			}

			return this.on_transaction_completed( response );
		}


		/**
		 * Logs an error to console and records the error in the gateway log if debug mode is enabled.
		 *
		 * @since 2.8.0
		 *
		 * @param {Object} error
		 */
		log_error_response( error ) {

			console.log( error.name, error.message );

			if ( this.debug_mode ) {

				let errorData = {
					action:   'wc_' + this.id + '_payment_form_log_script_event',
					security: this.log_event_script_nonce,
					name:     error.name,
					message:  error.message,
				};

				$.post( this.ajaxurl, errorData, function( response ) {
					if ( ! response || ! response.success ) {
						console.log( response );
					}
				} );
			}
		}


	}

	$( document.body ).trigger( 'wc_elavon_payment_form_handler_loaded' );

} );
