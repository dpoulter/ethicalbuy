# Public deployment

How the application expects to run on the public internet at
https://ethicalbuy.net. The tunnel, Caddy, DNS and backups are set up
outside this repository; this page covers what the application needs from
them and every setting it reads.

## Request path and proxy headers

```
visitor -> Cloudflare -> cloudflared (localhost) -> Caddy 127.0.0.1:8097 -> PHP-FPM
```

- PHP sees every public request from `127.0.0.1`. With `TRUST_PROXY=1`, and
  only when the TCP peer is loopback, the app takes the visitor's address from
  `CF-Connecting-IP` and the scheme from `X-Forwarded-Proto`. From any other
  peer both headers are ignored.
- `HTTPS=on` from Caddy also marks a request as secure.
- `/admin` is used only on `ethicalbuy.contelo.ai` over Tailscale. Keep the
  Caddy 404 for `/admin` on the public host; the app enforces the same rule
  with `ADMIN_ALLOWED_HOSTS`.
- Caddy's `php_fastcgi` sends missing paths to `index.php`. The app answers
  anything that is not a page with a real 404, and serves `/robots.txt`,
  `/sitemap.xml`, `/sitemap-pages.xml`, `/sitemap-brands.xml` and
  `/sitemap-products-N.xml` itself. Remove any static `robots.txt` or
  `X-Robots-Tag` header from Caddy so the app's `SITE_INDEXABLE` is the only
  switch.
- The app sends its own security headers (CSP, HSTS, nosniff,
  Referrer-Policy, Permissions-Policy, COOP, X-Frame-Options). Caddy should
  not add conflicting ones.

## Settings

Set as environment variables in the PHP-FPM pool (`env[NAME] = value`).
Unset means the default.

| Variable | Default | Meaning |
| --- | --- | --- |
| `DB_PASSWORD` | required | Database password. The site refuses to start without it. |
| `DB_HOST` | `localhost` | Database host. |
| `DB_NAME` | `ethicalbuy` | Database name. |
| `DB_USER` | `ethicalbuy` | Database user for the site (least privilege, see `dev/setup.sh`). |
| `DB_STATEMENT_TIMEOUT` | `5` | Seconds any one query may run in a web request. Command-line scripts are exempt. |
| `SITE_URL` | `https://ethicalbuy.net` | Base for canonical links, Open Graph and sitemap URLs. No trailing slash needed. |
| `SITE_INDEXABLE` | `0` | `0`: every page sends `noindex, nofollow` and `robots.txt` disallows all. `1`: open to search engines; `robots.txt` names the sitemap. |
| `APP_DEBUG` | `0` | `1` shows PHP errors, and only when the Host is localhost. Never set in production. |
| `TRUST_PROXY` | `0` | `1` to believe `CF-Connecting-IP` and `X-Forwarded-Proto` from a loopback peer. Set to `1` behind the tunnel. |
| `ADMIN_USER` | empty | Admin username. Admin returns 500 until both admin settings are set. |
| `ADMIN_PASSWORD_HASH` | empty | `password_hash()` digest; make one with `php dev/admin-password.php`. |
| `ADMIN_ALLOWED_HOSTS` | empty | Comma-separated host names where `/admin` exists, e.g. `ethicalbuy.contelo.ai`. Anywhere else it is a 404 before any login prompt. Empty means no restriction (local development). |
| `ADMIN_AUTH_MAX_FAILURES` | `5` | Failed admin sign-ins allowed per IP address... |
| `ADMIN_AUTH_WINDOW_MINUTES` | `15` | ...within this many minutes... |
| `ADMIN_AUTH_LOCK_MINUTES` | `15` | ...before that address gets 429 for this long, right password or not. |
| `RATE_BROWSE_PER_MINUTE` | `120` | Requests per minute per visitor to the home, search, brand, product and sitemap pages. Over it: 429 with `Retry-After`. |
| `RATE_CONTACT_PER_HOUR` | `3` | Contact form submissions per visitor per rolling hour. |
| `RATE_CONTACT_PER_DAY` | `10` | Contact form submissions per visitor per rolling day. |
| `CONTACT_MIN_SECONDS` | `3` | A contact form sent faster than this after loading is treated as automated and silently discarded. |
| `TURNSTILE_SITE_KEY` | empty | Cloudflare Turnstile site key. Turnstile is used only when both keys are set; otherwise no third-party script loads. |
| `TURNSTILE_SECRET` | empty | Cloudflare Turnstile secret, verified server side. |
| `IP_HASH_KEY` | derived from `DB_PASSWORD` | Key for the hash of IP addresses in the rate limit table. Set a long random value to keep it independent of the database password. |
| `MESSAGE_RETENTION_DAYS` | `180` | How long contact messages and log rows are kept. Shown in the privacy policy and used by the purge script. |
| `CONTROLLER_NAME` | empty | Data controller named in the privacy policy and terms. Pages show a visible placeholder until set. |
| `CONTACT_EMAIL` | empty | Address for privacy requests and corrections. Placeholder until set. |
| `CACHE_DIR` | system temp directory | Where generated sitemaps are cached for an hour. Must be writable by PHP-FPM. |
| `ADS_ENABLED` | `0` | Reserved for advertising. Nothing uses it yet beyond an empty CSP hook and an unwritten privacy section; leave at `0`. |

## Database

Apply `migrations/008_rate_limits.sql` and grant the site:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON ethicalbuy.rate_limits TO 'ethicalbuy'@'localhost';
```

The purge script runs as the importer account, which needs:

```sql
GRANT SELECT, DELETE ON ethicalbuy.contact_messages TO 'ethicalbuy_import'@'localhost';
GRANT SELECT, DELETE ON ethicalbuy.message_log      TO 'ethicalbuy_import'@'localhost';
GRANT SELECT, DELETE ON ethicalbuy.rate_limits      TO 'ethicalbuy_import'@'localhost';
```

## Rate limits

Counted per visitor (by `CF-Connecting-IP` behind the tunnel) in the
`rate_limits` table, per minute, as a keyed hash of the address. Rows older
than two days are removed as the site runs and by the purge script. If the
database is down, limits fail open; the pages fail on their own.

| What | Limit | Response |
| --- | --- | --- |
| Home, search, brand, product, sitemaps | 120 a minute | 429, `Retry-After` |
| Contact form | 3 an hour, 10 a day | 429, `Retry-After`, calm message |
| Admin sign-in | 5 failures in 15 minutes | 429 for 15 minutes |

## Soft launch, then indexing

1. Launch with `SITE_INDEXABLE=0` (the default). Check
   `curl -sI https://ethicalbuy.net/ | grep -i x-robots` shows
   `noindex, nofollow` and `/robots.txt` is `Disallow: /`.
2. When ready, set `SITE_INDEXABLE=1`, reload PHP-FPM, and check
   `/robots.txt` names `https://ethicalbuy.net/sitemap.xml` and pages no
   longer carry `noindex`. Then submit the sitemap in Google Search Console
   and Bing Webmaster Tools.

## Purging old messages

Dry run, then daily from cron as the importer account:

```sh
DB_USER=ethicalbuy_import DB_PASSWORD=... php bin/purge-messages.php
17 3 * * * cd /srv/ethicalbuy && DB_USER=ethicalbuy_import DB_PASSWORD=... php bin/purge-messages.php --apply
```

It prints counts and dates only, never message contents.

## Checks after deploying

```sh
curl -s -o /dev/null -w '%{http_code}\n' https://ethicalbuy.net/nope.php        # 404
curl -s -o /dev/null -w '%{http_code}\n' https://ethicalbuy.net/admin/          # 404
curl -sI https://ethicalbuy.net/ | grep -i -e content-security -e strict-transport -e x-robots
php tests/smoke.php                                                             # against the dev database
```

To check the CSP in a real browser, run `node dev/csp-check.js https://ethicalbuy.net`.
