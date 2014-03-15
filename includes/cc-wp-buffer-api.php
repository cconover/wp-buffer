<?php
/**
 * Buffer API class
 * Handles all API calls for the plugin
 **/

namespace cconover\buffer;

class Api extends Buffer {
	/* Class properties */
	protected $clientconfig; // OAuth client configuration
	
	// Class constructor
	function __construct() {
		// Initialize
		$this->initialize();
		$this->initialize_api();
	} // End __construct()
	
	/* API initialization and configuration */
	// Initialize the API
	function initialize_api() {
		// Load the OAuth client configuration
		$this->clientconfig();
	} // End initialize_api()
	
	// OAuth client configuration
	function clientconfig() {
		
	} // End clientconfig()
	/* End API initialization and configuration */
}
?>