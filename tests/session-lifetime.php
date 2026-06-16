<?php
/**
 * Tests for session lifetime and login session limiting.
 *
 * @package TN_Tame_Session_Defaults
 */

require __DIR__ . '/bootstrap.php';

tn_reset_test_filters();
tn_assert_same( 2 * 60 * 60, tn_set_session_length( 2 * 24 * 60 * 60, 123, false ), 'normal sessions are reduced to the default session length' );
tn_assert_same( 24 * 60 * 60, tn_set_session_length( 14 * 24 * 60 * 60, 123, true ), 'remembered sessions are reduced to the remember default session length' );
tn_assert_same( 300, tn_set_session_length( 300, 123, false ), 'shorter existing session expiries are preserved' );

add_filter(
	'tn_tame_session_default',
	function ( int $seconds, int $user_id ): int {
		return 600;
	},
	10,
	2
);
add_filter(
	'tn_tame_session_default_remember',
	function ( int $seconds, int $user_id ): int {
		return 1200;
	},
	10,
	2
);

tn_assert_same( 600, tn_set_session_length( 3600, 123, false ), 'normal session length filter changes the default expiry' );
tn_assert_same( 1200, tn_set_session_length( 3600, 123, true ), 'remember session length filter changes the remember expiry' );

tn_reset_test_filters();
tn_reset_test_request();
tn_session_limit( 'tim', new WP_User() );
tn_assert_same( 1, $tn_test_destroy_other_sessions_count, 'login destroys other sessions by default' );

tn_reset_test_filters();
tn_reset_test_request();
add_filter(
	'tn_tame_session_limit',
	function ( bool $skip_limit, WP_User $user ): bool {
		return true;
	},
	10,
	2
);
tn_session_limit( 'tim', new WP_User() );
tn_assert_same( 0, $tn_test_destroy_other_sessions_count, 'session limit filter can skip destroying other sessions' );

echo "PASS\n";
