<?php
/**
 * Tests for stale-session reauthentication helpers.
 *
 * @package TN_Tame_Session_Defaults
 */

require __DIR__ . '/bootstrap.php';

tn_reset_test_request();
tn_assert_same( true, tn_session_request_value_matches( array(), 'delete' ), 'empty request value allow list matches any value' );
tn_assert_same( true, tn_session_request_value_matches( 'delete', 'delete' ), 'scalar request value allow list matches the same value' );
tn_assert_same( false, tn_session_request_value_matches( array( 'edit' ), 'delete' ), 'request value allow list rejects missing values' );

$_REQUEST['action'] = 'delete';
$_REQUEST['bulk']   = array( 'delete' );
tn_assert_same( 'delete', tn_get_session_request_value( 'action' ), 'scalar request values are returned as strings' );
tn_assert_same( '', tn_get_session_request_value( 'bulk' ), 'array request values are ignored' );
tn_assert_same( '', tn_get_session_request_value( 'missing' ), 'missing request values return an empty string' );

$_SERVER['REQUEST_METHOD'] = 'post';
tn_assert_same( 'POST', tn_get_session_request_method(), 'request methods are normalized to uppercase' );

tn_reset_test_request();
$_SERVER['REQUEST_METHOD'] = 'GET';
tn_assert_same( true, tn_session_reauth_can_redirect(), 'GET requests can redirect for reauthentication' );
$_SERVER['REQUEST_METHOD'] = 'POST';
tn_assert_same( false, tn_session_reauth_can_redirect(), 'POST requests cannot redirect for reauthentication' );

tn_reset_test_request();
$GLOBALS['pagenow']      = 'users.php';
$_SERVER['QUERY_STRING'] = 'action=delete&user=123';
tn_assert_same( 'https://example.org/wp-admin/users.php?action=delete&user=123', tn_get_session_reauth_redirect_url(), 'reauth redirect URL keeps the current admin query string' );

tn_reset_test_filters();
tn_reset_test_request();
$tn_test_is_user_logged_in = true;
$tn_test_user_id           = 123;
$tn_test_session_token     = 'token';
$tn_test_session_data      = array( 'login' => 100 );
tn_assert_same( 20, tn_get_current_session_age( 120 ), 'session age is calculated from stored login time' );
tn_assert_same( 0, tn_get_current_session_age( 90 ), 'session age is never negative' );
tn_assert_same( false, tn_session_needs_reauthentication( array( 'max_age' => 30 ), 120 ), 'fresh sessions do not need reauthentication' );
tn_assert_same( true, tn_session_needs_reauthentication( array( 'max_age' => 10 ), 120 ), 'stale sessions need reauthentication' );
tn_assert_same( false, tn_session_needs_reauthentication( array( 'max_age' => 0 ), 120 ), 'operations without max age do not need reauthentication' );

tn_reset_test_request();
$tn_test_is_user_logged_in = true;
$tn_test_user_id           = 123;
$tn_test_session_token     = 'token';
$tn_test_session_data      = array();
tn_assert_same( null, tn_get_current_session_age( 120 ), 'session age is unavailable without a stored login time' );
tn_assert_same( true, tn_session_needs_reauthentication( array( 'max_age' => 10 ), 120 ), 'operations need reauthentication when session age is unavailable' );

tn_reset_test_filters();
tn_reset_test_request();
$GLOBALS['pagenow']       = 'users.php';
$_REQUEST['action']       = 'delete';
$_SERVER['REQUEST_METHOD'] = 'POST';
$tn_test_current_user_can = array( 'delete_users' => true );
add_filter(
	'tn_tame_session_reauth_operations',
	function ( array $operations, int $user_id ): array {
		$operations['delete-users'] = array(
			'max_age'    => 300,
			'capability' => 'delete_users',
			'pages'      => array( 'users.php' ),
			'actions'    => array( 'delete' ),
			'methods'    => array( 'POST' ),
		);

		return $operations;
	},
	10,
	2
);

tn_assert_same(
	array(
		'max_age'    => 300,
		'capability' => 'delete_users',
		'pages'      => array( 'users.php' ),
		'actions'    => array( 'delete' ),
		'methods'    => array( 'POST' ),
		'id'         => 'delete-users',
	),
	tn_get_sensitive_session_operation(),
	'sensitive operation matching adds the operation ID from the filter key'
);

tn_reset_test_request();
$GLOBALS['pagenow']       = 'users.php';
$_REQUEST['action']       = 'delete';
$_SERVER['REQUEST_METHOD'] = 'POST';
$tn_test_current_user_can = array( 'delete_users' => false );
tn_assert_same( null, tn_get_sensitive_session_operation(), 'sensitive operations are skipped when the current user lacks capability' );

echo "PASS\n";
