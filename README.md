# Tame Session Defaults
A very simple plugin that reduces the WordPress sessions lengths to be shorter. This helps prevents, session hijacking by reducing the window of opportunity to use gathered sessions.

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

## Changelog
You can see the CHANGELOG.md for up to date changes.