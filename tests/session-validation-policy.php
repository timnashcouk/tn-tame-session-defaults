<?php
/**
 * Tests for session validation area policy.
 *
 * @package TN_Tame_Session_Defaults
 */

require __DIR__ . '/bootstrap.php';

global $tn_filters;

tn_assert_same( true, isset( $tn_filters['template_redirect'] ), 'front-end validation hook is registered' );
tn_assert_same( true, isset( $tn_filters['rest_authentication_errors'] ), 'REST validation hook is registered' );
tn_assert_same( 100, tn_get_registered_filter_priority( 'rest_authentication_errors', 'tn_validate_rest_session' ), 'REST validation runs after core REST cookie authentication' );

tn_reset_test_request();
$tn_test_is_user_logged_in = true;
$tn_test_user_id           = 123;
$tn_test_session_token     = 'token';
$tn_test_session_data      = false;
$failure_without_data      = tn_get_session_validation_failure();

tn_assert_same(
	array(
		'ip'           => null,
		'ua'           => null,
		'session_data' => array(),
		'notify'       => false,
	),
	$failure_without_data,
	'missing stored session data produces an invalid-session failure without notification data'
);

$rest_failure = tn_handle_session_validation_failure( $failure_without_data, 'rest_api' );
tn_assert_same( true, $rest_failure instanceof WP_Error, 'REST validation failure handler returns a WP_Error' );
tn_assert_same( 'invalid_session', $rest_failure->code, 'REST validation failure handler uses invalid_session code' );
tn_assert_same( true, $tn_test_did_logout, 'validation failure handler logs out the user' );
tn_assert_same( array(), $tn_test_actions, 'missing session data does not fire invalid-session notification action' );

tn_reset_test_filters();
tn_assert_same(
	array(
		'wp_admin'  => true,
		'rest_api'  => false,
		'front_end' => false,
	),
	tn_get_session_validation_areas(),
	'default validation policy only enables the existing wp-admin area'
);

tn_reset_test_filters();
add_filter(
	'tn_tame_session_validate_session_areas',
	function ( array $areas ): array {
		$areas['front_end'] = true;
		$areas['rest_api']  = array( '/wp/v2/users', '/my-plugin/v1/' );

		return $areas;
	}
);

tn_assert_same( true, tn_should_validate_session_area( 'front_end', '/anything/' ), 'front-end true validates all front-end paths' );
tn_assert_same( true, tn_should_validate_session_area( 'rest_api', '/wp/v2/users/12' ), 'REST prefix validates matching child routes' );
tn_assert_same( true, tn_should_validate_session_area( 'rest_api', '/my-plugin/v1/settings' ), 'custom REST prefix validates matching child routes' );
tn_assert_same( false, tn_should_validate_session_area( 'rest_api', '/wp/v2/posts' ), 'REST prefix does not validate unrelated routes' );

tn_reset_test_filters();
add_filter(
	'tn_tame_session_validate_session_areas',
	function ( array $areas ): array {
		$areas['front_end'] = array( '/account/', '/checkout/' );

		return $areas;
	}
);

tn_assert_same( true, tn_should_validate_session_area( 'front_end', '/account/profile/' ), 'front-end prefix validates matching child paths' );
tn_assert_same( false, tn_should_validate_session_area( 'front_end', '/my-account/' ), 'front-end prefix does not validate partial path names' );
tn_assert_same( true, tn_should_validate_session_area( 'front_end', '/account' ), 'front-end prefix validates exact path without trailing slash' );
tn_assert_same( false, tn_should_validate_session_area( 'front_end', '/accounting/' ), 'front-end prefix does not validate sibling paths that share leading text' );

tn_reset_test_filters();
tn_reset_test_request();
$tn_policy_filter_calls = 0;
add_filter(
	'tn_tame_session_validate_session_areas',
	function ( array $areas ) use ( &$tn_policy_filter_calls ): array {
		++$tn_policy_filter_calls;

		$areas['front_end'] = true;
		$areas['rest_api']  = true;

		return $areas;
	}
);

tn_init_front_end_validate_session();
tn_validate_rest_session( null );

tn_assert_same( 0, $tn_policy_filter_calls, 'anonymous front-end and REST requests do not evaluate validation area policy' );

tn_reset_test_filters();
tn_reset_test_request();
add_filter(
	'tn_tame_session_validate_session_areas',
	function ( array $areas ): array {
		$areas['rest_api'] = true;

		return $areas;
	}
);

$tn_test_is_user_logged_in = true;
$tn_test_user_id           = 123;
$tn_test_session_token     = 'token';
$tn_test_session_data      = array(
	'ip' => '203.0.113.10',
	'ua' => 'original-user-agent',
);
$_SERVER['REMOTE_ADDR']    = '203.0.113.99';
$_SERVER['HTTP_USER_AGENT'] = 'changed-user-agent';
$_GET['rest_route']        = '/wp/v2/users';

$rest_error = tn_validate_rest_session( null );

tn_assert_same( true, $rest_error instanceof WP_Error, 'REST invalid sessions return a WP_Error' );
tn_assert_same( 'invalid_session', $rest_error->code, 'REST invalid sessions use invalid_session code' );
tn_assert_same( array( 'status' => 403 ), $rest_error->data, 'REST invalid sessions return 403 status data' );
tn_assert_same( true, $tn_test_did_logout, 'REST invalid sessions log the user out' );

tn_reset_test_request();
$tn_test_is_user_logged_in = true;
$tn_test_user_id           = 123;
$tn_test_session_token     = 'token';
$tn_test_session_data      = array(
	'ip' => '203.0.113.10',
	'ua' => 'original-user-agent',
);
$_SERVER['REMOTE_ADDR']    = '203.0.113.99';
$_SERVER['HTTP_USER_AGENT'] = 'changed-user-agent';
$_GET['rest_route']        = '/wp/v2/users';

$rest_cookie_success_error = tn_validate_rest_session( true );

tn_assert_same( true, $rest_cookie_success_error instanceof WP_Error, 'REST cookie-auth success still runs plugin validation' );
tn_assert_same( 'invalid_session', $rest_cookie_success_error->code, 'REST cookie-auth success can be rejected by invalid session validation' );
tn_assert_same( true, $tn_test_did_logout, 'REST cookie-auth success invalid sessions log the user out' );

tn_reset_test_request();
$tn_test_is_user_logged_in   = true;
$tn_test_user_id             = 123;
$tn_test_auth_cookie_token   = 'auth-token';
$tn_test_auth_cookie_user_id = 123;
$tn_test_session_data        = array(
	'ip' => '203.0.113.10',
	'ua' => 'original-user-agent',
);
$_SERVER['REMOTE_ADDR']      = '203.0.113.99';
$_SERVER['HTTP_USER_AGENT']  = 'changed-user-agent';

$auth_cookie_failure = tn_get_session_validation_failure();

tn_assert_same( 'auth-token', $tn_test_requested_session_token, 'auth cookie token is used when logged-in token is unavailable' );
tn_assert_same( true, is_array( $auth_cookie_failure ), 'auth-cookie-only sessions are still validated' );

echo "PASS\n";
