# Changelog

This contains all the major changes between versions.

### Unreleased

- [Feature] - Added area-based session validation controls for wp-admin, front-end, and REST API requests.
- [Feature] - Added `tn_tame_session_validate_session_areas`.

### V1.2.1 16th Jun 2026

- [Docs] - Added a security policy and linked to it from the README.
- [Docs] - Cleaned up README language and added usage examples.
- [Changed] - Added a GitHub Actions release workflow that builds a plugin zip when the plugin version changes on `main`.

### V1.2.0 16th Jun 2026

- [Feature] - Added opt-in stale session reauthentication for configured sensitive admin operations.
- [Feature] - Added `tn_tame_session_reauth_operations`, `tn_tame_session_reauth_redirect_url`, and `tn_tame_session_reauth_required`.
- [Feature] - Added helpers for reading current session data and session age.

### V1.1.0 17th Feb 2024

- [Feature] - Added the ability to compare a session's stored IP/User-Agent with the client's IP/User-Agent. If they do not match, force a logout.
- [Typo] - Fixed a small typo in the description.

### V1.0.0 6th Feb 2024

- [Feature] - Initial implementation.
