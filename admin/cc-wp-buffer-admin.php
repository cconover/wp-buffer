<?php
/**
 * Admin elements for the Buffer for WordPress plugin
 * admin/cc-wp-buffer-admin.php
 **/

/**
 * Admin plugin class
 * Extends main plugin class
 **/
class cc_wp_buffer_admin extends cc_wp_buffer {
	// Class constructor
	function __construct() {
		// Initialize
		$this->initialize();
		
		/* Hooks and filters */
		add_action( 'admin_menu', array( &$this, 'create_options_menu' ) ); // Add menu entry to Settings menu
		add_action( 'admin_init', array( &$this, 'set_options_init' ) ); // Initialize plugin options
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
	
	// Render options page
	function options_page() {
		
	} // End options_page()
	
	// Set up options page
	function set_options_init() {
		// Register the plugin settings
		register_setting(
			$this->prefix . 'options_fields', // The namespace for plugin options fields. This must match settings_fields() used when rendering the form.
			$this->prefix . 'options', // The name of the plugin options entry in the database.
			array( &$this, 'options_validate' ) // The callback method to validate plugin options
		);
		
		// Load options page sections
		$this->set_options_sections();
		
		// Load options fields
		$this->set_options_fields();
	} // End set_options_init()
	
	// Set up options page sections
	function set_options_sections() {
		// Authorize plugin with Buffer
		add_settings_section(
			'auth', // Name of the section
			'Authorization', // Title of the section, displayed on the options page
			array( &$this, 'auth_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
		
		// Default post/page settings
		add_settings_section(
			'postpage', // Name of the section
			'Post/Page Settings', // Title of the section, displayed on the options page
			array( &$this, 'postpage_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
		
		// Default scheduling settings
		add_settings_section(
			'schedule', // Name of the section
			'Schedule', // Title of the section, displayed on the options page
			array( &$this, 'postpage_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
	} // End set_options_sections()
	
	// Set up options fields
	function set_options_fields() {
		
	} // End set_options_fields()
	
	// Validate plugin options
	function options_validate() {
		
	} // End options_validate()
	/* End plugin options */
	
	/* Plugin activation and deactivation */
	// Plugin activation
	public function activate() {
		// Check to make sure the version of WordPress being used is compatible with the plugin
		if ( version_compare( get_bloginfo( 'version' ), self::VERSION, '<' ) ) {
	 		wp_die( 'Your version of WordPress is too old to use this plugin. Please upgrade to the latest version of WordPress.' );
	 	}
	 	
	 	// Default plugin options
	 	$options = array(
	 		'client_id' => NULL, // Application client ID
	 		'client_secret' => NULL, // Application client secret
	 		'post_publish_syntax' => 'New Post: {title} {url}', // Syntax of Buffer message when a post is published
	 		'post_update_synxat' => 'Updated Post: {title} {url}', // Syntax of Buffer message when a post is updated
	 		'post_networks' => NULL, // Social networks Buffer should push to when a post is published/updated
	 		'page_publish_syntax' => 'New Page: {title} {url}', // Syntax of Buffer message when a post is published
	 		'page_update_synxat' => 'Updated Page: {title} {url}', // Syntax of Buffer message when a post is updated
	 		'page_networks' => NULL, // Social networks Buffer should push to when a post is published/updated
	 	);
	 	
	 	// Add options to database
	 	add_option( $this->prefix . 'options', $options );
	} // End activate()
	
	// Plugin deactivation
	public function deactivate() {
		// Remove the plugin options from the database
		delete_option( $this->prefix . 'options' );
	} // End deactivate
	/* End plugin activation and deactivation */
}
?>