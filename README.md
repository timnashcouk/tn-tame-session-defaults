# Tame Session Defaults
A very simple plugin that reduces the WordPress sessions lengths to be shorter. This helps prevents, session hijacking by reducing the window of opportunity to use gathered sessions.
In addition it provides a couple of other use features to prevent session hijacking:
- Shortens sessions by default
- Provide filters, to do fine grain control of session lengths (for example by user or user role)
- Validates selected logged-in request areas against the stored session, logging out where the IP or useragent do not match
- Optionally limits the number of sessions a user can have to 1

## Install
This is probably best used as a MU Plugin, or consider simply taking the code and using in your own skeleton projects.

## Usage
By default it sets the logged in time for a user to 2 hours from 2 days, and for a logged in user with the remember me ticked from 2 weeks to 24 hours. 

It provides some filters to help fine tune this:

### tn_tame_session_default
This is the session length it takes an integer that represents the expiry length in seconds. It passes the user_id of the logged in user.

### tn_tame_session_default_remember
This is the session length if the remember me box is ticked it takes an integer that represents the expiry length in seconds. It passes the user_id of the logged in user.

### tn_tame_session_limit
This filter currently acts as an opt-out for destroying other sessions. By default, other sessions are destroyed on login. Returning `true` from this filter skips `wp_destroy_other_sessions()`.

Note: this is inverted from what the filter name may imply.

### tn_tame_session_validate_session
This filter controls whether session IP/User-Agent validation runs for logged-in admin-area requests. This currently runs on `admin_init`, so it covers wp-admin screens, `admin-ajax.php`, and `admin-post.php`. This defaults to true, to disable set filter to false.

### tn_tame_session_validate_session_areas
This filter controls which logged-in request areas should run session IP/User-Agent validation.

The default policy matches the original behaviour: wp-admin validation is enabled, front-end and REST API validation are disabled.

Area values can be:

- `true` to validate all logged-in requests in that area.
- `false` to skip validation for that area.
- An array of path or route prefixes to validate only matching requests.

```php
add_filter(
	'tn_tame_session_validate_session_areas',
	function ( array $areas ): array {
		$areas['front_end'] = array( '/account/', '/checkout/' );
		$areas['rest_api']  = array( '/wp/v2/users', '/my-plugin/v1/' );

		return $areas;
	}
);
```

Available areas are:

- `wp_admin` for wp-admin screens, `admin-ajax.php`, and `admin-post.php`.
- `front_end` for front-end requests.
- `rest_api` for REST API requests.

Prefix matching is segment-aware. For example, `/account/` matches `/account` and `/account/profile/`, but not `/accounting/` or `/my-account/`.

It also provides an action

### tn_tame_session_non_valid
This action is triggered if a session is deemed to not be valid, useful to hook into for maybe notification or logging.

## Changelog
You can see the CHANGELOG.md for up to date changes.
