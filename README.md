# Tame Session Defaults
A very simple plugin that reduces the WordPress sessions lengths to be shorter. This helps prevents, session hijacking by reducing the window of opportunity to use gathered sessions.
In addition it provides a couple of other use features to prevent session hijacking:
- Shortens sessions by default
- Provide filters, to do fine grain control of session lengths (for example by user or user role)
- Validates requests made against the stored session, logging out where the IP or useragent do not match
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
This is an optional filter that will destroy all other sessions, when a new session logs in. Meaning only a single session may occur at any given time. By default this is set to false, using this filter and setting to true will activate it.

### tn_tame_session_validate_session
This is an optional filter that will check the IP/Useragent of the client and compare it to the stored session details, logging out the client if they do not match. This defaults to true, to disable set filter to false.

It also provides an action

### tn_tame_session_non_valid
This action is triggered if a session is deemed to not be valid, useful to hook into for maybe notification or logging.

## Changelog
You can see the CHANGELOG.md for up to date changes.