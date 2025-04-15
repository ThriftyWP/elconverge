<?php
defined('ABSPATH') || exit;

/**
 * CAD-specific Elavon Converge Credit Card gateway
 */
class WC_Gateway_Elavon_Converge_Credit_Card_CAD extends WC_Gateway_Elavon_Converge_Credit_Card {
    
    /**
     * Constructor with specific ID and currency settings
     */
    public function __construct() {
        // IMPORTANT: We need to do direct property assignments rather than 
        // letting the parent constructor handle it
        $this->id = 'elavon_converge_cad';
        $this->title = 'Credit Card (CAD)';
        
        // Call parent constructor without passing the ID parameter
        // This way our ID won't be overwritten
        parent::__construct();
        
        // Re-assign critical properties after parent constructor
        $this->id = 'elavon_converge_cad';
        $this->title = 'Credit Card (CAD)';
        $this->method_title = __('Elavon Converge Credit Card (CAD)', 'woocommerce-gateway-elavon');
        $this->method_description = __('Accept credit card payments in CAD via Elavon Converge.', 'woocommerce-gateway-elavon');
        
        // Set currency-specific options
        $this->multi_currency_enabled = 'yes';
        $this->multi_currency_terminal_currency = 'CAD';
        
        // Make sure settings use our gateway ID
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        error_log('[Elavon CAD] Gateway initialized with ID: ' . $this->id);
        
        // Force gateway to be visible
        $this->update_option('enabled', 'yes');
    }
    
    /**
     * Override is_available to always return true for testing
     */
    public function is_available() {
        error_log('[Elavon CAD] Checking availability');
        return true;
    }   
    
    /**
     * Initialize form fields with currency-specific settings
     */
    public function init_form_fields() {
        // Call parent to get standard fields
        parent::init_form_fields();
        
        // Remove multi-currency options as they're pre-set
        unset($this->form_fields['multi_currency_enabled']);
        unset($this->form_fields['multi_currency_terminal_currency']);
        
        // Add a currency notice
        $this->form_fields['currency_notice'] = [
            'title'       => __('Currency', 'woocommerce-gateway-elavon'),
            'type'        => 'title',
            'description' => __('This gateway is configured for Canadian Dollar (CAD) transactions only.', 'woocommerce-gateway-elavon'),
        ];
    }
    
    /**
     * Always return true for multi-currency enabled
     */
    // public function is_multi_currency_enabled() {
    //     return true;
    // }
    
    /**
     * Always return CAD as the terminal currency
     */
    // public function get_multi_currency_terminal_currency() {
    //     return 'CAD';
    // }

    public function get_option_key() {
        return 'woocommerce_' . $this->id . '_settings';
    }
    
    public function get_field_key( $key ) {
        return $this->id . '_' . $key;
    }
    
    public function init_settings() {
        $this->settings = get_option( $this->get_option_key(), array() );
    }
    
    public function process_admin_options() {
        $this->init_settings(); // Ensures $this->settings is populated
        parent::process_admin_options();
    
        // Also save the settings manually to make sure they're updated
        update_option( $this->get_option_key(), $this->settings );
    }
    
    
}
