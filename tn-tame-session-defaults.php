<?php
/**
 * Plugin Name:     TN Tame Session Defaults
 * Plugin URI:      https://github.com/timnashcouk/tn-tame-session-defaults
 * Description:     Provides Default Session Values
 * Author:          Tim Nash
 * Author URI:      https://timnash.co.uk
 * Text Domain:     tn-tame-session-defaults
 * Domain Path:     /languages
 * Version:         1.2.1
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
 * Get the current session data.
 *
 * @return array|null Session data, or null if unavailable.
 * @since 1.2.0
 **/
function tn_get_current_session_data(): ?array {
	if ( ! is_user_logged_in() ) {
		return null;
	}

	$token = wp_get_session_token();
	if ( ! $token ) {
		return null;
	}

	$sessions     = WP_Session_Tokens::get_instance( get_current_user_id() );
	$session_data = $sessions->get( $token );

	if ( ! is_array( $session_data ) ) {
		return null;
	}

	return $session_data;
}

/**
 * Get the current session age in seconds.
 *
 * @param int|null $now Current timestamp, or null to use time().
 * @return int|null Session age in seconds, or null if unavailable.
 * @since 1.2.0
 **/
function tn_get_current_session_age( ?int $now = null ): ?int {
	$session_data = tn_get_current_session_data();

	if ( empty( $session_data['login'] ) ) {
		return null;
	}

	if ( null === $now ) {
		$now = time();
	}

	return max( 0, $now - absint( $session_data['login'] ) );
}

/**
 * Check whether a request value matches an allow list.
 *
 * @param array|string $allowed Allowed values.
 * @param string       $current Current value.
 * @return bool Whether the current value is allowed.
 * @since 1.2.0
 **/
function tn_session_request_value_matches( $allowed, string $current ): bool {
	if ( empty( $allowed ) ) {
		return true;
	}

	if ( ! is_array( $allowed ) ) {
		$allowed = array( $allowed );
	}

	return in_array( $current, $allowed, true );
}

/**
 * Get a scalar request value.
 *
 * @param string $key Request key.
 * @return string Request value, or an empty string.
 * @since 1.2.0
 **/
function tn_get_session_request_value( string $key ): string {
	if ( empty( $_REQUEST[ $key ] ) || is_array( $_REQUEST[ $key ] ) ) {
		return '';
	}

	return (string) wp_unslash( $_REQUEST[ $key ] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

/**
 * Get the current configured operation that needs session freshness.
 *
 * @return array|null Operation configuration, or null if none match.
 * @since 1.2.0
 **/
function tn_get_sensitive_session_operation(): ?array {
	$operations = apply_filters( 'tn_tame_session_reauth_operations', array(), get_current_user_id() );

	if ( empty( $operations ) || ! is_array( $operations ) ) {
		return null;
	}

	$current_page   = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
	$current_action = tn_get_session_request_value( 'action' );
	$bulk_action    = tn_get_session_request_value( 'action2' );
	$request_method = '';

	if ( ! empty( $_SERVER['REQUEST_METHOD'] ) ) {
		$request_method = strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	foreach ( $operations as $id => $operation ) {
		if ( ! is_array( $operation ) || empty( $operation['max_age'] ) ) {
			continue;
		}

		if ( ! empty( $operation['capability'] ) && ! current_user_can( (string) $operation['capability'] ) ) {
			continue;
		}

		if ( ! tn_session_request_value_matches( $operation['pages'] ?? array(), $current_page ) ) {
			continue;
		}

		if (
			! empty( $operation['actions'] ) &&
			! tn_session_request_value_matches( $operation['actions'], $current_action ) &&
			! tn_session_request_value_matches( $operation['actions'], $bulk_action )
		) {
			continue;
		}

		if ( ! tn_session_request_value_matches( $operation['methods'] ?? array(), $request_method ) ) {
			continue;
		}

		if ( empty( $operation['id'] ) && is_string( $id ) ) {
			$operation['id'] = $id;
		}

		return $operation;
	}

	return null;
}

/**
 * Check whether the session is too old for a sensitive operation.
 *
 * @param array    $operation Operation configuration.
 * @param int|null $now Current timestamp, or null to use time().
 * @return bool Whether the user should re-authenticate.
 * @since 1.2.0
 **/
function tn_session_needs_reauthentication( array $operation, ?int $now = null ): bool {
	$max_age = empty( $operation['max_age'] ) ? 0 : absint( $operation['max_age'] );

	if ( ! $max_age ) {
		return false;
	}

	$session_age = tn_get_current_session_age( $now );

	if ( null === $session_age ) {
		return true;
	}

	return $session_age > $max_age;
}

/**
 * Check whether the current request can safely redirect to the login form.
 *
 * @return bool Whether redirecting will preserve the request.
 * @since 1.2.0
 **/
function tn_session_reauth_can_redirect(): bool {
	$request_method = '';

	if ( ! empty( $_SERVER['REQUEST_METHOD'] ) ) {
		$request_method = strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	return in_array( $request_method, array( '', 'GET', 'HEAD' ), true );
}

/**
 * Get the current admin URL for redirecting after re-authentication.
 *
 * @return string Admin URL.
 * @since 1.2.0
 **/
function tn_get_session_reauth_redirect_url(): string {
	$current_page = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
	$redirect_url = admin_url( $current_page );

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		$redirect_url .= '?' . ltrim( (string) wp_unslash( $_SERVER['QUERY_STRING'] ), '?' ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	return $redirect_url;
}

/**
 * Force re-authentication for stale sessions before configured operations.
 *
 * @return void
 * @since 1.2.0
 **/
function tn_init_session_reauthentication(): void {
	$operation = tn_get_sensitive_session_operation();

	if ( ! $operation || ! tn_session_needs_reauthentication( $operation ) ) {
		return;
	}

	do_action( 'tn_tame_session_reauth_required', $operation, get_current_user_id() );

	$is_ajax_request = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
	$is_json_request = function_exists( 'wp_is_json_request' ) && wp_is_json_request();

	if ( $is_ajax_request || $is_json_request ) {
		wp_send_json_error(
			array(
				'code'    => 'reauthentication_required',
				'message' => esc_html__( 'Please re-authenticate to continue.', 'tn-tame-session-defaults' ),
			),
			401
		);
	}

	if ( ! tn_session_reauth_can_redirect() ) {
		wp_die(
			esc_html__( 'Please re-authenticate and retry this action.', 'tn-tame-session-defaults' ),
			'',
			array( 'response' => 401 )
		);
	}

	$redirect_url = apply_filters( 'tn_tame_session_reauth_redirect_url', tn_get_session_reauth_redirect_url(), $operation );

	wp_safe_redirect( wp_login_url( $redirect_url, true ) );
	exit;
}

add_action( 'admin_init', 'tn_init_session_reauthentication', 20 );

/**
 * Get the configured session validation areas.
 *
 * Area values can be true, false, or an array of path prefixes.
 *
 * @return array Session validation area policy.
 * @since 1.3.0
 **/
function tn_get_session_validation_areas(): array {
	$defaults = array(
		'wp_admin'  => true,
		'rest_api'  => false,
		'front_end' => false,
	);

	$areas = apply_filters( 'tn_tame_session_validate_session_areas', $defaults, get_current_user_id() );

	if ( ! is_array( $areas ) ) {
		return $defaults;
	}

	return array_merge( $defaults, $areas );
}

/**
 * Normalize a request path for session validation prefix matching.
 *
 * @param string $path Request path or route.
 * @return string Normalized path.
 * @since 1.3.0
 **/
function tn_normalize_session_validation_path( string $path ): string {
	$parsed_path = wp_parse_url( $path, PHP_URL_PATH );

	if ( is_string( $parsed_path ) ) {
		$path = $parsed_path;
	}

	$path = '/' . ltrim( $path, '/' );

	return '' === $path ? '/' : $path;
}

/**
 * Check whether a path matches one of the configured prefixes.
 *
 * @param array  $prefixes Path prefixes.
 * @param string $path Request path or route.
 * @return bool Whether the path matches a configured prefix.
 * @since 1.3.0
 **/
function tn_session_validation_path_matches( array $prefixes, string $path ): bool {
	$path = rtrim( tn_normalize_session_validation_path( $path ), '/' );

	if ( '' === $path ) {
		$path = '/';
	}

	foreach ( $prefixes as $prefix ) {
		if ( ! is_scalar( $prefix ) ) {
			continue;
		}

		$prefix = rtrim( tn_normalize_session_validation_path( (string) $prefix ), '/' );

		if ( '' === $prefix ) {
			$prefix = '/';
		}

		if ( '/' === $prefix ) {
			return true;
		}

		if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check whether session validation should run for an area and path.
 *
 * @param string $area Session validation area.
 * @param string $path Request path or route.
 * @return bool Whether session validation should run.
 * @since 1.3.0
 **/
function tn_should_validate_session_area( string $area, string $path = '' ): bool {
	$areas = tn_get_session_validation_areas();

	if ( ! array_key_exists( $area, $areas ) ) {
		return false;
	}

	if ( true === $areas[ $area ] ) {
		return true;
	}

	if ( ! is_array( $areas[ $area ] ) ) {
		return false;
	}

	return tn_session_validation_path_matches( $areas[ $area ], $path );
}

/**
 * Get the current request path.
 *
 * @return string Current request path.
 * @since 1.3.0
 **/
function tn_get_session_validation_request_path(): string {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return '/';
	}

	return tn_normalize_session_validation_path( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

/**
 * Get the current REST API route.
 *
 * @return string Current REST API route.
 * @since 1.3.0
 **/
function tn_get_session_validation_rest_route(): string {
	if ( ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) && is_scalar( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		return tn_normalize_session_validation_path( (string) $GLOBALS['wp']->query_vars['rest_route'] );
	}

	if ( ! empty( $_GET['rest_route'] ) && is_scalar( $_GET['rest_route'] ) ) {
		return tn_normalize_session_validation_path( (string) wp_unslash( $_GET['rest_route'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	return tn_get_session_validation_request_path();
}

/**
 * Get the current session token from available WordPress auth cookies.
 *
 * @return string Session token, or an empty string when unavailable.
 * @since 1.3.0
 **/
function tn_get_session_validation_token(): string {
	$token = wp_get_session_token();

	if ( $token ) {
		return $token;
	}

	foreach ( array( '', 'secure_auth', 'auth' ) as $scheme ) {
		$cookie = wp_parse_auth_cookie( '', $scheme );

		if ( empty( $cookie['token'] ) ) {
			continue;
		}

		if ( get_current_user_id() !== wp_validate_auth_cookie( '', $scheme ) ) {
			continue;
		}

		return (string) $cookie['token'];
	}

	return '';
}

/**
 * Get invalid session validation data for the current request.
 *
 * @return array|null Invalid session data, or null when the session is valid.
 * @since 1.3.0
 **/
function tn_get_session_validation_failure(): ?array {
	if ( ! is_user_logged_in() ) {
		return null;
	}

	$sessions = WP_Session_Tokens::get_instance( get_current_user_id() );
	$token    = tn_get_session_validation_token();

	// No session token means this is probably a API request let it pass.
	// @todo: realistically the only time this might happen is admin-ajax.php or post-admin.php.
	if ( ! $token ) {
		return null;
	}

	$session_data = $sessions->get( $token );

	if ( ! $session_data || ! is_array( $session_data ) ) {
		return array(
			'ip'           => null,
			'ua'           => null,
			'session_data' => array(),
			'notify'       => false,
		);
	}

	$ip = null;
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = wp_unslash( $_SERVER['REMOTE_ADDR'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	$ua = null;
	if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$ua = wp_unslash( $_SERVER['HTTP_USER_AGENT'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	if (
		empty( $ip ) ||
		empty( $ua ) ||
		empty( $session_data['ip'] ) ||
		empty( $session_data['ua'] ) ||
		$ip !== $session_data['ip'] ||
		$ua !== $session_data['ua']
	) {
		return array(
			'ip'           => $ip,
			'ua'           => $ua,
			'session_data' => $session_data,
			'notify'       => true,
		);
	}

	return null;
}

/**
 * Handle an invalid session validation failure.
 *
 * @param array  $failure Invalid session data.
 * @param string $area Session validation area.
 * @return WP_Error|null REST error for REST requests, otherwise null.
 * @since 1.3.0
 **/
function tn_handle_session_validation_failure( array $failure, string $area = 'wp_admin' ) {
	if ( ! empty( $failure['notify'] ) ) {
		do_action( 'tn_tame_session_non_valid', $failure['ip'], $failure['ua'], $failure['session_data'] );
	}

	wp_logout();

	if ( 'rest_api' === $area ) {
		return new WP_Error(
			'invalid_session',
			__( 'Invalid session.', 'tn-tame-session-defaults' ),
			array( 'status' => 403 )
		);
	}

	wp_die(
		esc_html__( 'Invalid session.', 'tn-tame-session-defaults' ),
		'',
		array( 'response' => 403 )
	);

	return null;
}

/**
 * Validates IP & UA of a session
 *
 * @return void
 * @since 1.1.0
 **/
function tn_validate_session(): void {
	$failure = tn_get_session_validation_failure();

	if ( null !== $failure ) {
		tn_handle_session_validation_failure( $failure );
	}
}

/**
 * Validate front-end requests when configured.
 *
 * @return void
 * @since 1.3.0
 **/
function tn_init_front_end_validate_session(): void {
	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( tn_should_validate_session_area( 'front_end', tn_get_session_validation_request_path() ) ) {
		tn_validate_session();
	}
}

/**
 * Validate REST API requests when configured.
 *
 * @param mixed $result Existing REST authentication result.
 * @return mixed REST authentication result.
 * @since 1.3.0
 **/
function tn_validate_rest_session( $result ) {
	if ( $result instanceof WP_Error || ( null !== $result && true !== $result ) ) {
		return $result;
	}

	if ( ! is_user_logged_in() ) {
		return $result;
	}

	if ( ! tn_should_validate_session_area( 'rest_api', tn_get_session_validation_rest_route() ) ) {
		return $result;
	}

	$failure = tn_get_session_validation_failure();

	if ( null === $failure ) {
		return $result;
	}

	return tn_handle_session_validation_failure( $failure, 'rest_api' );
}

/**
 * Check if we should validate sessions
 *
 * @return void
 * @since 1.1.0
 **/
function tn_init_validate_session(): void {
	// Check if we should validate sessions, defaults to true.
	if (
		apply_filters( 'tn_tame_session_validate_session', true ) &&
		tn_should_validate_session_area( 'wp_admin', tn_get_session_validation_request_path() )
	) {
		tn_validate_session();
	}
}

add_action( 'admin_init', 'tn_init_validate_session' );
add_action( 'template_redirect', 'tn_init_front_end_validate_session' );
add_filter( 'rest_authentication_errors', 'tn_validate_rest_session', 100 );
