# Schema

Describe a plugin's custom tables in one JSON file, and generate the BerlinDB
classes, the CRUD functions and the table versions from it.

## What it does

A custom table on BerlinDB is five classes that have to agree with each other
about every column -- a schema, a table, a query, a row and the registration
that ties them together -- plus the functions the rest of the plugin calls.
Twenty tables is over a hundred files, and hand-written they drift.

This is a development tool. You write `bin/schema.json`, run
`vendor/bin/schema generate`, and commit what it writes. It also gives every
table a version that rises when, and only when, its columns or indexes
change, so BerlinDB upgrades existing sites without a migration routine.

The generated classes extend the runtime in
[sugarcommerce/data](https://github.com/sugarcommerce/data) by default; the
two are meant to be used together.

## Features

* Generate the Schema, Table, Query and Row classes for every table, and the
  class that registers them
* Generate `{prefix}_get_order()`, `_add_`, `_update_`, `_delete_`, `_count_`
  and the meta functions, each with a filter or a before/after action pair
* Generate the figures a report or an add-on needs without SQL:
  `{prefix}_get_order_counts( $args, 'status' )` (how many of each status, one
  `GROUP BY` query) and `{prefix}_get_order_stats( 'sum', 'amount_total', $args )`
  (`sum`, `avg`, `min`, `max` or `count`; `groupby` in the arguments for one
  figure a value, such as a sum per currency), over the rows `_get_` would match
* Give each column a save-time callback, worked out from its name and type
* Fill a new row's column from a function, such as the store's currency
* Raise a table's version only when its shape changes, so BerlinDB adds the
  missing columns on the next admin request
* Refuse to overwrite a hand-written migration or a hand-written function
* Fail CI when a generated file is out of date
* Report, and on a dev database fix, columns that have drifted from the
  schema and tables that have gone

## Installation

```bash
composer require --dev sugarcommerce/schema
```

## Quick start

`bin/schema.json`, one entry per table, keyed by its plural name:

```json
{
  "tickets": {
    "singular": "ticket",
    "alias": "t",
    "uuid": false,
    "meta": true,
    "functions_dir": "tickets",
    "why": "A question somebody asked, and everything known about it.",
    "columns": [
      { "name": "title", "type": "varchar", "length": 200, "default": "", "searchable": true },
      { "name": "status", "type": "varchar", "length": 20, "default": "open", "index": true },
      { "name": "customer_id", "type": "bigint", "length": 20, "unsigned": true, "default": "0", "index": true },
      { "name": "currency", "type": "char", "length": 3, "default": "", "default_function": "acme_get_currency" },
      { "name": "body", "type": "longtext" }
    ]
  }
}
```

`composer.json`:

```json
{
  "extra": {
    "schema": {
      "namespace": "Acme\\Database",
      "prefix": "acme",
      "kern": "Acme\\Vendor\\BerlinDB\\Database\\Kern",
      "bases": { "query": "Acme\\Database\\Query" }
    }
  },
  "scripts": {
    "schema:generate": "schema generate",
    "schema:check": "schema check"
  }
}
```

Then `composer schema:generate` writes `src/Database/{Schemas,Tables,Queries,Rows}/`,
`src/Database/Components/Core.php`, `functions/tickets/tickets.php` and
`bin/schema.lock.json`. Every table gets an `id`, `date_created` and
`date_modified`; `"uuid": true` adds a `uuid`, and `"meta": true` a meta table
with `acme_add_ticket_meta()`, `_get_`, `_update_`, `_delete_` and
`acme_delete_ticket_meta_by_key()`, which removes a key from every ticket
through `delete_metadata()`'s delete-all mode, so core clears the caches.

## Columns

Anything BerlinDB's `Column` takes is written through: `type`, `length`,
`scale`, `default`, `unsigned`, `allow_null`, `searchable`, `sortable`,
`date_query`, `compare`, `cache_key`. Beside those:

| Key | Does |
|---|---|
| `"index": true` / `"unique": true` | An index on the column alone |
| `"default_function": "fn"` | A new row that leaves the column empty gets `fn()`. A `default` is a literal in `CREATE TABLE`, so it cannot be "today's currency" |
| `"transition": true` | BerlinDB fires `{prefix}_transition_{item}_{column}` when it changes. A `status` column is one unless it says `false` |
| `"kind": "html"` | Name the kind instead of letting it be worked out |
| `"sanitize": "fn"` / `false` | Name the callback outright, or have none |
| `"unsigned": false` | A number that can be negative. BerlinDB makes every number unsigned unless told |

Table-level `"unique"` and `"indexes"` take lists of column lists:
`"indexes": [ [ "status", "date_modified" ] ]`. An index is named after its
columns joined with `_`; past MySQL's 64 characters (a covering index of many
columns) it keeps the first 55 and ends with eight characters of a hash of the
whole, so the name is the same on every run.

A column cannot take a name BerlinDB's query keeps for itself -- `number`,
`order`, `count`, `fields`, `offset` and the rest of `Generator::RESERVED`.
Every column is also a query argument, so a `number` column would turn the
generated functions' `'number' => 0` into `WHERE number = ''`. The run stops
and asks for a name like `order_number`.

## Kinds

BerlinDB calls a column's `validate` callback with the value on every insert
and every update that changes it. Rather than a callback per column, each
column has a *kind*, and each kind a function:

```json
"kinds": {
  "id": "acme_sanitize_id",
  "amount": "acme_sanitize_amount",
  "key": { "function": "acme_sanitize_key", "length": true },
  "text": { "function": "acme_sanitize_text", "length": true }
}
```

A kind with `"length": true` also needs the column's length, so the Schema
class gets a `shape_{column}()` method that passes it on. The kind is worked
out by `rules`, the first that matches deciding. The defaults: datetimes are
`datetime`; `tinyint(1)` is `flag`; integers named `*_id` are `id`,
`amount`/`total`/`amount_*`/`*_amount`/`*_fee`/`*_net` are `amount`, any other
integer is `count`; decimals named `*_rate` are `rate`; text types are
`textarea`; `currency`, `country`, `email` and `ip` by name; `status`, `type`,
`*_status` and `*_type` are `key`; anything else is `text`. A kind with no
function configured gets no callback. Replace the rules to fit a plugin; each
matches on `types`, an exact `length`, and any of `names`, `prefixes`,
`suffixes` or `patterns`:

```json
"rules": [
  { "kind": "stripe_id", "patterns": [ "/^(?=stripe_).*_id$/" ] },
  { "kind": "json", "types": [ "longtext" ], "names": [ "payload" ] },
  { "kind": "text" }
]
```

## Versions and upgrades

`bin/schema.lock.json` holds a fingerprint of each table's columns and
indexes and the version it was given. A table whose fingerprint moves gets
today's date and a counter (`202611170`, then `202611171`); one that has not
keeps its number. BerlinDB 3.1 sees the higher version on the next admin
request and adds the columns and indexes an existing table is missing. Keys
MySQL never hears of -- `kind`, `sanitize`, `default_function`, `transition`
-- do not move a fingerprint. Commit the lock with the schema. It only adds:
a removed column stays and a changed type is not applied; `reconcile`
reports both.

## Hand-written code

Generated files are rewritten on every run, so what a row can be asked
beyond its columns goes in a trait, `src/Database/Rows/Concerns/{Row}.php`,
which the generated row uses. Helpers go in their own file beside the
generated one: `functions/tickets/ticket-details.php`. The generator stops,
before writing anything, rather than overwrite a Table class with a
`__{version}()` upgrade routine in it, a functions file with a function it
does not generate, or an old copy of a table's functions in another folder
that has functions of its own.

## Checking and reconciling

```bash
vendor/bin/schema check                        # exit 1 when a generated file is missing or differs
vendor/bin/schema reconcile                    # report how the live tables differ
vendor/bin/schema reconcile --apply            # add missing columns, fix their order
vendor/bin/schema reconcile --apply --drop     # and drop columns and empty tables nothing declares
```

`reconcile` loads WordPress from the nearest `wp-load.php` above the plugin,
or `--wp=path`, and exits 1 when anything has drifted: a column missing,
extra or out of order, a table with no schema, or a declared table that is
not there (the plugin's installer makes that one). A column that still holds
values is never dropped without `--force`, and a table with rows never.

## An add-on that extends another plugin's tables

An add-on that registers tables on its parent plugin's registry builds on
the parent's classes rather than bundling its own copy. Point the bases at
them, and keep the parent's prefix:

```json
"schema": {
  "namespace": "AcmeReviews\\Database",
  "prefix": "acme",
  "function_prefix": "acme_reviews",
  "kern": "Acme\\Vendor\\BerlinDB\\Database\\Kern",
  "bases": { "query": "Acme\\Database\\Query" },
  "register_function": "acme_register_component",
  "register_action": "acme_register_components"
}
```

## Settings

From `extra.schema` in composer.json, then a `"$config"` object in the schema
file, then `--config=file.json`, each over the one before.

| Setting | Default | |
|---|---|---|
| `namespace` | required | Generated classes go in `{namespace}\Schemas`, `\Tables`, `\Queries`, `\Rows`, `\Components` |
| `prefix` | required | Table prefix, cache groups, meta type |
| `function_prefix`, `hook_prefix` | `prefix` | CRUD function names; their hooks |
| `kern` | `BerlinDB\Database\Kern` | Where Schema, Table and Row come from |
| `bases` | Kern's, and `SugarCommerce\Data\Query` | `schema`, `table`, `row`, `query`: the classes extended |
| `register_function`, `register_action` | `{prefix}_register_component(s)` | What the generated `Core` calls and hooks |
| `default_number` | `100` | PHP for how many rows `_get_{plural}()` returns unasked |
| `schema`, `lock` | `bin/schema.json`, beside it | |
| `paths` | `src/Database`, `functions` | `classes`, `functions`, `concerns` |
| `components` | `Core` | `class` and `summary` of the registering class |
| `header` | | `copyright`, `license`, `since` for each file's docblock |
| `initial_version` | `1` | A new table's version, and every meta table's |
| `command` | `vendor/bin/schema generate` | What each Schema class says to run instead of editing it |
| `kinds`, `rules`, `texts` | see above | |

The generated files are already in the WordPress coding standards' format,
so `phpcbf` leaves them alone.

## Security

Everything in the schema file is written into PHP strings, SQL and file
paths, so `generate` refuses, before writing anything:

* a table, singular, alias, column or index name that is not a lowercase
  identifier, which could end the PHP string it is written into;
* a type, length or scale that is not a plain word or number, and a
  `default_function` or `sanitize` that is not a function name;
* a `functions_dir` with `..` or anything outside letters, digits, `-`, `_`
  and `/`, which would write outside the plugin.

A default with a quote or a backslash is written as a PHP string that reads
back as given, and a `why` cannot close the doc comment it goes into.
`reconcile` quotes every table and column name the way `%i` does, so a live
column named with a backtick cannot add a clause to an `ALTER TABLE`.

## Layout

* `src/Cli.php`, `Config.php`, `Generator.php`: the command, its settings,
  and the run that renders, guards and writes.
* `src/Guard.php`: what stops a run from deleting hand-written code.
* `src/Reconciler.php`: live tables against the Schema classes.
* `src/Render/`: one class per kind of output (classes, columns, functions,
  kinds, versions, the header) and the template reader.
* `src/Support/`: the JSON writer and the value helpers.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later (for `reconcile`, and for the generated code)

## License

GPL-2.0-or-later
