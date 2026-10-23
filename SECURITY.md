# Security Policy

## Supported versions

Only the latest code on the `main` branch gets security fixes.

## Reporting a vulnerability

Please do NOT open a public issue for security problems.

Instead, use the **Security** tab of this repository and click **Report a vulnerability** to open a private advisory. Include:

- Which file and action is affected
- Steps to reproduce
- What an attacker could do with it

You will get a reply as soon as possible. If the report is confirmed, a fix will be released and you will be credited (unless you prefer to stay anonymous).

## Notes for self-hosters

- Never upload your real `common/config.php` anywhere public. It contains your database password.
- Change the default admin password (`admin123`) immediately after install.
- Turn on Force HTTPS in your hosting panel.
- Keep your copy updated with the latest release.
