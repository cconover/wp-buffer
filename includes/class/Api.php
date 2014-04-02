<?php
/**
 * Plugin Buffer API class
 * Handles all API calls for the plugin
 **/

// Plugin namespace
namespace cconover\buffer;

class Api extends Buffer {
	// Class constructor
	function __construct() {
		// Initialize
		$this->initialize();
	} // End __construct()
	
	/*
	Plugin URL (used for callbacks, etc.)
	@param boolean $encode whether to encode the result for use in a URL string
	@param array $args variables to be added to the end of the URL. Must be an associative array, using 'variable_name' => 'value' syntax
	*/
	public function optionsurl( $encode = false, $args = array() ) {
		// Set the URL
		$url = admin_url( 'options-general.php?page=' . self::ID );
		
		// If any elements were sent in $args, run through them to add to the end of the URL
		if ( ! empty( $args ) ) {
			foreach ( $args as $name => $value ) {
				$url .= '&' . $name . '=' . $value;
			}
		}
		
		// If $encode is true, encode $url for use in a URL
		if ( $encode == true ) {
			$url = urlencode( $url );
		}
		
		return $url;
	} // End optionsurl()
	
	/*
	Buffer API request
	All other plugin API calls use this method to connect to the Buffer API
	@param string $access_token Buffer access token
	@param string $command Buffer API endpoint
	@param string $method HTTP method to use for the request (default: GET)
	@param array $args WordPress HTTP API arguments (default: empty)
	*/
	function request ( $access_token, $command, $method = 'get', $args = array() ) {
		// Format command for the API request
		$command = $command . '.json';
		
		// If the HTTP method is GET, do this
		if ( $method == 'get' ) {
			$request = wp_remote_get( 'https://api.bufferapp.com/1/' . $command . '?access_token=' . $access_token, $args );
		}
		// If the HTTP method is POST, do this
		elseif ( $method == 'post' ) {
			$request = wp_remote_post( 'https://api.bufferapp.com/1/' . $command . '?access_token=' . $access_token, $args );
		}
		
		// Check whether the result is a WordPress error
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		
		// Return the JSON-decoded Buffer API response
		return json_decode( $request['body'], true );
	} // End request()
	
	/* Authentication Methods */
	// Make the request to the Buffer API for OAuth authorization
	public function buffer_oauth_connect() {
		// Create the 'Authorize with Buffer' button
		$oauth_button = '<a class="button button-primary" href="https://bufferapp.com/oauth2/authorize?client_id=' . $this->options['client_id'] . '&redirect_uri=' . $this->optionsurl( true, array( 'noheader' => 'true' ) ) . '&response_type=code">Authenticate Me!</a>';
		
		// See whether Buffer has replied with a code
		if ( ! empty( $_REQUEST['code'] ) ) {
			// Set a local variable for the code returned, decode from URL format
			$code = urldecode( $_REQUEST['code'] );
			
			// Set parameters for the WordPress HTTP API, including the POST data
			$args = array(
				'body' => array(
					'client_id' => $this->options['client_id'], // Application client ID
					'client_secret' => $this->options['client_secret'], // Application client secret
					'redirect_uri' => $this->optionsurl( false, array( 'noheader' => 'true' ) ), // The callback endpoint for the plugin
					'code' => $code, // The temporary code we just got from Buffer
					'grant_type' => 'authorization_code' // We want back a long-term access token
				)
			);
			
			// Make the request to the Buffer API
			$request = wp_remote_post( 'https://api.bufferapp.com/1/oauth2/token.json', $args );
			
			// Check whether WordPress threw an error, and if so display it
			if ( is_wp_error( $request ) ) {
				echo '<div class="error settings-error"><p><strong>WordPress encountered an error: ' . $request->get_error_message() . '</strong></p></div>' . $oauth_button;
			}
			// If there is no WordPress error, we'll have gotten a reply from Buffer
			else {
				// Decode the JSON data returned by Buffer
				$request = json_decode( $request['body'], true );
				
				// If Buffer returned an access token, process it
				if ( ! empty( $request['access_token'] ) ) {					
					// Get details about the user from Buffer
					$user = $this->get_user( $request['access_token'] );
					
					// If we get a valid response from Buffer, proceed with processing the returned data
					if ( ! empty( $user['id'] ) ) {
						// Create a local variable for the options stored in the database
						$options = $this->options;
					
						// Set the value of 'site_access_token' and 'site_user_id'
						$options['site_access_token'] = $request['access_token'];
						$options['site_user_id'] = $user['id'];
					
						// Save the newly acquired information to the database
						update_option( self::PREFIX . 'options', $options );
					
						// Redirect the user back to the plugin options page
						wp_redirect( $this->optionsurl() );
					}
					// If we get an error, notify the user
					elseif ( ! empty( $user['code'] ) ) {
						echo '<div class="error settings-error"><p><strong>Hmmm, we weren\'t able to get your account information from Buffer. Let\'s try again!<br /><em>API Error: ' . $user['code'] . ' ' . $user['error'] . '</em></strong></p></div>' . $oauth_button;
					}
				}
				// If Buffer replied but not with an access token, something went wrong and we need to notify the user
				else {
					echo '<div class="error settings-error"><p><strong>Nuts! We didn\'t get authorization from Buffer. Let\'s try it again!<br /><em>API Error: ' . $request['error'] . '</em></strong></p></div>' . $oauth_button;
				}
			}
		}
		// If the API returns an error, handle that
		elseif ( ! empty( $_REQUEST['error'] ) ) {
			echo '<div class="error settings-error"><p><strong>Uh oh! Buffer replied with an error. Let\'s try again!</strong></p></div>' . $oauth_button;
		}
		else {
			echo $oauth_button;
		}
	} // End buffer_oauth_connect()
	
	// Disconnect the OAuth authentication from Buffer
	public function buffer_oauth_disconnect() {
		// Check whether a request to disconnect has been made
		if ( isset( $_REQUEST['buffer_oauth_disconnect'] ) ) {
			// Set a local variable for the class options property
			$options = $this->options;
			
			// Remove the site access token
			$options['site_access_token'] = null;
			
			// Update the plugin options with the newly empty site access token
			update_option( self::PREFIX . 'options', $options );
			
			// Redirect the user back to the plugin options page
			wp_redirect( $this->optionsurl() );
		}
		// If no request for disconnect has been made, display a button for disconnecting
		else {
			echo '<a class="button" href="' . $this->optionsurl( false, array( 'buffer_oauth_disconnect' => 'yes', 'noheader' => 'true' ) ) . '">Disconnect</a>';
		}
	}
	/* End Authentication Methods */
	
	/* User Methods */
	// Validate a Buffer user
	public function get_user( $access_token ) {
		// We'll be asking the Buffer API for information about the specified user
		$command = 'user';
			
		// Make the request to the Buffer API
		$result = $this->request( $access_token, $command );
			
		// Return the response from the Buffer API
		return $result;
	} // End validate_user()
	/* End User Methods */
	
	/* Profile Methods */
	/**
	 * Get a profile (or all profiles) for the specified user account
	 * @param string $access_token the access token for the account
	 * @param string $profile the profile to be retrieve (defaults to all profiles associated with the account)
	 **/
	public function get_profile( $access_token, $profile = null ) {
		// If a specific profile is specified, use the Buffer endpoint for that profile
		if ( ! empty( $profile ) ) {
			$command = 'profiles/' . $profile;
		}
		// If no profile is specified, get all the profiles for the account
		else {
			$command = 'profiles';
		}
		
		// Make the request to the Buffer API
		$result = $this->request( $access_token, $command );
		
		// Return the reponse
		return $result;
	} // End get_profile()
	
	/* End Profile Methods */
}
?>