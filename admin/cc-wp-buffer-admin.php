<?php
/**
 * Admin elements for the Buffer for WordPress plugin
 * admin/cc-wp-buffer-admin.php
 **/

// Plugin namespace
namespace cconover\buffer;

/**
 * Admin plugin class
 * Extends main plugin class
 **/
class Admin extends Buffer {
	// Class constructor
	function __construct() {
		// Initialize
		$this->initialize();
		$this->admin_initialize();
		$this->api_initialize();
		
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
		// Make sure the user has the necessary privileges to manage plugin options
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sorry, you do not have sufficient privileges to access the plugin options for ' . self::NAME . '.' );
		}
		?>
		
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo self::NAME; ?></h2>
			
			<form action="options.php" method="post">
				<?php
				settings_fields( $this->prefix . 'options_fields' ); // Retrieve the fields created for plugin options
				do_settings_sections( self::ID ); // Display the section(s) for the options page
				submit_button(); // Form submit button generated by WordPress
				?>
			</form>
		</div>
		
		<?php
	} // End options_page()
	
	// Set up options page
	function set_options_init() {
		// Register the plugin settings
		register_setting(
			$this->prefix . 'options_fields', // The namespace for plugin options fields. This must match settings_fields() used when rendering the form.
			$this->prefix . 'options', // The name of the plugin options entry in the database.
			array( &$this, 'options_validate' ) // The callback method to validate plugin options
		);
		
		// Load plugin options for authorization
		$this->auth_options();
		
		// If the application has been fully authenticated with Buffer, load the rest of the options
		if ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) && ! empty( $this->options['site_access_token'] ) ) {
			// Options page sections and fields
			$this->set_options_sections();
			$this->set_options_fields();
		}
	} // End set_options_init()
	
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
			add_settings_field(
				'site_access_token', // Field ID
				'Access Token', // Field title/label, displayed to the user
				array( &$this, 'site_access_token_callback' ), // Callback method to display the option field
				self::ID, // Page ID for the options page
				'auth' // Settings section in which to display the field
			);
		}
	} // End auth_options()
	
	// Set up options page sections
	function set_options_sections() {		
		// Twitter settings
		add_settings_section(
			'twitter', // Name of the section
			'Twitter', // Title of the section, displayed on the options page
			array( &$this, 'twitter_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
		
		// Facebook settings
		add_settings_section(
			'fb', // Name of the section
			'Facebook', // Title of the section, displayed on the options page
			array( &$this, 'fb_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
		
		// LinkedIn settings
		add_settings_section(
			'linkedin', // Name of the section
			'LinkedIn', // Title of the section, displayed on the options page
			array( &$this, 'linkedin_callback' ), // Callback method to display plugin options
			self::ID // Page ID for the options page
		);
	} // End set_options_sections()
	
	// Set up options fields
	function set_options_fields() {
		// Enable Twitter
		add_settings_field(
			'twitter_send', // Field ID
			'Send Posts to Twitter', // Field title/label, displayed to the user
			array( &$this, 'twitter_send_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'twitter' // Settings section in which to display the field
		);
		
		// Default syntax for Twitter messages
		add_settings_field(
			'twitter_publish_syntax', // Field ID
			'Tweet Format', // Field title/label, displayed to the user
			array( &$this, 'twitter_publish_syntax_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'twitter' // Settings section in which to display the field
		);
		
		// Schedule for Twitter messsages
		add_settings_field(
			'twitter_schedule', // Field ID
			'Schedule for Tweets', // Field title/label, displayed to the user
			array( &$this, 'twitter_schedule_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'twitter' // Settings section in which to display the field
		);
		
		// Enable Facebook
		add_settings_field(
			'fb_send', // Field ID
			'Send Posts to Facebook', // Field title/label, displayed to the user
			array( &$this, 'fb_send_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'fb' // Settings section in which to display the field
		);
		
		// Default syntax for Facebook messages
		add_settings_field(
			'fb_publish_syntax', // Field ID
			'Facebook Post Format', // Field title/label, displayed to the user
			array( &$this, 'fb_publish_syntax_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'fb' // Settings section in which to display the field
		);
		
		// Enable LinkedIn
		add_settings_field(
			'twitter_send', // Field ID
			'Send Posts to LinkedIn', // Field title/label, displayed to the user
			array( &$this, 'linkedin_send_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'linkedin' // Settings section in which to display the field
		);
		
		// Default syntax for LinkedIn messages
		add_settings_field(
			'linkedin_publish_syntax', // Field ID
			'LinkedIn Post Format', // Field title/label, displayed to the user
			array( &$this, 'linkedin_publish_syntax_callback' ), // Callback method to display the option field
			self::ID, // Page ID for the options page
			'linkedin' // Settings section in which to display the field
		);
	} // End set_options_fields()
	
	/* Plugin options callbacks */
	// Authorization section
	function auth_callback() {
		// If client ID & secret haven't yet been saved, display this message
		if ( empty( $this->options['client_id'] ) || empty( $this->options['client_secret'] ) ) {
			echo '<p style="color: #E30000; font-weight: bold;">In order to use this plugin, you need to <a href="https://bufferapp.com/developers/apps/create" target="_blank">register it as a Buffer application</a></p><p>Don\'t worry, I\'ll walk you through it. Once you\'ve registered the application, copy the Client ID and Client Secret from the email you receive and paste them here.</p><p><strong>Callback URL</strong>: NEEDS TO BE SET</p>';
		}
		// If they have been saved, check whether there's an access token and display the appropriate message
		else {
			if ( ! empty( $this->options['site_access_token'] ) ) {
				echo '<p style="color: #199E22;">This site is fully authenticated with Buffer. Have fun!</p>';
			}
			else {
				echo '<p style="color: #F08C00;">You\'re almost done! Copy the access token for the <a href="https://bufferapp.com/developers/apps" target="_blank">application you just registered</a> and paste it below.</p>';
			}
		}
	} // End auth_callback()
	
	// Twitter section
	function twitter_callback() {
		
	} // End twitter_callback()
	
	// Facebook section
	function fb_callback() {
		
	} // End fb_callback()
	
	// LinkedIn section
	function linkedin_callback() {
		
	} // End linkedin_callback()
	
	// Client ID
	function client_id_callback() {
		?>
		<input type="text" name="<?php echo $this->prefix; ?>options[client_id]" id="<?php echo $this->prefix; ?>options[client_id]" value="<?php echo $this->options['client_id']; ?>" size=40>
		<?php
	} // End client_id_callback()
	
	// Client secret
	function client_secret_callback() {
		// If client secret is saved in the database, the field is type 'password'. If not, it's type 'text'.
		if ( ! empty( $this->options['client_secret'] ) ) {
			echo '<input type="password" name="' . $this->prefix . 'options[client_secret]" id="' . $this->prefix . 'options[client_secret]" value="' . $this->options['client_secret'] . '" size=40>';
		}
		else {
			echo '<input type="text" name="' . $this->prefix . 'options[client_secret]" id="' . $this->prefix . 'options[client_secret]" value="' . $this->options['client_secret'] . '" size=40>';
		}
	} // End client_id_callback()
	
	// Access token
	function site_access_token_callback() {
		// Show text input to provide access token
		echo '<input type="text" name="' . $this->prefix . 'options[site_access_token]" id="' . $this->prefix . 'options[site_access_token]" value="' . $this->options['site_access_token'] . '" size=40>';
	} // End client_id_callback()
	
	// Enable Twitter
	function twitter_send_callback() {
		// Determine whether checkbox should be checked
		if ( $this->options['twitter_send'] == 'yes' ) {
			$checked = 'checked';
		}
		else {
			$checked = null;
		}
		echo '<input type="checkbox" name ="' . $this->prefix . 'options[twitter_send]" id="' . $this->prefix . 'options[twitter_send]" value="yes" ' . $checked . '>';
	} // End twitter_send_callback()
	
	// Twitter syntax
	function twitter_publish_syntax_callback() {
		echo '<input type="text" name="' . $this->prefix . 'options[twitter_publish_syntax]" id="' . $this->prefix . 'options[twitter_publish_syntax]" value="' . $this->options['twitter_publish_syntax'] . '" size=40><br />';
		echo '<span class="description">Available tags: {title}, {url}</span>';
	} // End twitter_publish_syntax_callback()
	
	// Twitter schedule
	function twitter_schedule_callback() {
		// Set up input field for number of additional tweets to send
		$numposts = '<input type="text" name="' . $this->prefix . 'options[twitter_post_number]" id="' . $this->prefix . 'options[twitter_post_number]" value="' . $this->options['twitter_post_number'] . '" size=1>';
		
		// Set up input field for interval (in hours) between tweets
		$interval = '<input type="text" name="' . $this->prefix . 'options[twitter_post_interval]" id="' . $this->prefix . 'options[twitter_post_interval]" value="' . $this->options['twitter_post_interval'] . '" size=1>';
		
		// Show the options fields with explanation
		echo '<p>Send ' . $numposts . ' additional tweets, spaced ' . $interval . ' hours apart</p>';
		echo '<span class="description">A tweet will automatically be sent when a post is published. This setting lets you schedule follow-up tweets to be sent by Buffer.</span>';
	} // End twitter_schedule_callback()
	
	// Enable Facebook
	function fb_send_callback() {
		// Determine whether checkbox should be checked
		if ( $this->options['fb_send'] == 'yes' ) {
			$checked = 'checked';
		}
		else {
			$checked = null;
		}
		echo '<input type="checkbox" name ="' . $this->prefix . 'options[fb_send]" id="' . $this->prefix . 'options[fb_send]" value="yes" ' . $checked . '>';
	} // End fb_send_callback()
	
	// Facebook syntax
	function fb_publish_syntax_callback() {
		echo '<input type="text" name="' . $this->prefix . 'options[fb_publish_syntax]" id="' . $this->prefix . 'options[fb_publish_syntax]" value="' . $this->options['fb_publish_syntax'] . '" size=40><br />';
		echo '<span class="description">Available tags: {title}, {url}</span>';
	} // End fb_publish_syntax_callback()
	
	// Enable LinkedIn
	function linkedin_send_callback() {
		// Determine whether checkbox should be checked
		if ( $this->options['linkedin_send'] == 'yes' ) {
			$checked = 'checked';
		}
		else {
			$checked = null;
		}
		echo '<input type="checkbox" name ="' . $this->prefix . 'options[linkedin_send]" id="' . $this->prefix . 'options[linkedin_send]" value="yes" ' . $checked . '>';
	} // End linkedin_send_callback()
	
	// LinkedIn syntax
	function linkedin_publish_syntax_callback() {
		echo '<input type="text" name="' . $this->prefix . 'options[linkedin_publish_syntax]" id="' . $this->prefix . 'options[linkedin_publish_syntax]" value="' . $this->options['linkedin_publish_syntax'] . '" size=40><br />';
		echo '<span class="description">Available tags: {title}, {url}</span>';
	} // End linkedin_publish_syntax_callback()
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
			// If one of them is not hexadecimal, throw an error
			else {
				add_settings_error (
					self::ID, // Setting to which the error applies
					'client-auth', // Identify the option throwing the error
					'Whoops! The client ID or client secret you entered doesn\'t match Buffer\'s format. Double-check them both, and take another crack at it.', // Error message
					'error' // The type of message it is
				);
			}
		}
		
		// Access token will only be saved if Client ID and Client Secret are both already saved
		if ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) ) {
			// Make sure a value is provided for the access token
			if ( ! empty( $input['site_access_token'] ) ) {
				// Only perform the validation tasks if the value has changed from what's in the database
				if ( $input['site_access_token'] != $this->options['site_access_token'] ) {
					// Make sure the user is valid
					if ( $this->api->validate_user( $input['site_access_token'] ) ) {
						$options['site_access_token'] = $input['site_access_token'];
					}
					// If the user is not valid, throw an error
					else {
						add_settings_error (
							self::ID, // Setting to which the error applies
							'site-access-token', // Identify the option throwing the error
							'Whoops! Buffer says that access token isn\'t quite right. Let\'s double-check what we put in, and give it another shot!', // Error message
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
					'So close! It looks like you didn\'t provide an access token, and we can\'t continue without one. Let\'s try again!', // Error message
					'error' // The type of message it is
				);
			}
		}
		
		// All other options require application to be fully authenticated, and no validation or assigment will be done without that
		if ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) && ! empty( $this->options['site_access_token'] ) ) {
			/* Twitter options */
			// Check the value for enabling Twitter
			if ( $input['twitter_send'] == 'yes' || $input['twitter_send'] == null ) {
				$options['twitter_send'] = $input['twitter_send'];
			}
			
			// Check the Twitter syntax
			// Verify that Twitter is enabled, otherwise we'll ignore the syntax field
			if ( $input['twitter_send'] == 'yes' ) {
				if ( $this->validate_syntax( $input['twitter_publish_syntax'] ) ) {
					// If the syntax provided is valid, use it
					$options['twitter_publish_syntax'] = $input['twitter_publish_syntax'];
				}
				// If not, throw an error
				else {
					add_settings_error (
						self::ID, // Setting to which the error applies
						'twitter-publish-syntax', // Identify the option throwing the error
						'Hang on a second. The Twitter syntax you provided doesn\'t look quite right. Make sure everything is entered properly, and try it again.', // Error message
						'error' // The type of message it is
					);
				}
			}
			
			// Check the number of additional tweets
			if ( is_numeric( $input['twitter_post_number'] ) && 0 <= $input['twitter_post_number'] ) {
				$options['twitter_post_number'] = $input['twitter_post_number'];
			}
			else {
				add_settings_error (
					self::ID, // Setting to which the error applies
					'twitter-post-number', // Identify the option throwing the error
					'You provided an invalid value for the number of additional tweets to send. You must provide a number greater than or equal to 0.', // Error message
					'error' // The type of message it is
				);
			}
			
			// Check the interval between tweets
			// Only require this field if twitter_post_number is greater than 0
			if ( $input['twitter_post_number'] && ( is_numeric( $input['twitter_post_number'] ) && $input['twitter_post_number'] > 0 ) ) {
				if ( $input['twitter_post_interval'] && ( is_numeric( $input['twitter_post_interval'] ) && 1 <= $input['twitter_post_interval'] ) ) {
					$options['twitter_post_interval'] = $input['twitter_post_interval'];
				}
				else {
					add_settings_error (
						self::ID, // Setting to which the error applies
						'twitter-post-interval', // Identify the option throwing the error
						'You provided an invalid value for the interval between tweets. You must provide a number greater than 0.', // Error message
						'error' // The type of message it is
					);
				}
			}
			// If twitter_post_number is set to 0, set this option to null
			else {
				$options['twitter_post_interval'] = null;
			}
			/* End Twitter options */
			
			/* Facebook options */
			// Check the value for enabling Facebook
			if ( $input['fb_send'] == 'yes' || $input['fb_send'] == null ) {
				$options['fb_send'] = $input['fb_send'];
			}
			
			// Check the Facebook syntax
			// Verify that Facebook is enabled, otherwise we'll ignore the syntax field
			if ( $input['fb_send'] == 'yes' ) {
				if ( $this->validate_syntax( $input['fb_publish_syntax'] ) ) {
					// If the syntax provided is valid, use it
					$options['fb_publish_syntax'] = $input['fb_publish_syntax'];
				}
				// If not, throw an error
				else {
					add_settings_error (
						self::ID, // Setting to which the error applies
						'fb-publish-syntax', // Identify the option throwing the error
						'The post format you provided for Facebook is invalid.', // Error message
						'error' // The type of message it is
					);
				}
			}
			/* End Facebook options */
			
			/* LinkedIn options */
			// Check the value for enabling LinkedIn
			if ( $input['linkedin_send'] == 'yes' || $input['linkedin_send'] == null ) {
				$options['linkedin_send'] = $input['linkedin_send'];
			}
			
			// Check the LinkedIn syntax
			// Verify that LinkedIn is enabled, otherwise we'll ignore the syntax field
			if ( $input['linkedin_send'] == 'yes' ) {
				if ( $this->validate_syntax( $input['linkedin_publish_syntax'] ) ) {
					// If the syntax provided is valid, use it
					$options['linkedin_publish_syntax'] = $input['linkedin_publish_syntax'];
				}
				// If not, throw an error
				else {
					add_settings_error (
						self::ID, // Setting to which the error applies
						'linkedin-publish-syntax', // Identify the option throwing the error
						'The post format you provided for LinkedIn is invalid.', // Error message
						'error' // The type of message it is
					);
				}
			}
			/* End LinkedIn options */
		}
		
		return $options;
	} // End options_validate()
	
	// Validate message syntax (NEEDS SYNTAX CHECK ADDED)
	function validate_syntax( $input ) {
		// Check that the service is enabled
		if ( $input ) {
			// If the service is enabled, and proper syntax is used, return the syntax
			return TRUE;
		}
		else {
			return FALSE;
		}
	}
	/*
	===== End Admin Plugin Options =====
	*/
	
	/*
	===== Admin initialization =====
	*/
	// Initialize the admin class
	protected function admin_initialize() {
		// Run plugin upgrade
		$this->upgrade();
	} // End admin_initialize()
	
	// Plugin upgrade
	function upgrade() {
		// Check whether the database-stored plugin version number is less than the current plugin version number, or whether there is no plugin version saved in the database
		if ( version_compare( $this->options['dbversion'], self::VERSION, '<' ) ) {
			// Set local variable for options (always the first step in the upgrade process)
			$options = $this->options;
			
			/* Update the plugin version saved in the database (always the last step of the upgrade process) */
			// Set the value of the plugin version
			$options['dbversion'] = self::VERSION;
				
			// Save to the database
			update_option( $this->prefix . 'options', $options );
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
	 		'twitter_send' => null, // Don't enable Twitter by default
	 		'twitter_publish_syntax' => 'New Post: {title} {url}', // Default syntax of Twitter messages
	 		'twitter_post_number' => 0, // Number of tweets to schedule
	 		'twitter_post_interval' => null, // Interval between scheduled tweets
	 		'fb_send' => null, // Don't enable Facebook by default
	 		'fb_publish_syntax' => 'New Post: {title} {url}', // Default syntax of Facebook messages
	 		'linkedin_send' => null, // Don't enable LinkedIn by default
	 		'linkedin_publish_syntax' => 'New Post: {title} {url}', // Default syntax of LinkedIn messages
	 		'dbversion' => self::VERSION, // Current plugin version
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