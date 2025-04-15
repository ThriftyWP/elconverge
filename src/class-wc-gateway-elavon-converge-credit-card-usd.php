<?php
defined('ABSPATH') || exit;

/**
 * USD-specific Elavon Converge Credit Card gateway
 */
class WC_Gateway_Elavon_Converge_Credit_Card_USD extends WC_Gateway_Elavon_Converge_Credit_Card {
    
    /**
     * Constructor with specific ID and currency settings
     */
    public function __construct() {
        // We need to set the ID before calling parent constructor
        $this->id = 'elavon_converge_usd';
        
        // Call parent constructor with specific args
        parent::__construct(
            'elavon_converge_usd',
            array(
                'method_title' => __('Elavon Converge Credit Card (USD)', 'woocommerce-gateway-elavon'),
                'method_description' => __('Accept credit card payments in USD via Elavon Converge.', 'woocommerce-gateway-elavon'),
                'supports' => array(
                    self::FEATURE_CARD_TYPES,
                    self::FEATURE_CREDIT_CARD_CHARGE,
                    self::FEATURE_CREDIT_CARD_AUTHORIZATION,
                    self::FEATURE_CREDIT_CARD_CAPTURE,
                    self::FEATURE_REFUNDS,
                    self::FEATURE_VOIDS,
                    self::FEATURE_TOKENIZATION,
                    self::FEATURE_ADD_PAYMENT_METHOD,
                    self::FEATURE_TOKEN_EDITOR,
                    self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
                ),
            )
        );
        
        // Set currency-specific options
        $this->multi_currency_enabled = 'yes';
        $this->multi_currency_terminal_currency = 'USD';
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
            'description' => __('This gateway is configured for US Dollar (USD) transactions only.', 'woocommerce-gateway-elavon'),
        ];
    }
    
    /**
     * Always return true for multi-currency enabled
     */
    public function is_multi_currency_enabled() {
        return true;
    }
    
    /**
     * Always return USD as the terminal currency
     */
    public function get_multi_currency_terminal_currency() {
        return 'USD';
    }
}
