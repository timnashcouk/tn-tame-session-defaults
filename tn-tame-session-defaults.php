<?php
/**
 * Plugin Name:     TN Tame Session Defaults
 * Plugin URI:      https://github.com/timnashcouk/tn-tame-session-defaults
 * Description:     Provides Defult Session Values
 * Author:          Tim Nash
 * Author URI:      https://timnash.co.uk
 * Text Domain:     tn-tame-session-defaults
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         tn-tame-session-defaults
 **/

/**
 * Set the default session length
 *
 * @param int  $seconds Session length in seconds.
 * @param int  $user_id User ID.
 * @param bool $remember Whether to remember the session.
 * @return int Session length.
 * @since 1.0.0
 **/
function tn_set_session_length( $seconds, $user_id, $remember = false ) {
	$default_session          = absint( apply_filters( 'tn_tame_session_default', 60 * 60 * 2, $user_id ) ); // 2 hours
	$default_remember_session = absint( apply_filters( 'tn_tame_session_default_remember', 60 * 60 * 24, $user_id ) ); // 24 hours
	if ( ! $remember ) {
		$expiry = $default_session;
	} else {
		$expiry = $default_remember_session;
	}
	// If the default session is geater then the pre-existing session expiry, use the pre-existing.
	if ( $expiry > absint( $seconds ) ) {
		$expiry = absint( $seconds );
	}
	return $expiry;
}
add_filter( 'auth_cookie_expiration', 'tn_set_session_length', 10, 3 );

/**
 * Destroy other sessions
 *
 * @param string $username User name.
 * @param object $user User object.
 * @return mixed
 * @since 1.0.0
 **/
function tn_session_limit( $username, $user ) {
	if ( apply_filters( 'tn_tame_session_limit', false, $user ) ) {
		return;
	}
	return wp_destroy_other_sessions();
}
add_action( 'wp_login', 'tn_session_limit', 10, 2 );
