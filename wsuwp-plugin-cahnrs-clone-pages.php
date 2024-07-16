<?php
/**
 *
 * @wordpress-plugin
 * Plugin Name:       CAHNRS Clone Page Templates
 * Plugin URI:        https://cahnrs.wsu.edu/
 * Description:       Allows super admins to clone page templates from other subsites in the same network. 
 * Version:           1.0.1
 * Author:            CAHNRS Communications
 * Author URI:        https://cahnrs.wsu.edu/
 * Text Domain:       cahnrs-clone-page-templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

//Define the version of this CAHNRS Clone Pages plugin
define( 'CAHNRSCLONEPAGEVERSION', '1.0.1' );

// Gets CAHNRS Clone Pages plugin URL.
function _get_cahnrs_clone_pages_plugin_url() {
    static $cahnrs_clone_pages_plugin_url;
  
    if (empty($cahnrs_clone_pages_plugin_url)) {
      $cahnrs_clone_pages_plugin_url = plugins_url(null, __FILE__);
    }
  
    return $cahnrs_clone_pages_plugin_url;
  }
  
//Load other files of this plugin
function cahnrs_clone_pages_init(){
	require_once __DIR__ . '/includes/plugin.php';
}

add_action( 'plugins_loaded', 'cahnrs_clone_pages_init' );