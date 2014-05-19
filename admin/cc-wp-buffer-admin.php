<?php
/**
 * Admin elements for the Buffer for WordPress plugin
 * This file contains all functionality for actions within WordPress admin
 * admin/cc-wp-buffer-admin.php
 **/

// Plugin namespace
namespace cconover\buffer;

/**
 * Admin plugin class
 * Extends main plugin class
 **/
class Admin extends Buffer {
	/* Class properties */
	protected $profile; // All the social media profiles associated with site-wide Buffer account
	
	// Class constructor
	function __construct() {
		// Initialize plugin data
		$this->initialize();
		$this->admin_initialize();
		
		/* Hooks and filters */
		add_action( 'admin_menu', array( &$this, 'options_menu' ) ); // Add menu entry to Settings menu
		add_action( 'admin_init', array( &$this, 'options_init' ) ); // Initialize plugin options
		add_action( 'add_meta_boxes', array( &$this, 'add_metabox' ) ); // Add post meta box
		add_action( 'save_post', array( &$this, 'save_metabox' ) ); // Save the contents of the meta box to post meta
		add_action( 'publish_post', array( &$this, 'publish_metabox' ) ); // Send post data to Buffer when the post is published
		/* End hooks and filters */
		
		/* Admin notices */
		add_action( 'admin_notices', array( &$this, 'notice_not_auth' ) ); // If plugin is not fully authenticated
		/* End admin notices */
	} // End __construct()
	
	/*
	===== Post Meta Box =====
	*/
	// Add meta box to post edit screen
	function add_metabox() {
		// Only add the meta box if the site has been authenticated with Buffer
		if ( $this->api->is_site_authenticated() ) {
			// Get Buffer profiles for specified Buffer account
			$this->profile = $this->api->get_profile( $this->options['site_access_token'] );
			
			// Array of post types where the meta box should appear
			$post_types = array( 'post', 'page' );
		
			// Call the WordPress function 'add_meta_box' for each post type where the meta box should appear
			foreach ($post_types as $post_type ) {
				add_meta_box(
					self::ID, // HTML ID of the meta box
					'Send to Buffer', // Title of the meta box, visible to the user
					array( &$this, 'add_metabox_callback' ), // Callback method to display the meta box
					$post_type // Post type where the meta box should be shown
				);
			}
		}
	} // End add_metabox()
	
	// Meta box callback
	function add_metabox_callback( $post ) {
		// Message to display at the top of the meta box
		echo '<p>Use the options below to customize what Buffer receives for this post. When the post is published, this information will be sent to Buffer.</p>';
		
		// Add a nonce field to the meta box
		wp_nonce_field( self::PREFIX . 'metabox', self::PREFIX . 'nonce' );
		
		// Get meta box data already saved to post meta
		$postmeta = get_post_meta( $post->ID, '_' . self::PREFIX . 'meta', true );
		
		// Iterate through each profile
		foreach ( $this->profile as $profile ) {
			// If meta box data is saved in post meta, get the values for the current profile
			if ( ! empty( $postmeta ) ) {
				$value = $postmeta[$profile['id']];
			}
			
			// Set whether 'enabled' should be checked
			if ( ! empty( $value['enabled'] ) ) {
				$enabled_checked = 'checked';
			}
			else {
				$enabled_checked = null;
			}
			?>
			<div id="<?php echo self::PREFIX; ?>profile_<?php echo $profile['id']; ?>">
				<strong><?php echo $profile['formatted_username']; ?></strong>
				<br />
				<label id="label_<?php echo self::PREFIX; ?>profile_<?php echo $profile['id']; ?>" for="<?php echo self::PREFIX; ?>profile[<?php echo $profile['id']; ?>][enabled]" class="selectit">Send to Buffer</label>
				<input type="checkbox" name="<?php echo self::PREFIX; ?>profile[<?php echo $profile['id']; ?>][enabled]" id="<?php echo self::PREFIX; ?>profile_<?php echo $profile['id']; ?>_enabled" <?php echo $enabled_checked; ?>>
				
				<label id="label_<?php echo self::PREFIX; ?>profile_<?php echo $profile['id']; ?>" for="<?php echo self::PREFIX; ?>profile[<?php echo $profile['id']; ?>][message]" class="selectit">Message</label>
				<input type="text" name="<?php echo self::PREFIX; ?>profile[<?php echo $profile['id']; ?>][message]" id="<?php echo self::PREFIX; ?>profile_<?php echo $profile['id']; ?>_message" value="<?php echo $value['message']; ?>">
			</div>
			<?php
		}
	} // End add_metabox_callback()
	
	// Process the contents of the meta box
	function save_metabox( $post_id ) {
		// Check to make sure post is not autosave and that the nonce is valid. If any of those conditions fail, exit the script.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! wp_verify_nonce( $_POST[self::PREFIX . 'nonce'], self::PREFIX . 'metabox' ) ) {
			return;
		}
		
		// If profile settings data has been posted, process it
		if ( ! empty( $_POST[self::PREFIX . 'profile'] ) ) {
			// Set local variable for posted profile data
			$profiles = $_POST[self::PREFIX . 'profile'];
			
			// Iterate through each profile to validate and sanitize the values
			foreach ( $profiles as $id => $fields ) {
				// Expand profile array to access individual values
				foreach ( $fields as $field => $value ) {
					// If a value is set for 'enabled', sanitize it
					if ( ! empty( $fields['enabled'] ) ) {
						$profiles[$id]['enabled'] = 'on';
					}
					
					// Sanitize the message field
					$profiles[$id]['message'] = sanitize_text_field( $fields['message'] );
				}
			}
			
			// Save the data to post meta
			update_post_meta( $post_id, '_' . self::PREFIX . 'meta', $profiles );
		}
	} // End save_metabox()
	
	// Send the post data to Buffer when the post is published
	function publish_metabox( $post_id ) {
		
	} // End publish_metabox
	/*
	===== End Post Meta Box =====
	*/
	
	/*
	===== Admin Plugin Options =====
	*/
	// Create submenu entry under the Settings menu
	function options_menu() {
		add_options_page(
			self::NAME, // Page title. This is displayed in the browser title bar.
			self::NAME, // Menu title. This is displayed in the Settings submenu.
			'manage_options', // Capability required to access the options page for this plugin
			self::ID, // Menu slug
			array( &$this, 'options_page' ) // Function to render the options page
		);
	} // End options_menu()
	
	// Render options page
	function options_page() {
		// Make sure the user has the necessary privileges to manage plugin options
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sorry, you do not have sufficient privileges to access the plugin options for ' . self::NAME . '.' );
		}
		?>
		
		<div class="wrap">
			<h2><?php echo self::NAME; ?></h2>
			
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PREFIX . 'options_fields' ); // Retrieve the fields created for plugin options
				do_settings_sections( self::ID ); // Display the section(s) for the options page
				
				// Show the submit button on any screen other than OAuth authorization
				if ( ! ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) && empty( $this->options['site_access_token'] ) ) ) {
					submit_button(); // Form submit button generated by WordPress
				}
				?>
			</form>
		</div>
		
		<?php
	} // End options_page()
	
	// Set up options page
	function options_init() {
		// Register the plugin settings
		register_setting(
			self::PREFIX . 'options_fields', // The namespace for plugin options fields. This must match settings_fields() used when rendering the form.
			self::PREFIX . 'options', // The name of the plugin options entry in the database.
			array( &$this, 'options_validate' ) // The callback method to validate plugin options
		);
		
		// Load plugin options for Buffer authentication
		$this->auth_options();
		
		/*
		Buffer Profiles
		Buffer uses profiles to store the social media accounts attached to a Buffer account. We will retrieve all
		social media profiles every time the plugin options page is loaded. This will only happen when the following
		conditions are met:
		- Plugin is fully authenticated with Buffer
		- The plugin options page is being displayed
		If the profiles are successfully retrieved, we can display the Buffer options. If not, we'll throw an error
		and no Buffer options will be shown, since we have no data from which to create them.
		*/
		if ( $this->api->is_site_authenticated() && ( isset( $_REQUEST['page'] ) && self::ID == $_REQUEST['page'] ) ) {
			// Get Buffer profiles for specified Buffer account
			$this->profile = $this->api->get_profile( $this->options['site_access_token'] );
			
			// If WordPress returns an error, notify the user
			if ( is_wp_error( $this->profile ) ) {
				echo '<div class="error settings-error"><p><strong>Uh oh! We had a problem getting the social media accounts tied to your Buffer account. Let\'s try again.</strong><br><em>WordPress Error: ' . $this->profile->get_error_message() . '</em></p></div>';
			}
			// If Buffer returns an error, notify the user
			elseif ( ! empty( $this->profile['code'] ) ) {
				echo '<div class="error settings-error"><p><strong>Uh oh! We had a problem getting the social media accounts tied to your Buffer account. Let\'s try again.</strong><br><em>API Error: ' . $this->profile['code'] . ' ' . $this->profile['error'] . '</em></p></div>';
			}
			// Otherwise the profile data is valid, so we can add the Buffer options to the page
			else {
				$this->buffer_options();
			}
		}
	} // End options_init()
	
	// Options for Buffer authorization
	function auth_options() {		
		// Options section
		add_settings_section(
			'auth', // Name of the section
			'Authorization', // Title of the section, displayed on the options page
			array( &$this, 'auth_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
		
		// If the Client ID and Client Secret are not stored in the database, show the fields for those items
		if ( empty( $this->options['client_id'] ) || empty( $this->options['client_secret'] ) ) {
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
		}
		
		// Buffer access token to be used globally for the site (only show if Client ID and Client Secret are saved)
		if ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) ) {
			// If no access token is saved in the database, display a static field label
			if ( empty( $this->options['site_access_token'] ) ) {
				// Add the settings field
				add_settings_field(
					'site_access_token', // Field ID
					'Connect to Buffer', // Field title/label, displayed to the user
					array( &$this, 'site_access_token_callback' ), // Callback method to display the option field
					self::ID, // Page ID for the options page
					'auth' // Settings section in which to display the field
				);
			}
			// If it is set, provide the option to disconnect from Buffer
			else {
				// Add the settings field
				add_settings_field(
					'buffer_oauth_disconnect', // Field ID
					'Disconnect from Buffer', // Field title/label, displayed to the user
					array( &$this, 'buffer_oauth_disconnect_callback' ), // Callback method to display the option field
					self::ID, // Page ID for the options page
					'auth' // Settings section in which to display the field
				);
			}
		}
	} // End auth_options()
	
	/* Buffer Options */
	// Generate plugin options fields from profiles
	function buffer_options() {
		// Iterate through each profile
		foreach ( $this->profile as $profile ) {
			// Add a settings section for each type of social network
			add_settings_section(
				$profile['service'], // Name of the section
				$profile['formatted_service'], // Title of the section, displayed on the options page
				null, // Callback for the section - unneeded for this plugin
				self::ID // Page ID for the options page
			);
			
			// Create a settings field to enable or disable each social media profile
			add_settings_field(
				$profile['id'], // Field ID (use the profile ID from Buffer)
				'<img class="buffer_profile_avatar" src="' . $profile['avatar_https'] . '" alt="Avatar for ' . $profile['formatted_username'] . '">' . $profile['formatted_username'], // Field title/label displayed to the user (use the formatted username from Buffer)
				array( &$this, 'buffer_settings_field_callback' ), // Callback method to display the option field
				self::ID, // Page ID for the options page
				$profile['service'], // Settings section in which to display the field
				$profile // Send all the profile details to the callback method as an argument
			);
		}
	} // End buffer_options()
	
	/* End Buffer Options */
	
	/* Plugin options callbacks */
	// Authorization section
	function auth_callback() {
		// If client ID & secret haven't yet been saved, display this message
		if ( empty( $this->options['client_id'] ) || empty( $this->options['client_secret'] ) ) {
			// Set the callback URL. Do not encode for a URL string.
			$callbackurl = $this->api->optionsurl();
			
			// Display the message
			echo '<p style="color: #E30000; font-weight: bold;">In order to use this plugin, you need to <a href="https://bufferapp.com/developers/apps/create" target="_blank">register it as a Buffer application</a></p><p>It\'s easy! Once you\'ve registered the application, copy the Client ID and Client Secret from the email you receive and paste them here.</p><p><strong>Callback URL</strong>: <a href="' . $callbackurl . '">' . $callbackurl . '</a></p>';
		}
		// If they have been saved, check whether there's an access token. If not, inform the user.
		else {
			if ( empty( $this->options['site_access_token'] ) && empty( $_REQUEST['code'] ) && empty( $_REQUEST['error'] ) ) {
				echo '<div class="updated settings-error"><p><strong>You\'re almost done!</strong><br>Click the button below to authenticate this site with your Buffer account.</p></div>';
			}
		}
	} // End auth_callback()
	
	// Client ID
	function client_id_callback() {
		echo '<input type="text" name="' . self::PREFIX . 'options[client_id]" id="' . self::PREFIX . 'options_client_id" value="' . $this->options['client_id'] . '" size=40>';
	} // End client_id_callback()
	
	// Client secret
	function client_secret_callback() {
		// If client secret is saved in the database, the field is type 'password'. If not, it's type 'text'.
		if ( ! empty( $this->options['client_secret'] ) ) {
			echo '<input type="password" name="' . self::PREFIX . 'options[client_secret]" id="' . self::PREFIX . 'options_client_secret" value="' . $this->options['client_secret'] . '" size=40>';
		}
		else {
			echo '<input type="text" name="' . self::PREFIX . 'options[client_secret]" id="' . self::PREFIX . 'options_client_secret" value="' . $this->options['client_secret'] . '" size=40>';
		}
	} // End client_id_callback()
	
	// Access token
	function site_access_token_callback() {
		// If access token is not set, run the process to retrieve it
		if ( empty( $this->options['site_access_token'] ) ) {
			// Call the OAuth method
			$this->api->buffer_oauth_connect();
		}
	} // End client_id_callback()
	
	// Buffer OAuth disconnect
	function buffer_oauth_disconnect_callback() {
		// Checkbox input field
		echo '<input type="checkbox" name="' . self::PREFIX . 'options[oauth_disconnect]" id="' . self::PREFIX . 'options_oauth_disconnect" value="yes">';
		echo '<p class="description"><strong>WARNING:</strong> checking this box will remove the account credentials for the Buffer user currently associated with this plugin.</p>';
	}
	
	// Callback for dynamically generated Buffer settings fields
	// @param array $args arguments passed to the callback from the settings field
	function buffer_settings_field_callback( $args ) {
		// If this profile is enabled in plugin options, check the box
		if ( ! empty( $this->options['profiles'][$args['id']]['active'] ) ) {
			$checked = 'checked';
		}
		// If not, leave the box unchecked
		else {
			$checked = null;
		}
		
		// Create checkbox for enabling publishing to this service
		echo '<p>Enabled? <input id="' . self::PREFIX . 'options_profiles_' . $args['id'] . '_active" name="' . self::PREFIX . 'options[profiles][' . $args['id'] . '][active]" type="checkbox" value="yes" ' . $checked . '></p>';
		
		// Create text input for post syntax
		echo '<p>Syntax <input id="' . self::PREFIX . 'options_profiles_' . $args['id'] . '_syntax" name="' . self::PREFIX . 'options[profiles][' . $args['id'] . '][syntax]" type="text" value="' . $this->options['profiles'][$args['id']]['syntax'] . '" size=40></p>';
	} // End buffer_settings_field_callback()
	/* End plugin options callbacks */
	
	// Validate plugin options
	function options_validate( $input ) {
		// Set a local variable for the existing plugin options. This is so we don't mix up data.
		$options = $this->options;
		
		// If client ID and client secret have been changed from what's in the database, validate them
		if ( empty( $this->options['client_id'] ) || empty( $this->options['client_secret'] ) ) {
			// Check to make sure whether the provided values are hexadecimal
			if ( ctype_xdigit( $input['client_id'] ) && ctype_xdigit( $input['client_secret'] ) ) {
				$options['client_id'] = $input['client_id']; // Application client ID
				$options['client_secret'] = $input['client_secret']; // Application client secret
			}
			// If either one of them is not hexadecimal, throw an error
			else {
				add_settings_error (
					self::ID, // Setting to which the error applies
					'client-auth', // Identify the option throwing the error
					'Hang on a second! The client ID or client secret you entered doesn\'t match Buffer\'s format. Double-check them both, and take another crack at it.', // Error message
					'error' // The type of message it is
				);
			}
		}
		
		// Access token will only be saved if Client ID and Client Secret are both already saved, but no access token is saved
		if ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) && empty( $this->options['site_access_token'] ) ) {
			// Make sure a value is provided for the access token
			if ( ! empty( $input['site_access_token'] ) ) {
				// Only perform the validation tasks if the value has changed from what's in the database
				if ( $input['site_access_token'] != $this->options['site_access_token'] ) {
					// Query the plugin API to validate the access token
					$apiresult = $this->api->get_user( $input['site_access_token'] );
					
					// If the API returns a user ID, and the user ID is hexadecimal, the access token is valid
					if ( ! empty( $apiresult['id'] ) && ctype_xdigit( $apiresult['id'] ) ) {
						$options['site_access_token'] = $input['site_access_token'];
						$options['site_user_id'] = $apiresult['id'];
						
						// Display a successful message on the next page load
						add_settings_error (
							self::ID, // Setting to which the message applies
							'site-access-token', // Identify the option throwing the message
							'Hooray! Your site is now fully authenticated with Buffer, and you\'re ready to go!', // Success message
							'updated' // The type of message it is
						);
					}
					// If we got an error back from Buffer, notify the user
					elseif ( ! empty( $apiresult['code'] ) ) {
						add_settings_error (
							self::ID, // Setting to which the error applies
							'site-access-token', // Identify the option throwing the error
							'Uh oh! Buffer says that something went wrong. Let\'s give it another shot!<br><em>' . $apiresult['code'] . ' ' . $apiresult['error'] . '</em>', // Error message
							'error' // The type of message it is
						);
					}
					// If the result was a WordPress error, show the error
					elseif ( is_wp_error( $apiresult ) ) {
						add_settings_error (
							self::ID, // Setting to which the error applies
							'site-access-token', // Identify the option throwing the error
							'Uh oh! WordPress had an error.<br><em>' . $apiresult->get_error_message() . '</em>', // Error message
							'error' // The type of message it is
						);
					}
				}
			}
			// If nothing is provided for the access token, throw an error
			else {
				add_settings_error (
					self::ID, // Setting to which the error applies
					'site-access-token', // Identify the option throwing the error
					'Whoops! It looks like you haven\'t yet authenticated with Buffer, and we can\'t continue until that\'s done. Let\'s try again!', // Error message
					'error' // The type of message it is
				);
			}
		}
		
		// If the site is fully authenticated, process the rest of the plugin options
		if ( $this->api->is_site_authenticated() ) {
			// If OAuth Disconnect is selected, remove the Buffer user credentials
			if ( ! empty( $input['oauth_disconnect'] ) ) {
				$options['site_access_token'] = null;
				$options['site_user_id'] = null;
			}
			// If OAuth Disconnect is not set, process the Buffer profile settings
			else {
				// Set local variable for 'profiles' input
				$profiles = $input['profiles'];
				
				// Sanitize the values of the 'enabled' checkboxes
				foreach ( $profiles as $profile ) {
					foreach ( $profile as $key => $value ) {
						if ( ! empty( $profile['active'] ) ) {
							$profile['active'] = 'yes';
						}
						else {
							$profile['active'] = null;
						}
					}
				}
			
				// Save profiles options
				$options['profiles'] = $profiles;
			}
		}
		
		// Return the validated options
		return $options;
	} // End options_validate()
	/*
	===== End Admin Plugin Options =====
	*/
	
	/*
	===== Admin Notices =====
	*/
	// If the plugin is not fully authenticated and the plugin options page is not the current page, display an admin notice
	function notice_not_auth() {
		if ( ! $this->api->is_site_authenticated() && ( ! isset( $_REQUEST['page'] ) || self::ID != $_REQUEST['page'] ) ) {
			echo '<div class="error"><p><strong>Hang on a second! <a href="' . $this->api->optionsurl() . '">' . self::NAME . '</a> needs to be connected to Buffer.</strong></p></div>';
		}
	} // End notice_not_auth()
	/*
	===== End Admin Notices =====
	*/
	
	/*
	===== Admin Initialization =====
	*/
	// Initialize the admin class
	protected function admin_initialize() {
		// Run plugin upgrade
		$this->upgrade();
		
		// Initialize the plugin API
		$this->api_initialize();
	} // End admin_initialize()
	
	// Plugin upgrade
	function upgrade() {
		// Check whether the database-stored plugin version number is less than the current plugin version number, or whether there is no plugin version saved in the database
		if ( ! empty( $this->options['dbversion'] ) && version_compare( $this->options['dbversion'], self::VERSION, '<' ) ) {
			// Set local variable for options (always the first step in the upgrade process)
			$options = $this->options;
			
			/* Update the plugin version saved in the database (always the last step of the upgrade process) */
			// Set the value of the plugin version
			$options['dbversion'] = self::VERSION;
			
			// Save to the database
			update_option( self::PREFIX . 'options', $options );
			/* End update plugin version */
		}
	} // End upgrade()
	/*
	===== End Admin Initialization =====
	*/
	
	/*
	===== Plugin Activation and Deactivation =====
	*/
	// Plugin activation
	public function activate() {
		// Check to make sure the version of WordPress being used is compatible with the plugin
		if ( version_compare( get_bloginfo( 'version' ), self::WPVER, '<' ) ) {
	 		wp_die( 'Your version of WordPress is too old to use this plugin. Please upgrade to the latest version of WordPress.' );
	 	}
	 	
	 	// Default plugin options
	 	$options = array(
	 		'client_id' => null, // Application client ID
	 		'client_secret' => null, // Application client secret
	 		'site_access_token' => null, // Access token, for the Buffer account used to publish posts globally
	 		'site_user_id' => null, // Buffer user ID for the account that will be used for site-level Buffer messages
	 		'profiles' => null, // Social media profile settings
	 		'dbversion' => self::VERSION, // Current plugin version
	 	);
	 	
	 	// Add options to database
	 	add_option( self::PREFIX . 'options', $options );
	} // End activate()
	
	// Plugin deactivation
	public function deactivate() {
		// Remove the plugin options from the database
		delete_option( self::PREFIX . 'options' );
	} // End deactivate
	/*
	===== End Plugin Activation and Deactivation =====
	*/
}
?>