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
	===== Admin Plugin Options =====
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
		
		// Default post settings
		add_settings_section(
			'posts', // Name of the section
			'Posts', // Title of the section, displayed on the options page
			array( &$this, 'posts_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
		
		// Default page settings
		add_settings_section(
			'pages', // Name of the section
			'Pages', // Title of the section, displayed on the options page
			array( &$this, 'pages_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
		
		// Default scheduling settings
		add_settings_section(
			'schedule', // Name of the section
			'Schedule', // Title of the section, displayed on the options page
			array( &$this, 'schedule_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
	} // End set_options_sections()
	
	// Set up options fields
	function set_options_fields() {
		// Buffer application client ID
		add_settings_field(
			'client_id', // Field ID
			'Client ID', // Field title/label, displayed to the user
			array( &$this, 'client_id_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'auth' // Settings section in which to display the field
		);
		
		// Buffer application client secret
		add_settings_field(
			'client_secret', // Field ID
			'Client secret', // Field title/label, displayed to the user
			array( &$this, 'client_secret_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'auth' // Settings section in which to display the field
		);
		
		// Syntax of Buffer message for publishing posts
		add_settings_field(
			'post_publish_syntax', // Field ID
			'Publish Post', // Field title/label, displayed to the user
			array( &$this, 'post_publish_syntax_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'posts' // Settings section in which to display the field
		);
		
		// Social media networks to which Buffer should send posts
		add_settings_field(
			'post_networks', // Field ID
			'Social Media', // Field title/label, displayed to the user
			array( &$this, 'post_networks_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'posts' // Settings section in which to display the field
		);
		
		// Syntax of Buffer message for publishing pages
		add_settings_field(
			'page_publish_syntax', // Field ID
			'Publish Page', // Field title/label, displayed to the user
			array( &$this, 'page_publish_syntax_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'pages' // Settings section in which to display the field
		);
		
		// Social media networks to which Buffer should send pages
		add_settings_field(
			'page_networks', // Field ID
			'Social Media', // Field title/label, displayed to the user
			array( &$this, 'page_networks_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'pages' // Settings section in which to display the field
		);
		
		// Publishing schedule
		add_settings_field(
			'schedule', // Field ID
			'Buffer Schedule', // Field title/label, displayed to the user
			array( &$this, 'schedule_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'schedule' // Settings section in which to display the field
		);
	} // End set_options_fields()
	
	// Validate plugin options
	function options_validate() {
		
	} // End options_validate()
	/*
	===== End Admin Plugin Options =====
	*/
	
	/*
	===== Plugin Activation and Deactivation =====
	*/
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
	 		'schedule' => NULL // Default Buffer push schedule
	 	);
	 	
	 	// Add options to database
	 	add_option( $this->prefix . 'options', $options );
	} // End activate()
	
	// Plugin deactivation
	public function deactivate() {
		// Remove the plugin options from the database
		delete_option( $this->prefix . 'options' );
	} // End deactivate
	/*
	===== End Plugin Activation and Deactivation =====
	*/
}
?>