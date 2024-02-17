<?php
/**
 * Plugin Name:     TN Tame Session Defaults
 * Plugin URI:      https://github.com/timnashcouk/tn-tame-session-defaults
 * Description:     Provides Default Session Values
 * Author:          Tim Nash
 * Author URI:      https://timnash.co.uk
 * Text Domain:     tn-tame-session-defaults
 * Domain Path:     /languages
 * Version:         1.1.0
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
function tn_set_session_length( int $seconds, int $user_id, bool $remember = false ): int {
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
 * @param string  $username User name.
 * @param WP_User $user User object.
 * @return void
 * @since 1.0.0
 **/
function tn_session_limit( string $username, WP_User $user ): void {
	if ( apply_filters( 'tn_tame_session_limit', false, $user ) ) {
		return;
	}
	wp_destroy_other_sessions();
}
add_action( 'wp_login', 'tn_session_limit', 10, 2 );

/**
 * Validates IP & UA of a session
 *
 * @return void
 * @since 1.1.0
 **/
function tn_validate_session(): void {

	if ( is_user_logged_in() ) {
		$sessions = WP_Session_Tokens::get_instance( get_current_user_id() );
		$token    = wp_get_session_token();
		// No session token means this is probably a API request let it pass.
		// @todo: realistically the only time this might happen is admin-ajax.php or post-admin.php.
		if ( ! $token ) {
			return;
		}
		$session_data = $sessions->get( $token );
		// No session data, we should log the user out as their token might have expired.
		if ( ! $session_data || ! is_array( $session_data ) ) {
			wp_logout();
		}
		// Current User IP address.
		$ip = null;
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = wp_unslash( $_SERVER['REMOTE_ADDR'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		// Current User User-agent.
		$ua = null;
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$ua = wp_unslash( $_SERVER['HTTP_USER_AGENT'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		if ( isset( $ip ) && isset( $ua ) ) {
			if ( $ip === $session_data['ip'] && $ua === $session_data['ua'] ) {
				return;
			}
			// The session is invalid, log the user out.
			do_action( 'tn_tame_session_non_valid', $ip, $ua, $session_data );
			wp_logout();
		}
	}
}

/**
 * Check if we should validate sessions
 *
 * @return void
 * @since 1.1.0
 **/
function tn_init_validate_session(): void {
	// Check if we should validate sessions, defaults to true.
	if ( apply_filters( 'tn_tame_session_validate_session', true ) ) {
		tn_validate_session();
	}
}

add_action( 'admin_init', 'tn_init_validate_session' );
