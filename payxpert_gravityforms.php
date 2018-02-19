<?php
/**
 * Plugin Name: Gravity Forms PayXpert Add-On
 * Plugin URI: http://www.gravityforms.com
 * Description: Integrates Gravity Forms with PayXpert, enabling end users to purchase goods and services through Gravity Forms.
 * Version: 1.1.0
 * Author: Payxpert
 * Author URI: http://www.payxpert.com
 * Text Domain: gravityformspayxpert
 * Domain Path: /languages
*/

define( 'GF_PAYXPERT_VERSION', '1.1' );
 
add_action( 'gform_loaded', array( 'GF_Payxpert_Bootstrap', 'load' ), 5 );
 
class GF_Payxpert_Bootstrap {
 
    public static function load() {
 
        if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
            return;
        }
 
        require_once( 'class-gf-payxpert.php' );
 
        GFAddOn::register( 'GFPayxpert' );
    }
 
}
 
function gf_payxpert() {
    return GFPayxpert::get_instance();
}