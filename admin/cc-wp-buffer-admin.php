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
	protected $apiconfig; // Buffer API configuration info
	protected $service; // Array containing the list of services with active accounts
	
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
		
		// Publish post/page hooks
		// Array of statuses from which to hook if status changes to 'publish'
		$oldstatus = array( 'new', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' );
		
		// Iterate through each old status to create a hook
		foreach ( $oldstatus as $status ) {
			add_action( $status . '_to_publish', array( &$this, 'publish_metabox' ) );
		}
		
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
			
			// Set defaults for any Buffer profiles not saved in plugin options
			$this->buffer_profile_defaults();
			
			// Load scripts and styles
			add_action( 'admin_enqueue_scripts', array( &$this, 'metabox_scripts' ) );
			
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
		// Diplay if JavaScript is disabled
		?>
		<noscript>JavaScript must be enabled to use this feature.</noscript>
		<?php
		// Container <div> for meta box contents
		?>
		<div id="<?php echo self::ID; ?>-metabox-contents">
		<?php
		// Add a nonce field to the meta box
		wp_nonce_field( self::PREFIX . 'metabox', self::PREFIX . 'nonce' );
		
		// Get meta box data already saved to post meta
		$postmeta = get_post_meta( $post->ID, '_' . self::PREFIX . 'meta', true );
		
		// Create tabs for each service
		?>
		<ul>
			<?php
			foreach ( $this->profile as $profile ) {
				echo '<li><a href="#' . self::ID . '-' . $profile['service'] . '">' . $this->apiconfig['services'][$profile['service']]['types']['profile']['name'] . '</a></li>';
			}
			?>
		</ul>
		<?php
		
		// Iterate through each profile to create update options form
		foreach ( $this->profile as $profile ) {
			// If the specified profile is enabled in plugin options, make it available in the meta box
			if ( ! empty( $this->options['profiles'][$profile['id']]['enabled'] ) ) {
				// If meta box data is saved in post meta for the specified profile, get the values for that profile
				if ( ! empty( $postmeta[$profile['id']] ) ) {
					$value = $postmeta[$profile['id']];
				}
				// If not, set defaults for the profile
				else {
					$value = array(
						'enabled'	=> 'on',
						'message'	=> $this->options['profiles'][$profile['id']]['message'],
						'schedule'	=> 'buffer',
					);
				}
				
				// Set whether 'enabled' should be checked
				if ( ! empty( $value['enabled'] ) ) {
					$enabled_checked = 'checked';
				}
				else {
					$enabled_checked = null;
				}
				
				// Parse tags in message text
				$value['message'] = $this->parse_tags( $value['message'], $post );
				?>
				<div id="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>">
					<img class="buffer_metabox_profile_avatar" src="<?php echo $profile['avatar_https']; ?>" alt="Avatar for <?php echo $profile['service']; ?> - <?php echo $profile['formatted_username']; ?>"><strong><?php echo $profile['formatted_username']; ?></strong>
					<br />
					<label for="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_enabled" class="selectit">Send to Buffer</label>
					<input type="checkbox" name="<?php echo self::PREFIX; ?>update[<?php echo $profile['id']; ?>][enabled]" id="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_enabled" class="<?php echo self::ID; ?>-metabox-enabled" <?php echo $enabled_checked; ?>>
					<br>
					<label for="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_message" class="selectit">Message</label>
					<br>
					<textarea name="<?php echo self::PREFIX; ?>update[<?php echo $profile['id']; ?>][message]" id="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_message" class="<?php echo self::ID; ?>-metabox-message"><?php echo $value['message']; ?></textarea>
					<br>
					<label for="<?php echo self::PREFIX; ?>update[<?php echo $profile['id']; ?>_schedule" class="selectit">Schedule</label>
					<br>
					<div class="<?php echo self::ID; ?>-metabox-schedule"><input type="radio" name="<?php echo self::PREFIX; ?>update[<?php echo $profile['id']; ?>][schedule]" id="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_schedule_buffer" value="buffer" <?php if ( 'now' != $value['schedule'] && 'top' != $value['schedule'] ) { echo 'checked'; } ?>><label for="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_schedule_buffer">Add to buffer</label></div><div class="<?php echo self::ID; ?>-metabox-schedule"><input type="radio" name="<?php echo self::PREFIX; ?>update[<?php echo $profile['id']; ?>][schedule]" id="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_schedule_top" value="top" <?php if ( 'top' == $value['schedule'] ) { echo 'checked'; } ?>><label for="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_schedule_top">Put at the top of the buffer</label></div><div class="<?php echo self::ID; ?>-metabox-schedule"><input type="radio" name="<?php echo self::PREFIX; ?>update[<?php echo $profile['id']; ?>][schedule]" id="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_schedule_now" value="now" <?php if ( 'now' == $value['schedule'] ) { echo 'checked'; } ?>><label for="<?php echo self::PREFIX; ?>update_<?php echo $profile['id']; ?>_schedule_now">Share immediately when the post is published</label></div>
					
					<input type="hidden" name="<?php echo self::PREFIX; ?>update[<?php echo $profile['id']; ?>][service]" value="<?php echo $profile['service']; ?>">
				</div>
				<?php
			}
		}
		// Close container <div>
		?>
		</div>
		<?php
	} // End add_metabox_callback()
	
	// Process the contents of the meta box
	// @param int $post_id the ID for the post or page
	function save_metabox( $post_id ) {
		// Check to make sure post is not autosave and that the nonce is valid. If any of those conditions fail, exit the script.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! wp_verify_nonce( $_POST[self::PREFIX . 'nonce'], self::PREFIX . 'metabox' ) ) {
			return;
		}
		
		// If profile settings data has been posted, process it
		if ( ! empty( $_POST[self::PREFIX . 'update'] ) ) {
			// Validate and sanitize submitted values
			$updates = $this->validate_metabox( $_POST[self::PREFIX . 'update'] );
			
			// Save the data to post meta
			update_post_meta( $post_id, '_' . self::PREFIX . 'meta', $updates );
		}
	} // End save_metabox()
	
	// Send the post data to Buffer when the post is published
	// @param int $post_id the ID for the post or page
	function publish_metabox( $post ) {
		// If nonce does not validate, exit the script
		if ( ! wp_verify_nonce( $_POST[self::PREFIX . 'nonce'], self::PREFIX . 'metabox' ) ) {
			return;
		}
		
		// If data has been sent from the meta box, validate and sanitize all meta box data
		if ( ! empty( $_POST[self::PREFIX . 'update'] ) ) {
			// Validate and sanitize the meta box values
			$updates = $this->validate_metabox( $_POST[self::PREFIX . 'update'] );
			
			// Get current post meta for Buffer, so we can save to post meta the returned status for each update
			$postmeta = get_post_meta( $post->ID, '_' . self::PREFIX . 'meta', true );
			
			// Send updates to Buffer
			$buffer = $this->api->create_update( $this->options['site_access_token'], $updates, $post );
		}
	} // End publish_metabox
	
	// Validate the values provided in the meta box fields
	// @param mixed $data the data submitted through the meta box
	function validate_metabox( $data ) {
		// Iterate through each profile to validate and sanitize the values
		foreach ( $data as $id => $fields ) {
			// If a value is set for 'enabled', sanitize it
			if ( ! empty( $fields['enabled'] ) ) {
				$data[$id]['enabled'] = 'on';
			}
				
			// Sanitize the message field
			$data[$id]['message'] = sanitize_text_field( $fields['message'] );
			
			// Check schedule selection. If the value is not either 'now' or 'top', set the value to the default of 'buffer'
			if ( 'now' != $fields['schedule'] && 'top' != $fields['schedule'] ) {
				$data[$id]['schedule'] = 'buffer';
			}
		}
		
		return $data;
	} // End validate_metabox()
	
	/** Parse tags used by the plugin to fill in post attributes
	 * @param string $message the text for the Buffer message
	 * @param mixed $post the WordPress post object. Must use $post when calling this method.
	 */
	function parse_tags( $message, $post ) {
		// Array of tags to parse and their corresponding replacement values
		$tags = array(
			'excerpt'	=> $this->api->post_excerpt( $post ),
			'title'		=> $post->post_title,
		);
		
		// Iterate through each tag and parse it if it's present in the message
		foreach ( $tags as $tag => $value ) {
			$message = preg_replace( '/{{' . $tag . '}}/i', $value, $message );
		}
		
		// Return the parsed-out message text
		return $message;
	} // End parse_tags()
	
	// Meta box scripts and styles
	function metabox_scripts() {
		// Load JavaScript
		wp_enqueue_script(
			self::ID . '-metabox', // Handle for the script
			plugins_url( 'admin/assets/js/edit.js', $this->pluginfile ), // Location of the script
			array(
				'jquery',
				'jquery-ui-tabs'
			),
			self::VERSION
		);
		
		//Load stylesheet
		wp_enqueue_style(
			self::ID . '-metabox', // Handle for the script
			plugins_url( 'admin/assets/css/edit.css', $this->pluginfile ), // Location of the stylesheet
			array(),
			self::VERSION
		);
	} // End metabox_scripts()
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
	
	// Set up options page
	function options_init() {
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
				// Set defaults for any Buffer profiles not saved in plugin options
				$this->buffer_profile_defaults();
			
				// Set the array with list of enabled services
				$this->service = $this->services_array( $this->profile );
			
				// If the services array has values, set up the settings
				if ( ! empty( $this->service ) ) {
					// Call the Buffer options
					$this->buffer_options();
					
					foreach ( $this->service as $service ) {
						register_setting(
							self::PREFIX . $service, // The namespace for plugin options fields. This must match settings_fields() used when rendering the form.
							self::PREFIX . 'options', // The name of the plugin options entry in the database.
							array( &$this, 'options_validate' ) // The callback method to validate plugin options
						);
					}
				}
			}
		}
		
		// Load plugin options for Buffer authentication
		$this->auth_options();
		
		// Register the Buffer authentication settings
		register_setting(
			self::PREFIX . 'buffer_auth', // The namespace for plugin options fields. This must match settings_fields() used when rendering the form.
			self::PREFIX . 'options', // The name of the plugin options entry in the database.
			array( &$this, 'options_validate' ) // The callback method to validate plugin options
		);
		
		// Load scripts and stylesheets for the Options page
		add_action( 'admin_enqueue_scripts', array( &$this, 'options_scripts' ) );
	} // End options_init()
	
	/* Buffer Options */
	// Generate plugin options fields from profiles
	function buffer_options() {
		// Iterate through each profile
		foreach ( $this->profile as $profile ) {
			// Add a settings section for each type of social network
			add_settings_section(
				self::PREFIX . $profile['service'], // ID of the section
				null, // Title of the section, unneeded here because it's handled by the tabbed navigation
				null, // Callback for the section - unneeded for this plugin
				self::ID // Page ID for the options page
			);
			
			// Create a settings field to manage each social media profile
			add_settings_field(
				$profile['id'], // Field ID (use the profile ID from Buffer)
				'<img class="buffer_profile_avatar" src="' . $profile['avatar_https'] . '" alt="Avatar for ' . $profile['service'] . ' - ' . $profile['formatted_username'] . '"><span class="buffer_profile_username">' . $profile['formatted_username'] . '</span>', // Field title/label displayed to the user, includes avatar for profile (use the formatted username from Buffer)
				array( &$this, 'buffer_settings_field_callback' ), // Callback method to display the option field
				self::ID, // Page ID for the options page
				self::PREFIX . $profile['service'], // Settings section ID in which to display the field
				$profile // Send all the profile details to the callback method as an argument
			);
		}
	} // End buffer_options()
	
	// Authentication options
	function auth_options() {
		// Options section
		add_settings_section(
			self::PREFIX . 'buffer_auth', // ID of the section
			null, // Title of the section, unneeded here because it's handled by the tabbed navigation
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
				self::PREFIX . 'buffer_auth' // Settings section ID in which to display the field
			);
			
			// Buffer application client secret
			add_settings_field(
				'client_secret', // Field ID
				'Client secret', // Field title/label, displayed to the user
				array( &$this, 'client_secret_callback' ), // Callback method to display the option field
				self::ID, // Page ID for the options page
				self::PREFIX . 'buffer_auth' // Settings section ID in which to display the field
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
					self::PREFIX . 'buffer_auth' // Settings section ID in which to display the field
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
					self::PREFIX . 'buffer_auth' // Settings section ID in which to display the field
				);
			}
		}
	} // End auth_options()
	
	/* Plugin options callbacks */
	// Callback for dynamically generated Buffer settings fields
	// @param array $args arguments passed to the callback from the settings field
	function buffer_settings_field_callback( $args ) {
		// If this profile is enabled in plugin options, check the box
		if ( ! empty( $this->options['profiles'][$args['id']]['enabled'] ) ) {
			$checked = 'checked';
		}
		// If not, leave the box unchecked
		else {
			$checked = null;
		}
		
		// Create checkbox for enabling publishing to this service
		echo '<p>Enabled? <input id="' . self::PREFIX . 'options_profiles_' . $args['id'] . '_enabled" name="' . self::PREFIX . 'options[profiles][' . $args['id'] . '][enabled]" type="checkbox" ' . $checked . '></p>';
		
		// Create text input for post message
		echo '<p>Message <input id="' . self::PREFIX . 'options_profiles_' . $args['id'] . '_message" name="' . self::PREFIX . 'options[profiles][' . $args['id'] . '][message]" type="text" value="' . $this->options['profiles'][$args['id']]['message'] . '" size=40></p>';
	} // End buffer_settings_field_callback()
	/* End plugin options callbacks */
	
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
	
	// Validate plugin options
	function options_validate( $input ) {
		// Set a local variable for the existing plugin options. This is so we don't mix up data.
		$options = $this->options;
		
		// If client ID and client secret were not previously set, check the provided values
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
				foreach ( $profiles as $id => $fields ) {
					// Sanitize the 'enabled' checkbox
					if ( ! empty( $fields['enabled'] ) ) {
						$profiles[$id]['enabled'] = 'on';
					}
					else {
						$profile[$id]['enabled'] = null;
					}
					
					// Sanitize the text input for the 'message' field
					$profiles[$id]['message'] = sanitize_text_field( $fields['message'] );
				}
			
				// Save profiles options
				$options['profiles'] = $profiles;
			}
		}
		
		// Return the validated options
		return $options;
	} // End options_validate()
	
	// Render options page
	function options_page() {
		// Make sure the user has the necessary privileges to manage plugin options
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sorry, you do not have sufficient privileges to access the plugin options for ' . self::NAME . '.' );
		}
		?>
		
		<div class="wrap">
			<h2><?php echo self::NAME; ?></h2>
			
			<?php
			// Check to see if 'tab' is set, and if so get the value
			if ( ! empty( $_GET['tab'] ) ) {
				$active_tab = $_GET['tab'];
			}
			// If 'tab' is not set, default to the first service in the array
			elseif ( empty( $_GET['tab'] ) && ! empty( $this->service ) ) {
				$active_tab = $this->service[0];
			}
			// If neither 'tab' nor the service array are set, default to the Buffer Authentication tab
			else {
				$active_tab = 'buffer_auth';
			}
			?>
			
			<h2 class="nav-tab-wrapper">
			<?php
			// If the service array is set, set up the tabs for the services
			if ( ! empty( $this->service ) ) {
				// Iterate through each service in the array to create each tab
				foreach( $this->service as $service ) {
					?>
					<a href="?page=<?php echo self::ID; ?>&tab=<?php echo $service; ?>" class="nav-tab <?php echo $service == $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo $this->apiconfig['services'][$service]['types']['profile']['name']; ?></a>
					<?php
				}
			}
			?>
				<a href="?page=<?php echo self::ID; ?>&tab=buffer_auth" class="nav-tab <?php echo 'buffer_auth' == $active_tab ? 'nav-tab-active' : ''; ?>">Buffer Authentication</a>
			</h2><!-- .nav-tab-wrapper -->
			
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PREFIX . $active_tab ); // Options page information fields
				do_settings_sections( self::ID ); // Display the section for the current tab
				
				// Show the submit button on any screen other than OAuth authorization
				if ( ! ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) && empty( $this->options['site_access_token'] ) ) ) {
					submit_button(); // Form submit button generated by WordPress
				}
				?>
			</form>
		</div>
		<?php
	} // End options_page()
	
	// Scripts and stylesheets for Options page
	function options_scripts() {
		//Load stylesheet
		wp_enqueue_style(
			self::ID, // Handle for the script
			plugins_url( 'admin/assets/css/options.css', $this->pluginfile ), // Location of the stylesheet
			array(),
			self::VERSION
		);
	}
	/*
	===== End Admin Plugin Options =====
	*/
	
	/*
	===== Admin Notices =====
	*/
	// If the plugin is not fully authenticated and the plugin options page is not the current page, display an admin notice
	function notice_not_auth() {
		if ( ! $this->api->is_site_authenticated() && ( ! isset( $_REQUEST['page'] ) || self::ID != $_REQUEST['page'] ) ) {
			echo '<div class="error"><p><strong>Heads up! If you want to use Buffer with this site, <a href="' . $this->api->optionsurl() . '">' . self::NAME . '</a> needs to be connected.</strong></p></div>';
		}
	} // End notice_not_auth()
	/*
	===== End Admin Notices =====
	*/
	
	/*
	===== Miscellaneous =====
	*/
	/**
	 * Create an array containing the list of services with active accounts. Unique array.
	 * @param array $profiles the array of profiles retrieved from Buffer
	 */
	function services_array( $profiles ) {
		// Initialize the array where the list will be stored
		$profilelist = array();
		
		// Iterate through all the Buffer profiles to get the associated service and add it to the array
		foreach ( $profiles as $profile ) {
			array_push( $profilelist, $profile['service'] );
		}
		
		// Remove duplicate entries from the array
		$profilelist = array_unique( $profilelist );
		
		// Return the array
		return $profilelist;
	}
	/*
	===== Admin Initialization =====
	*/
	// Initialize the admin class
	protected function admin_initialize() {
		// Run plugin upgrade
		$this->upgrade();
		
		// Initialize the plugin API
		$this->api_initialize();
		
		// Get Buffer API configuration info
		$this->apiconfig = $this->api->get_config_info( $this->options['site_access_token'] );
	} // End admin_initialize()
	
	// If a Buffer profile does not have options saved in the database, set default options for the profile
	function buffer_profile_defaults() {
		// Set a local variable for plugin options
		$options = $this->options;
		
		// Get all Buffer profiles for the user account
		$profiles = $this->api->get_profile( $options['site_access_token'] );
		
		// Iterate through each Buffer profile
		foreach ( $profiles as $profile ) {
			// If the profile does not have an entry in plugin options, set defaults for the profile
			if ( empty( $options['profiles'][$profile['id']] ) ) {
				// Set a local variable to store the profile options array
				$profile_options = array();
				
				// Set the default values
				$profile_options['enabled'] = 'on';
				$profile_options['message'] = '{{title}}';
				
				// Update the local plugin options array
				foreach ( $profile_options as $key => $value ) {
					$options['profiles'][$profile['id']][$key] = $value;
				}
			}
		}
		
		// Update plugin options in the database, and update class property if the DB update succeeds
		if ( update_option( self::PREFIX . 'options', $options ) ) {
			$this->options = $options;
		}
	} // End buffer_profile_defaults()
	
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