<?php
/**
 * Admin elements for the Buffer for WordPress plugin
 * admin/wp-buffer-admin.php
 **/

/**
 * Admin plugin class
 **/
class cc_buffer_admin extends cc_buffer {
	// Class constructor
	function __construct() {
		// Initialize
		$this->initialize();
		
		/* Hooks and filters */
		add_action( 'admin_menu', array( &$this, 'create_options_menu' ) ); // Add menu entry to Settings menu
		/* End hooks and filters */
	} // End __construct()
	
	/*
	Plugin options
	Uses WordPress Options API
	*/
	// Create submenu entry under the Settings menu
	function create_options_menu() {
		add_options_page(
			self::NAME, // Page title. This is displayed in the browser title bar.
			self::NAME, // Menu title. This is displayed in the Settings submenu.
			'manage_options', // Capability required to access the options page for this plugin
			self::ID, // Menu slug
			array( &$this, 'options_page' ) // Function to render the options page
		);
	} // End create_options_menu()
	/* End plugin options */
}
?>