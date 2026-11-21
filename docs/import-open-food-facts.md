# Importing products from Open Food Facts

Products, and the nutrition, environment and welfare scores derived from
them, come from the Open Food Facts bulk export. The importer reads that file
from disk and makes no network calls.

**Never point the importer at the Open Food Facts API for bulk work.** Their
search API is meant for one call per real user scan. Paging it for every
brand gets rate limited (HTTP 503) and is likely to be blocked. The API path
in `bin/import-openfoodfacts.php` is kept only for single-brand spot checks
(`--brand=NAME`) and small category imports.

## 1. Download the export

The export is rebuilt nightly. Downloading it monthly is plenty.

```bash
mkdir -p ~/ebimport/dump
curl -L -o ~/ebimport/dump/off-products.csv.gz \
  https://static.openfoodfacts.org/data/en.openfoodfacts.org.products.csv.gz
```

The URL redirects to an S3 object, which is why `-L` is needed. The file is
about 1.5 GB. **Keep it compressed:** the importer streams the gzip directly,
so you only need disk space for the `.csv.gz` itself.

Licence: Open Food Facts data is under the Open Database Licence (ODbL 1.0).
The site credits it wherever the data appears; keep that attribution.

## 2. Dry run

Run from the repository root, as the importer's database user:

```bash
php bin/import-openfoodfacts.php --products --from-dump=$HOME/ebimport/dump/off-products.csv.gz
```

This reads the file once, which should take a minute or two for about
4.9 million rows, and writes nothing. It keeps rows that are:

* sold in the UK (`--country=TAG` to change it, `--country=any` for all
  countries), and
* tagged with one of our brands. A brand tag such as `xx:amy-s-kitchen`,
  `en:cadbury` or `fr:alpro` has its prefix up to the first colon stripped,
  then matches our brand name exactly after normalisation (case, accents,
  apostrophes, `&`). Near misses never match.

For each brand the dry run prints:

* products kept, with how many would be added and removed;
* how many products have each grade, per dimension;
* the resulting brand score per dimension, and the change from the stored
  score. Curated (hand-entered) scores are shown as kept.

At the end it prints:

* totals;
* **brands with no products in the dump**, with the tag it looked for, so a
  misspelt brand name can be fixed;
* the largest score changes.

Review that output before applying.

A filtered subset with the same header line also works, plain or gzipped,
for example the pre-filtered `subset-uk-brands.tsv`:

```bash
php bin/import-openfoodfacts.php --products --from-dump=$HOME/ebimport/dump/subset-uk-brands.tsv
```

## 3. Apply

```bash
php bin/import-openfoodfacts.php --products --from-dump=$HOME/ebimport/dump/off-products.csv.gz --apply
```

For each brand with rows in the dump, one transaction:

* writes its products and product scores;
* deletes its products that are no longer in the dump (the dump is a full
  snapshot);
* recomputes its derived brand scores as the mean of its product scores.

What it does not do:

* A brand with **no** rows in the dump is left as it is, in case its tag was
  simply wrong.
* Ratings, notes, owners and curated scores are never touched.

Each product's `retrieved_at` is the dump file's date, and its `source_url`
is its Open Food Facts page.

Running the same dump twice changes nothing; the second run reports every
brand as unchanged.

## Notes

* **Grades.**
  * Nutri-Score A to E maps to 10, 8, 6, 4, 2.
  * Eco-Score (Green-Score) also uses A+ and F: A+ is 10 (as A) and F is 1.
  * "unknown", "not-applicable" and blank grades are left unscored and
    excluded from the brand's mean.
  * The About page's "How scores work" section is generated from the same
    mapping.
* **Products tagged with several of our brands.** Product barcodes are
  unique, so such a product is filed under one brand: the first of its own
  brand tags that we hold. The dry run counts these as "shared".
* **Search index.** After a large import, it is worth running
  `OPTIMIZE TABLE products;`. MariaDB's full-text index on product names can
  occasionally miss a row after many deletes and re-inserts, and a rebuild
  repairs it.
