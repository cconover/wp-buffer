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
	
	/**
	 * Plugin options URL (used for callbacks, etc.)
	 * @param boolean $encode whether to encode the result for use in a URL string
	 * @param array $args variables to be added to the end of the URL. Must be an associative array, using 'variable_name' => 'value' syntax
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
		if ( true == $encode ) {
			$url = urlencode( $url );
		}
		
		return $url;
	} // End optionsurl()
	
	/**
	 * Buffer API request
	 * All plugin API calls (except OAuth) use this method to connect to the Buffer API
	 * @param string $access_token Buffer access token
	 * @param string $endpoint Buffer API endpoint
	 * @param string $method HTTP method to use for the request (default: GET)
	 * @param array $args WordPress HTTP API arguments (default: empty; use WordPress defaults)
	 */
	function request( $access_token, $endpoint, $method = 'get', $args = array() ) {
		// Create the full Buffer API request URI (same for GET and POST)
		$api = 'https://api.bufferapp.com/1/' . $endpoint . '.json?access_token=' . $access_token;
		
		// If the HTTP method is GET, do this
		if ( 'get' == $method ) {
			$request = wp_remote_get( $api, $args );
		}
		// If the HTTP method is POST, do this
		elseif ( 'post' == $method ) {
			$request = wp_remote_post( $api, $args );
		}
		
		// Check whether the result is a WordPress error
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		
		// Return the JSON-decoded Buffer API response as an associative array
		return json_decode( $request['body'], true );
	} // End request()
	
	/* Authentication Methods */
	/**
	 * Set up OAuth authentication with Buffer
	 * @param integer $userid the ID of the WordPress user. Defaults to NULL, meaning this is for the plugin's global options
	 */
	public function buffer_oauth_connect( $userid = null ) {
		// Create the 'Authorize with Buffer' button
		$oauth_button = '<a class="button button-primary" href="https://bufferapp.com/oauth2/authorize?client_id=' . $this->options['client_id'] . '&redirect_uri=' . $this->optionsurl( true ) . '&response_type=code">Authenticate Me!</a>';
		
		// See whether Buffer has replied with a code
		if ( ! empty( $_REQUEST['code'] ) ) {
			// Set a local variable for the code returned, decode from URL format
			$code = urldecode( $_REQUEST['code'] );
			
			// Set parameters for the WordPress HTTP API, including the POST data
			$args = array(
				'body' => array(
					'client_id' => $this->options['client_id'], // Application client ID
					'client_secret' => $this->options['client_secret'], // Application client secret
					'redirect_uri' => $this->optionsurl(), // The callback endpoint for the plugin
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
				// Decode the JSON data returned by Buffer as an associative array
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
						if ( update_option( self::PREFIX . 'options', $options ) ) {
							// Show a link back to the plugin options page
							echo '<a href="' . $this->optionsurl() . '">Set up plugin options</a>';
						}
					}
					// If we get an error, notify the user
					elseif ( ! empty( $user['code'] ) ) {
						echo '<div class="error settings-error"><p><strong>Hmmm, we weren\'t able to get your account information from Buffer. Let\'s try again!</strong><br><em>API Error: ' . $user['code'] . ' ' . $user['error'] . '</em></p></div>' . $oauth_button;
					}
				}
				// If Buffer replied but not with an access token, something went wrong and we need to notify the user
				else {
					echo '<div class="error settings-error"><p><strong>Nuts! We didn\'t get authorization from Buffer. Let\'s try it again!</strong><br><em>API Error: ' . $request['error'] . '</em></p></div>' . $oauth_button;
				}
			}
		}
		// If the API returns an error, handle that
		elseif ( ! empty( $_REQUEST['error'] ) ) {
			echo '<div class="error settings-error"><p><strong>Uh oh! Buffer replied with an error. Let\'s try again!</strong><br><em>API Error: ' . $_REQUEST['error'] . '</em></p></div>' . $oauth_button;
		}
		else {
			echo $oauth_button;
		}
	} // End buffer_oauth_connect()
	
	// Check whether all authentication fields are present
	public function is_site_authenticated() {
		// If all the fields necessary to authenticate with Buffer have values, return true
		if ( ! empty( $this->options['client_id'] ) && ! empty( $this->options['client_secret'] ) && ! empty( $this->options['site_access_token'] ) ) {
			return true;
		}
		// If not, return false
		else {
			return false;
		}
	} // End is_site_authenticated()
	/* End Authentication Methods */
	
	/* User Methods */
	// Validate a Buffer user
	public function get_user( $access_token ) {
		// We'll be asking the Buffer API for information about the specified user
		$endpoint = 'user';
			
		// Make the request to the Buffer API
		$result = $this->request( $access_token, $endpoint );
			
		// Return the response from the Buffer API
		return $result;
	} // End validate_user()
	/* End User Methods */
	
	/* Profile Methods */
	/**
	 * Get a profile (or all profiles) for the specified user account
	 * @param string $access_token the access token for the account
	 * @param string $profile the profile to be retrieve (defaults to NULL, resulting in all profiles associated with the account)
	 */
	public function get_profile( $access_token, $profile = null ) {
		// If a specific profile is specified, use the Buffer endpoint for that profile
		if ( ! empty( $profile ) ) {
			$endpoint = 'profiles/' . $profile;
		}
		// If no profile is specified, get all the profiles for the account
		else {
			$endpoint = 'profiles';
		}
		
		// Make the request to the Buffer API
		$result = $this->request( $access_token, $endpoint );
		
		// Return the reponse
		return $result;
	} // End get_profile()
	
	/* End Profile Methods */
	
	/* Updates Methods */
	/** Create a new update
	 * @param string $access_token Buffer access token, for API authentication
	 * @param array $data the payload to send to the Buffer API
	 * @param array $post the WordPress post object
	 */
	public function create_update( $access_token, $data, $post ) {
		// Specify the Buffer API endpoint to use for this request
		$endpoint = '/updates/create';
		
		// Specify the method to use for this request
		$method = 'post';
		
		// Create array to store API replies
		$result = array();
		
		// Iterate through each update
		foreach ( $data as $id => $fields ) {
			// If the profile update is enabled, process the update
			if ( ! empty( $fields['enabled'] ) ) {
				// Append the post URL to the message, unless the service is Facebook
				if ( 'facebook' != $fields['service'] ) {
					$fields['message'] = $fields['message'] . ' ' . $post->guid;
				}
				
				// Create array to use as API request payload
				$args = array(
					'body' => array(
						'text'			=> $fields['message'],	// Text of the Buffer update
						'profile_ids'	=> array( $id ),		// Profile ID that the update should be used with
						'media'			=> array(
							'link'			=> $post->guid,
							'title'			=> $post->post_title,
							'description'	=> $this->post_excerpt( $post ),
						),
					)
				);
				
				// If schedule is set to 'now' or 'top', set the appropriate value in the array
				switch ( $fields['schedule'] ) {
					case ( 'now' ):
						$args['body']['now'] = true;
						break;
					case ( 'top' ):
						$args['body']['top'] = true;
						break;
				}
				
				// Send update to Buffer API using request() method
				$result[$id] = $this->request(
					$this->options['site_access_token'],
					$endpoint,
					$method,
					$args
				);
			}
		}
		
		// Return the results
		return $result;
	} // End create_update()
	
	/** Process the post excerpt. If one is defined, use it. If not, generate it from the post content.
	 * @param mixed $post the WordPress post object
	 */
	function post_excerpt( $post ) {
		// If the post excerpt is defined, use it
		if ( ! empty( $post->post_excerpt ) ) {
			$excerpt = $post->post_excerpt;
		}
		// If it's not definted, use the first 500 characters of the post content and sanitize it
		else {
			$excerpt = sanitize_text_field( substr( $post->post_content, 0, 500 ) );
		}
		
		// Return the result
		return $excerpt;
	} // End post_excerpt()
	/* End Updates Methods */
	
	/* Info Methods */
	/** Get Buffer API configuration
	 * @param string $access_token the user access token for the site
	 */
	public function get_config_info( $access_token ) {
		// Set the API request endpoint
		$endpoint = '/info/configuration';
		
		// Request the API configuration
		$result = $this->request( $access_token, $endpoint );
		
		// Return the data received from the API
		return $result;
	} // End get_config_info()
}
?>