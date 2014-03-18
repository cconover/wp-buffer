<?php
/**
 * Buffer API class
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
	Method arguments:
		Access token*
		API command*
		Request HTTP method (defaults to GET)
		WordPress API arguments for wp_remote_get and wp_remote_post (defaults to empty array)
	*/
	function request ( $access_token, $command, $method = 'get', $args = array() ) {
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
		
		// If the response code is 200, return the decoded JSON response
		if ( $request['response']['code'] == 200 ) {
			return json_decode( $request['body'] );
		}
		// If not, return an error
		else {
			return 'Error ' . $request['response']['code'] . ': ' . $request['response']['message'];
		}
	} // End request()
	
	/* User Methods */
	// Validate a Buffer user
	protected function validate_user( $access_token ) {
		// Check to make sure an access token is provided
		if ( ! empty( $access_token ) ) {
			// We'll be asking the Buffer API for information about the specified user
			$command = 'user';
			
			// Make the request to the Buffer API
			$result = $this->request( $access_token, $command );
			
			// If the response includes a user ID, the user is valid
			if ( ! empty( $result['id'] ) ) {
				return true;
			}
			// If it doesn't, the specified user is no valid
			else {
				return false;
			}
		}
		// If any of those items are missing, return that the user could not be validated
		else {
			return false;
		}
	} // End validate_user()
	/* End User Methods */
}
?>