<?php
/**
 * Lightweight WordPress test bootstrap.
 *
 * @package TN_Tame_Session_Defaults
 */

$tn_filters = array();

class WP_User {}

class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( string $code, string $message, array $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
}

class WP_Session_Tokens {
	public static function get_instance( int $user_id ): self {
		return new self();
	}

	public function get( string $token ) {
		global $tn_test_requested_session_token, $tn_test_session_data;

		$tn_test_requested_session_token = $token;
		return $tn_test_session_data;
	}
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	global $tn_filters;

	$tn_filters[ $hook ][ $priority ][] = array(
		'callback'      => $callback,
		'accepted_args' => $accepted_args,
	);
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	add_filter( $hook, $callback, $priority, $accepted_args );
}

function apply_filters( string $hook, $value, ...$args ) {
	global $tn_filters;

	if ( empty( $tn_filters[ $hook ] ) ) {
		return $value;
	}

	ksort( $tn_filters[ $hook ] );

	foreach ( $tn_filters[ $hook ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$callback_args = array_slice( array_merge( array( $value ), $args ), 0, $callback['accepted_args'] );
			$value         = $callback['callback']( ...$callback_args );
		}
	}

	return $value;
}

function do_action( string $hook, ...$args ): void {
	global $tn_test_actions;

	$tn_test_actions[] = array(
		'hook' => $hook,
		'args' => $args,
	);
}

function absint( $value ): int {
	return abs( (int) $value );
}

function is_user_logged_in(): bool {
	global $tn_test_is_user_logged_in;

	return (bool) $tn_test_is_user_logged_in;
}

function wp_get_session_token(): string {
	global $tn_test_session_token;

	return (string) $tn_test_session_token;
}

function wp_parse_auth_cookie( string $cookie = '', string $scheme = '' ) {
	global $tn_test_auth_cookie_token;

	if ( '' === $tn_test_auth_cookie_token ) {
		return false;
	}

	return array(
		'token'  => $tn_test_auth_cookie_token,
		'scheme' => $scheme,
	);
}

function wp_validate_auth_cookie( string $cookie = '', string $scheme = '' ) {
	global $tn_test_auth_cookie_user_id;

	return $tn_test_auth_cookie_user_id;
}

function get_current_user_id(): int {
	global $tn_test_user_id;

	return (int) $tn_test_user_id;
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function wp_logout(): void {
	global $tn_test_did_logout;

	$tn_test_did_logout = true;
}

function wp_destroy_other_sessions(): void {
	global $tn_test_destroy_other_sessions_count;

	++$tn_test_destroy_other_sessions_count;
}

function wp_die( string $message = '', string $title = '', array $args = array() ): void {
	global $tn_test_wp_die;

	$tn_test_wp_die = array(
		'message' => $message,
		'title'   => $title,
		'args'    => $args,
	);
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return $text;
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function admin_url( string $path = '' ): string {
	return 'https://example.org/wp-admin/' . $path;
}

function wp_safe_redirect( string $location ): void {
	global $tn_test_redirect_location;

	$tn_test_redirect_location = $location;
}

function wp_login_url( string $redirect = '', bool $force_reauth = false ): string {
	return 'https://example.org/wp-login.php';
}

function wp_doing_ajax(): bool {
	return false;
}

function wp_is_json_request(): bool {
	return false;
}

function wp_send_json_error( array $response, ?int $status_code = null ): void {}

function current_user_can( string $capability ): bool {
	global $tn_test_current_user_can;

	if ( is_array( $tn_test_current_user_can ) && array_key_exists( $capability, $tn_test_current_user_can ) ) {
		return (bool) $tn_test_current_user_can[ $capability ];
	}

	return (bool) $tn_test_current_user_can;
}

require __DIR__ . '/../tn-tame-session-defaults.php';

function tn_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		echo "FAIL: {$message}\n";
		echo 'Expected: ' . var_export( $expected, true ) . "\n";
		echo 'Actual:   ' . var_export( $actual, true ) . "\n";
		exit( 1 );
	}
}

function tn_reset_test_filters(): void {
	global $tn_filters;

	$registered = $tn_filters;
	$tn_filters = array();

	foreach ( $registered as $hook => $priorities ) {
		if ( in_array( $hook, array( 'auth_cookie_expiration', 'wp_login', 'admin_init', 'rest_authentication_errors', 'template_redirect' ), true ) ) {
			$tn_filters[ $hook ] = $priorities;
		}
	}
}

function tn_reset_test_request(): void {
	global $tn_test_actions, $tn_test_auth_cookie_token, $tn_test_auth_cookie_user_id, $tn_test_current_user_can, $tn_test_destroy_other_sessions_count, $tn_test_did_logout, $tn_test_is_user_logged_in, $tn_test_redirect_location, $tn_test_requested_session_token, $tn_test_session_data, $tn_test_session_token, $tn_test_user_id, $tn_test_wp_die;

	$tn_test_actions                      = array();
	$tn_test_auth_cookie_token            = '';
	$tn_test_auth_cookie_user_id          = false;
	$tn_test_current_user_can             = true;
	$tn_test_destroy_other_sessions_count = 0;
	$tn_test_did_logout                   = false;
	$tn_test_is_user_logged_in            = false;
	$tn_test_redirect_location            = '';
	$tn_test_requested_session_token      = '';
	$tn_test_session_data                 = null;
	$tn_test_session_token                = '';
	$tn_test_user_id                      = 0;
	$tn_test_wp_die                       = array();
	$_GET                                 = array();
	$_REQUEST                             = array();
	$_SERVER                              = array();
	unset( $GLOBALS['pagenow'], $GLOBALS['wp'] );
}

function tn_get_registered_filter_priority( string $hook, string $callback_name ): ?int {
	global $tn_filters;

	if ( empty( $tn_filters[ $hook ] ) ) {
		return null;
	}

	foreach ( $tn_filters[ $hook ] as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( $callback_name === $callback['callback'] ) {
				return (int) $priority;
			}
		}
	}

	return null;
}
