# Production verification and Blade cache

The production application is `/var/www/ik-workdesk` on `10.1.20.97:8093`. PHP-FPM runs as `www-data`; deployments and the user queue service run as `r92`.

On 27 October 2026 the dashboard returned HTTP 500 with `touch(): Utime failed: Operation not permitted`. Compiled Blade files were owned by `r92`; Laravel attempted to set an explicit modification time from a PHP-FPM request running as `www-data`. A successful render by the deployment user did not detect this failure.

## Deployment steps

1. Keep source and build artifacts readable by the web process. Use `umask 0022` for checkout and artifact installation; private backups must remain private.
2. Keep `storage` and `bootstrap/cache` directories writable by the application group, with the existing `www-data` group and setgid directory permissions. Avoid recursively changing application source ownership or opening all permissions.
3. Clear compiled views with `php artisan view:clear`. Let PHP-FPM create its own compiled views. Do not run `view:cache` or an `optimize` command that includes view compilation as `r92` against the shared web view directory. If precompiling views is required, run it under the PHP-FPM identity using an explicitly authorized deployment mechanism.
4. Any CLI verification that renders HTML must use a separate compiled-view directory from its first application bootstrap. Create a private directory and set `VIEW_COMPILED_PATH` in the process environment before loading the application. Do not share the web compiled-view directory with CLI smoke checks.
5. Verify authenticated pages through PHP-FPM, including `/panel`, PR, SR, user access configuration and approval pages. A CLI kernel render and an unauthenticated `/login` response alone do not prove that authenticated web pages work.
6. Check that expected denials still return 403, read-only probes perform no database writes, no new application errors appear, maintenance is off and services remain active. Remove temporary probes after use; do not leave public authentication bypass routes.

For the incident above, `view:clear` restored access. Eleven read-only checks then passed through the actual FastCGI socket as UID 33, including dashboard accounts 1/12/17, both request pages, the existing HO draft, SCM settings, user edit and the COO II approval/profile pages. Requester access to SCM settings remained denied.
