<?php
/**
 * Plugin Name: Buffer for WordPress
 * Plugin URI: https://christiaanconover.com/code/wp-buffer?ref=plugin-data
 * Description: Buffer for WordPress allows you to use Buffer to share your posts and pages with social media, using their suite of sharing features.
 * Version: 0.1.0-alpha1
 * Author: Christiaan Conover
 * Author URI: https://christiaanconover.com?ref=wp-buffer-plugin-author-uri
 * License: GPLv2
 * @package cc-wp-buffer
 **/

// Plugin namespace
namespace cconover\buffer;

/**
 * Main plugin class
 **/
class Buffer {
	/* Plugin constants */
	const ID = 'cc-wp-buffer'; // Plugin ID
	const NAME = 'Buffer for WordPress'; // Plugin name
	const VERSION = '0.1.0-alpha1'; // Plugin version
	const WPVER = '3.6'; // Minimum version of WordPress required for this plugin
	const PREFIX = 'cc_wp_buffer_'; // Plugin database prefix
	/* End plugin constants */
	
	/* Plugin properties */
	protected $options = array(); // Plugin options
	protected $api; // Contains the plugin API object
	protected $pluginpath; // Plugin directory path
	protected $pluginfile; // Plugin file path
	protected $classpath; // Path to class includes directory
	/* End plugin properties */
	
	// Class constructor
	function __construct() {
		// Plugin initialization
		$this->initialize();
		$this->api_initialize();
		
		/* Admin elements (only loaded if in admin) */
		if ( is_admin() ) {
			require_once( $this->pluginpath . '/admin/' . self::ID . '-admin.php' ); // Require the file containing the plugin's admin class
			$admin = new \cconover\buffer\Admin; // Create new admin object
			
			// Admin hooks and filters to be loaded by the main plugin class
			register_activation_hook( $this->pluginfile, array( &$admin, 'activate' ) ); // Plugin activation
			register_deactivation_hook( $this->pluginfile, array( &$admin, 'deactivate' ) ); // Plugin deactivation
		}
		/* End admin elements */
	} // End __construct()
	
	/* Plugin initialization methods */
	// Initialize the plugin
	protected function initialize() {
		// Set $wpdb object to global
		global $wpdb;
		
		// Get plugin options
		$this->get_options();
		
		// Set plugin directory and file paths
		$this->pluginpath = dirname( __FILE__ ); // Plugin directory path
		$this->pluginfile = __FILE__; // Plugin file path
		
		// Set path to class includes directory
		$this->classpath = $this->pluginpath . '/includes/class/';
	}
	
	// Initialize the plugin API
	protected function api_initialize() {
		require_once( $this->classpath . 'Api.php' );
		$this->api = new \cconover\buffer\Api;
	}
	
	// Get plugin options from the database
	protected function get_options() {
		$this->options = get_option( self::PREFIX . 'options' );
	}
	/* End plugin initialization methods */
}
/**
 * End main plugin class
 **/

// Create plugin object in the global space
global $cc_wp_buffer;
$cc_wp_buffer = new \cconover\buffer\Buffer;
?>