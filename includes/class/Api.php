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
	
	/* End Authentication Methods */
	
	/* User Methods */
	// Validate a Buffer user
	public function validate_user( $access_token ) {
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