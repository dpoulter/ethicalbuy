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
bin/          command-line importers
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

## Personalised ratings

Ethics aren't one number. A reader focused on climate wants carbon weighted
heavily; a vegan wants animal welfare to dominate; someone avoiding processed
food cares about nutrition. So a brand carries a score per **dimension**, and
the number shown is those dimensions weighted by priorities the reader sets on
`/priorities.php`.

| Dimension | Where its score comes from |
| --- | --- |
| Our overall view | `brands.rating`, written by you in `/admin` |
| Environment & carbon | Eco-Score / Green-Score via Open Food Facts |
| Nutrition | Nutri-Score via Open Food Facts |
| Diet & animal welfare | Open Food Facts label tags |
| Ownership & business size | Curated in `/admin/scores.php` |

The same data ranks differently for different people. In the sample data
Café Verde scores **4.8/10** for a climate-first reader and **7.8/10** for a
vegan; Amber Mill inverts it at **6.5** and **4.1**.

### Two rules the engine enforces

**Unknown is not zero.** A brand with no carbon data is not a brand with bad
carbon data. Missing dimensions are excluded from the weighted average, never
counted as zero, and never silently. Leave a score blank in `/admin/scores.php`
and it means "not assessed", which is a different claim from a low score.

**The reader is told what the score is missing.** `personal_score()` returns
the fraction of requested weight it could actually assess. Below 50% the page
says so in plain terms, so `8.2/10` never hides that it ignored the one thing
the reader cared about most.

A dimension weighted **Ignore** is dropped entirely: the reader said it doesn't
matter, so its absence isn't a gap either.

### Where it runs, and why

Scoring happens **server-side, in PHP, once**. Priorities live in a
first-party cookie holding five small integers.

That is a deliberate choice on both counts. Re-implementing the weighting in
JavaScript is how this site originally ended up with two copies of its rating
logic that disagreed — see `rating_class()` below. And a cookie means no
account, no password, no server-side profile, and nothing personal stored: no
GDPR duty and no consent banner, since a functional preference cookie the user
asked for is exempt under PECR. Accounts can be layered on later without
touching the engine.

`/admin/scores.php` edits the per-dimension scores. The editorial rating stays
on the brand form and is injected as its own dimension at read time rather than
copied, so the two can never drift.

### Rating colours

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

## Importing real data

```sh
# dry run -- prints what it would change, writes nothing
DB_USER=ethicalbuy_import DB_PASSWORD=... \
  php bin/import-openfoodfacts.php --category=en:chocolates --contact=you@example.com

# same again with --apply to write
```

Run `migrations/002_brand_provenance.sql` first. The brand page will fail to
load until you do, because it selects the provenance columns.

### The rule: import facts, never ratings

An import writes brand name, category, product type, where it is sold, and
which certifications a source records. It **never** writes a rating and
**never** writes notes. New brands arrive as *Not rated* and wait for you in
`/admin`.

That is a deliberate legal and editorial position, not caution for its own
sake:

- **Someone else's ratings are their property.** In the UK a compiled database
  attracts *database right* (Copyright and Rights in Databases Regulations
  1997) on top of copyright. Ethical Consumer and similar publishers are
  subscription co-operatives whose ratings are the product. Copying them is
  infringement, and doing it to build a competing free site would be a poor
  way to treat the people doing the underlying research. If you want their
  data, licence it.
- **A published rating carries defamation risk.** Saying a named company scores
  1/10 for serious ethical problems is a statement that can be sued over, and
  UK law favours the claimant. The defences that matter — truth, and honest
  opinion based on facts you indicate — both require *you* to hold the
  evidence. A scraped score gives you the liability without the file to defend
  it. Ratings you derive from sourced facts, with the facts shown, are the
  defensible version.
- **Facts with a citation are neither problem.** Ownership, certifications and
  stockists are verifiable, and every imported row records where it came from.

### Safety properties

The importer is built so a bad run cannot quietly damage your data:

- **Dry run by default.** It writes only with `--apply`.
- **Never overwrites a value that is already there.** It fills blanks. Your
  edits always win.
- **Never touches `rating` or `notes`.**
- **Fails loudly.** A partial failure — for example lacking privilege to create
  a category — aborts that brand rather than filing it wrongly and reporting
  success.
- **Idempotent.** Re-running reports `unchanged` rather than duplicating.
- **Identifies itself.** It refuses to run without `--contact`, sends a
  descriptive User-Agent, and sleeps between requests. On the first run use
  `--dump` to print a raw product and confirm the field names.

### Database user

The importer runs as its own account (`ethicalbuy_import` in dev), because it
needs INSERT on `categories`, which the web application must never have. It has
no DELETE anywhere.

### Attribution

Open Food Facts is ODbL 1.0: reuse is allowed, including commercially, with
attribution and share-alike. The brand page credits the source, licence and
retrieval date, and states that the rating and notes are your own. Keep that
block if you keep the data. For a bulk import prefer their
[data export](https://world.openfoodfacts.org/data) over paging the API.

### Companies House: who owns the brand

```sh
export CH_API_KEY=...            # free, from the link below
DB_USER=ethicalbuy_import DB_PASSWORD=... \
  php bin/import-companies-house.php --contact=you@example.com
# add --apply to write
```

Run `migrations/003_owner_companies.sql` first. Get a key at
[developer.company-information.service.gov.uk](https://developer.company-information.service.gov.uk/).

This resolves each owner in your database to its registered company, and reads
the corporate parent from the persons-with-significant-control register. Two
rules govern it, both stricter than they need to be for a reason.

**It never guesses an ownership link.** "Northwind Foods Group" matches more
than one real registered company. Wiring a brand to the wrong one publishes a
false statement about a real business — wrong, and precisely the sort of thing
that draws a defamation claim. A link is made automatically only when the
normalised name matches *exactly*, the match is *unique*, and the company is
*active*. Everything else lands in `/admin/owners.php` with the candidates
ranked and a note explaining the doubt, for you to pick. In practice most
owners need a human decision, which is the correct outcome rather than a
shortcoming.

Name normalisation handles the usual noise: `Acme Ltd`, `Acme Limited`,
`The Acme Company` and `ACME CO. LTD.` all compare equal, and `Kestrel & Fen`
matches `KESTREL AND FEN LIMITED`.

**It never stores personal data.** The PSC register names real people, with
partial dates of birth, nationality and country of residence. Copying that onto
a public consumer site would put you inside UK GDPR for no product benefit, so
individual PSCs are discarded before they reach the database — only corporate
controlling entities are kept. Those answer "which company owns this company",
which is the question users are actually asking. The test suite asserts that no
name, date of birth or nationality from an individual PSC can reach storage.

Confirming a match in `/admin` can only promote a candidate the importer
actually fetched, so a forged form field cannot invent a company number.

### Other UK sources worth adding

All open-licensed, none requiring a scrape:

| Source | Licence | Gives you |
| --- | --- | --- |
| [Modern Slavery Statement Registry](https://modern-slavery-statement-registry.service.gov.uk/) | Open Government Licence | Whether a company has filed a statement, and its text |
| [B Corp directory](https://www.bcorporation.net/en-us/find-a-b-corp/) | Check terms before bulk use | Certified B Corps |
| Environment Agency public registers | Open Government Licence | Permits, pollution incidents |

Companies House is the highest-value next one: it answers the ownership
question directly, and ownership is what most users are actually asking about.

## Tests

```sh
php tests/smoke.php
```

310 assertions covering the helpers, the search builder's whitelisting, admin
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
