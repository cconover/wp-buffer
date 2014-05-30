/*
JavaScript to use when editing a post or page
*/

// Don't execute anything until the page is loaded
jQuery( document ).ready( function( $ ) {
	// Show the meta box form (not shown by default; requires JavaScript for it to be visible)
	$( '#cc-wp-buffer-metabox-contents' ).show();
	
	// Apply jQueryUI tabs to the meta box
	$( "#cc-wp-buffer-metabox-contents" ).tabs();
} );