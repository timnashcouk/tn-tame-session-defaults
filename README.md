# Tame Session Defaults

Tame Session Defaults is a small WordPress plugin that reduces authentication session lifetimes. Shorter sessions reduce the window of opportunity for session hijacking.

It also includes controls for:

- Shortening session lengths by default.
- Fine-tuning session lengths with filters, including per user or role.
- Validating admin requests against the stored session IP address and user agent.
- Limiting users to one active session by destroying other sessions on login.
- Requiring re-authentication before configured sensitive admin operations.

## Install

This is probably best used as an MU plugin. You can also copy the plugin code into your own starter or skeleton projects.

## Usage

By default, the plugin reduces the normal logged-in session lifetime from 2 days to 2 hours. If "Remember Me" is checked, it reduces the session lifetime from 2 weeks to 24 hours.

### tn_tame_session_default

Filters the default session length. The value is an integer expiry length in seconds. The logged-in user ID is passed as the second argument.

```php
add_filter(
	'tn_tame_session_default',
	function ( int $seconds, int $user_id ): int {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return 30 * MINUTE_IN_SECONDS;
		}

		return 2 * HOUR_IN_SECONDS;
	},
	10,
	2
);
```

### tn_tame_session_default_remember

Filters the session length when "Remember Me" is checked. The value is an integer expiry length in seconds. The logged-in user ID is passed as the second argument.

```php
add_filter(
	'tn_tame_session_default_remember',
	function ( int $seconds, int $user_id ): int {
		return DAY_IN_SECONDS;
	},
	10,
	2
);
```

### tn_tame_session_limit

By default, other sessions are destroyed on login. This filter currently acts as an opt-out: returning `true` skips `wp_destroy_other_sessions()`.

```php
add_filter(
	'tn_tame_session_limit',
	function ( bool $skip_limit, WP_User $user ): bool {
		return user_can( $user, 'manage_options' );
	},
	10,
	2
);
```

### tn_tame_session_validate_session

Controls whether IP address and user-agent validation runs for logged-in admin-area requests.

This runs on `admin_init`, so it covers wp-admin screens, `admin-ajax.php`, and `admin-post.php`. It is not a site-wide front-end or REST API validation layer. Validation is enabled by default.

```php
add_filter( 'tn_tame_session_validate_session', '__return_false' );
```

### tn_tame_session_reauth_operations

Registers sensitive admin operations that should require a fresh session. Each operation can define a maximum session age, capability, admin page, request action, and request method.

If a matching session is stale, GET and HEAD requests redirect to the login form. AJAX, JSON, and unsafe methods receive a 401 response instead.

```php
add_filter(
	'tn_tame_session_reauth_operations',
	function ( array $operations, int $user_id ): array {
		$operations['delete-users'] = array(
			'max_age'    => 15 * MINUTE_IN_SECONDS,
			'capability' => 'delete_users',
			'pages'      => array( 'users.php' ),
			'actions'    => array( 'delete', 'delete-selected' ),
			'methods'    => array( 'GET', 'POST' ),
		);

		return $operations;
	},
	10,
	2
);
```

### tn_tame_session_reauth_redirect_url

Filters the URL passed to `wp_login_url()` when a stale session needs to re-authenticate.

```php
add_filter(
	'tn_tame_session_reauth_redirect_url',
	function ( string $redirect_url, array $operation ): string {
		return admin_url( 'users.php' );
	},
	10,
	2
);
```

## Actions

### tn_tame_session_non_valid

Runs when a session fails IP address or user-agent validation. This is useful for logging or notifications.

```php
add_action(
	'tn_tame_session_non_valid',
	function ( ?string $ip, ?string $user_agent, array $session_data ): void {
		error_log( 'Invalid WordPress session detected.' );
	},
	10,
	3
);
```

### tn_tame_session_reauth_required

Runs when a configured sensitive operation requires re-authentication.

```php
add_action(
	'tn_tame_session_reauth_required',
	function ( array $operation, int $user_id ): void {
		error_log( 'Re-authentication required for user ' . $user_id );
	},
	10,
	2
);
```

## Security

Please see [SECURITY.md](SECURITY.md) for vulnerability reporting instructions.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.
