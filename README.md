# Ethical Buy

A small PHP site for looking up the ethical rating of grocery brands. Visitors
can browse brands by category or search for a brand by name.

## Layout

```
public/       web root -- the only directory the web server should serve
  index.php     category browser
  search.php    brand search with filters
  brand.php     single brand detail, with alternatives in the same category
  about.php     what the site is and how ratings work
  contact.php   contact form
  admin/        brand CRUD, behind HTTP Basic auth
  vendor/       vendored front-end libraries
includes/     configuration and helpers (not web-accessible)
templates/    page fragments rendered by render() (not web-accessible)
  partials/     small includes shared between templates
  admin/        admin screens
migrations/   one-off SQL, applied by hand in filename order
dev/          local development schema, seed data and setup script
tests/        smoke.php -- run it before every deploy
```

`templates/header.php` opens the HTML document and `templates/footer.php`
closes it. Every other template is a **fragment**: it must not emit
`<!doctype>`, `<html>`, or `<body>`.

## Requirements

- PHP 8.0+ with `pdo_mysql`
- MySQL or MariaDB
- A web server with its document root set to `public/`

## Configuration

Secrets come from the environment, not from source. Copy `.env.example` and
set the variables in your web server config, systemd unit, or shell:

| Variable      | Required | Default                          | Notes                                        |
| ------------- | -------- | -------------------------------- | -------------------------------------------- |
| `DB_PASSWORD` | yes      | —                                | App exits with a 500 if unset                |
| `DB_HOST`     | no       | `localhost`                      |                                              |
| `DB_NAME`     | no       | `ethicalbuy`                     |                                              |
| `DB_USER`     | no       | `ethicalbuy`                     |                                              |
| `SITE_URL`    | no       | `https://ethicalbuy.duckdns.org` | Used to build redirects                      |
| `APP_DEBUG`   | no       | `0`                              | `1` shows PHP errors. Never enable in prod   |

With Apache and mod_php, for example:

```apache
SetEnv DB_PASSWORD "..."
SetEnv DB_USER "ethicalbuy"
```

## Database

The app reads one view and writes to two log tables. Column names matter;
the underlying tables behind `brand_v` are up to you.

```sql
-- Read by get_categories(), search_brands(), search_categories()
CREATE OR REPLACE VIEW brand_v AS
SELECT
    brand,         -- varchar, brand name
    category,      -- varchar, e.g. 'Dairy'; NULL shows as "Uncategorised"
    type,          -- varchar, product type
    owner,         -- varchar, parent company
    notes,         -- text, free-form commentary
    availability,  -- varchar, where to buy
    rating         -- numeric 1-10, or NULL when unrated
FROM ...;

-- Written by write_log()
CREATE TABLE message_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    module       VARCHAR(100),
    message_text TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Written by log_job()
CREATE TABLE jobs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    job_name   VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Give the app's database user `SELECT` on `brand_v` and `INSERT` on the log and
contact tables — nothing more.

### Migrations

Apply anything in `migrations/` by hand, in filename order:

```sh
mysql -u root -p ethicalbuy < migrations/001_contact_messages.sql
```

`001_contact_messages.sql` creates the table behind the contact form. **The
contact page will report a save failure until it is applied.**

### Ratings

`rating_class()` in `includes/functions.php` is the single source of truth for
rating colours. `index.php` computes the class server-side and passes it to the
browser, so PHP and JavaScript cannot disagree.

| Rating   | Bootstrap suffix |
| -------- | ---------------- |
| 1–3      | `danger`         |
| 4        | `warning`        |
| 5–6      | `secondary`      |
| 7–8      | `primary`        |
| 9–10     | `success`        |
| NULL     | none             |

## Conventions

- **All SQL goes through `query()` with bound parameters.** Never concatenate a
  value into a statement. `like_escape()` escapes `%` and `_` in user input
  before it is wrapped in wildcards.
- **Anything that cannot be a bound parameter must be whitelisted.** Column and
  sort names are structure, not values, so `build_brand_search()` resolves them
  against `SEARCH_FIELDS` and `SEARCH_SORTS` and falls back to a safe default.
  It returns `[$sql, $params]` so this can be tested without a database.
- **All output is escaped**: `e()` for HTML, `json_for_html()` for data
  embedded in a `<script>` block, `urlencode()` for values put in a query
  string.
- **Every state-changing form carries a CSRF token**: emit `csrf_field()` in
  the form and check `csrf_valid()` before acting on the POST. Reads (search,
  browse) use GET and need no token.
- Validation errors are an array keyed by field name; entries with integer keys
  are treated as form-wide. Templates re-display submitted values so a failed
  submission never loses the user's typing.
- `apologize()` renders a whole page and exits, so it must be called before any
  output — from `public/*.php`, never from inside a template.
- `redirect()` sends a relative `Location`, which is valid per RFC 7231 and
  avoids trusting the client-supplied `Host` header. Use it after a successful
  POST so a refresh doesn't resubmit.

## Admin

`/admin` lists every brand and lets you create, edit and delete them. Writes go
to the `brands` base table, not to `brand_v`, because a multi-table view is not
reliably writable.

### Enabling it

```sh
php dev/admin-password.php 'a long admin password'
# ADMIN_PASSWORD_HASH='$2y$12$...'
```

Put that hash and a username in the environment:

```
ADMIN_USER=yourname
ADMIN_PASSWORD_HASH='$2y$12$...'
```

Only the hash is ever stored. If either variable is missing, `/admin` returns
500 and stays shut — it fails closed, so a misconfigured deploy locks the admin
area rather than opening it.

### What it enforces

- **HTTPS.** Basic credentials are base64, not encrypted, so `require_admin()`
  refuses to run over plain HTTP. `localhost` is exempt for development.
  `X-Forwarded-Proto` is only believed when `TRUST_PROXY=1`, since any client
  can forge that header against a directly reachable app.
- **CSRF on every write.** Create, edit and delete all require a valid token.
- **Deletes are POST-only.** A GET to `delete.php` shows a confirmation page
  and changes nothing, so no link or crawler can destroy data.
- **An audit trail.** Every create, edit and delete is recorded in
  `message_log`.

### If the login prompt loops under PHP-FPM or CGI

`PHP_AUTH_USER` is only populated automatically under mod_php. Everywhere else
the web server must forward the header. The code already falls back to reading
`Authorization` itself, but the server has to pass it through:

```apache
# Apache 2.4.13+
CGIPassAuth On
# older Apache
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

nginx passes it via the standard `fastcgi_params` (`HTTP_AUTHORIZATION`); if
you have a trimmed config, add it back.

### Optional second layer

The PHP gate travels with the code and works on any host. If you want
belt-and-braces, add web-server auth in front of it as well:

```apache
<Directory /var/www/ethicalbuy/public/admin>
    AuthType Basic
    AuthName "Ethical Buy admin"
    AuthUserFile /etc/apache2/ethicalbuy.htpasswd
    Require valid-user
</Directory>
```

```nginx
location /admin/ {
    auth_basic "Ethical Buy admin";
    auth_basic_user_file /etc/nginx/ethicalbuy.htpasswd;
}
```

Basic auth has no lockout or rate limiting. If `/admin` is internet-facing,
put fail2ban or an equivalent on the 401s in your access log.

## Tests

```sh
php tests/smoke.php
```

164 assertions covering the helpers, the search builder's whitelisting, admin
validation, and every template rendered with hostile input (script payloads in
every field, quotes and apostrophes in names, `</script>` in free text). It
exits non-zero on failure, so it drops straight into CI. Most of it needs no
database; the `validate_brand` section uses the local development one.

## Local development

`dev/setup.sh` builds a working database from nothing: it creates the schema,
loads sample data, applies the migrations, and grants the app user least
privilege. It needs a running MySQL or MariaDB you can reach as an admin.

```sh
./dev/setup.sh

DB_PASSWORD=ethicalbuy_dev APP_DEBUG=1 php -S 127.0.0.1:8000 -t public
```

- `dev/schema.sql` is a **reconstruction** of the tables behind `brand_v`,
  inferred from the columns the app reads. It is not a copy of production.
  Replace it with `mysqldump --no-data ethicalbuy > dev/schema.sql` when you
  have the real thing.
- `dev/seed.sql` contains **entirely invented** brands and companies. Real
  companies are avoided deliberately so no fabricated ethical rating can be
  mistaken for a real assessment. Never load it into production.

The seed data is chosen to exercise the awkward cases: every rating band plus
unrated brands, a brand with no category, a row that is almost entirely NULL,
apostrophes and double quotes in names, and a non-ASCII brand name that will
catch a broken connection charset.

## Front-end

Bootstrap 5.3.8 is vendored under `public/vendor/bootstrap/`. There are no
other front-end dependencies, no build step, and no CDN: the site renders
correctly on a network that cannot reach jsDelivr, which is also what makes it
screenshot-testable in CI.

To upgrade:

```sh
npm install bootstrap@5.3.x --no-save --prefix /tmp/bs
cp /tmp/bs/node_modules/bootstrap/dist/css/bootstrap.min.css public/vendor/bootstrap/
cp /tmp/bs/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js public/vendor/bootstrap/
```
