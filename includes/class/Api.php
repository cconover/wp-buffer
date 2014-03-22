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
	Buffer API request
	All other plugin API calls use this method to connect to the Buffer API
	Method arguments:
		Access token*
		API command*
		Request HTTP method (defaults to GET)
		WordPress API arguments for wp_remote_get and wp_remote_post (defaults to empty array)
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
	public function buffer_oauth_request() {
		// See whether Buffer has replied with a code
		if ( isset( $_REQUEST['code'] ) ) {
			// Set a local variable for the code returned
			$code = $_REQUEST['code'];
			
			// Set up the data to be sent to the Buffer API
			$postdata = array(
				'client_id' => $this->options['client_id'], // Application client ID
				'client_secret' => $this->options['client_secret'], // Application client secret
				'redirect_url' => urlencode( $this->callbackurl ), // The callback endpoint for the plugin
				'code' => $code, // The temporary code we just got from Buffer
				'grant_type' => 'authorization_code' // We want back a long-term access token
			);
			
			// Set parameters for the WordPress HTTP API
			$args = array(
				'body' => $postdata // Send the POST data to the HTTP API
			);
			
			// Make the request to the Buffer API
			$request = wp_remote_post( 'https://api.bufferapp.com/1/oauth2/token.json', $args );
			
			// Check whether WordPress threw an error, and if so display it
			if ( is_wp_error( $request ) ) {
				echo 'WordPress encountered an error: ' . $request->get_error_message();
			}
			// If not, keep going
			else {				
				// If an access token was sent back, save it
				if ( ! empty( $request['access_token'] ) ) {
					// Get details about the user from Buffer
					$user = $this->get_user( $request['access_token'] );
					
					// Create a local variable for the options stored in the database
					$options = $this->options;
					
					// Set the value of 'site_access_token' and 'site_user_id'
					$options['site_access_token'] = $request['access_token'];
					$options['site_user_id'] = $user['id'];
					
					// Save the newly acquired information to the database
					update_option( $this->prefix . 'options', $options );
					
					// Redirect the user back to the plugin options page
					wp_redirect( admin_url( 'options-general.php?page=' . self::ID ) );
					exit;
				}
			}
		}
	} // End buffer_oauth_request()
	/* End Authentication Methods */
	
	/* User Methods */
	// Validate a Buffer user
	public function get_user( $access_token ) {
		// Check to make sure an access token is provided
		if ( ! empty( $access_token ) ) {
			// We'll be asking the Buffer API for information about the specified user
			$command = 'user';
			
			// Make the request to the Buffer API
			$result = $this->request( $access_token, $command );
			
			// Return the response from the Buffer API
			return $result;
		}
	} // End validate_user()
	/* End User Methods */
	
	/* Profile Methods */
	
	/* End Profile Methods */
}
?>