# Registration email identity and duplicate prevention

## Behavior

Each event has one attendee email identity, recorded in event metadata
`_b2b_attendee_email_field`. The first existing email-type field is adopted using
its stable `name`, never its label. If none exists, the effective form gets a
required system field (`ee_attendee_email`, with a suffix if that name is already
used). The chosen identity remains stable across label changes. Its label can be
edited, but its type stays email, it cannot become optional, and it cannot be
deleted. Additional custom email fields remain ordinary editable fields.

New registrations store normalized identity in `_b2b_attendee_email`. Normalization
trims surrounding whitespace, lowercases the address, and validates it with
WordPress `is_email()`. Dots and plus suffixes are preserved. Submitted answers are
stored separately without identity normalization replacing their original values.
Pending, approved and rejected registrations all reserve the address for that
event. Anonymous and authenticated submissions use the same rules.

There is no public email-lookup endpoint. The required duplicate message still
reveals that an address is registered when someone submits a valid form; this is
a deliberate MVP privacy tradeoff. Do not add emails to URLs, analytics or logs.
The email hash in the index is deterministic, not anonymization.

## Atomicity and recovery

`{prefix}b2b_registration_emails` has a primary key on `(event_id, email_hash)`.
`email_hash` is a SHA-256 digest of the normalized address. Duplicate detection
uses this database constraint, not a serialized-answer search or postmeta
uniqueness flag. A transaction covers the claim, registration post, answers,
status, field snapshot, normalized-email metadata and final registration ID.

A failed post insert or metadata write rolls back both the post and claim. A
connection/process death rolls back an uncommitted InnoDB transaction. WordPress
connection retry is temporarily disabled during the transaction, preventing a
lost query from being silently replayed on a fresh autocommit connection. If the
commit succeeds but its acknowledgement is lost, a retry finds the committed
registration and reports a duplicate rather than inserting another.

A connection-scoped MySQL advisory lock serializes migration and registration
writes. It has a bounded acquisition wait, but no expiry/TTL: connection closure
releases it. It does not leave an expiring reservation requiring cleanup. For
this MVP, the lock serializes all registration writes within the site's table
prefix; high-volume deployments may need a maintenance gate and finer locking.
The unique primary key is an additional database-level enforcement mechanism.

### Hosting requirements

- MySQL/MariaDB with InnoDB and `GET_LOCK`/`RELEASE_LOCK` support.
- `posts`, `postmeta`, and the identity index must all use InnoDB. The plugin checks
  this and refuses registration if the engines are unsuitable. It does not
  convert existing tables automatically.
- The database account needs schema creation/update permissions and access to its
  own table engine information in `information_schema`.
- Use the standard WordPress single primary database connection. Database routing
  drop-ins, replication routing and transaction-committing hooks require separate
  validation. Hooks must not issue DDL, `COMMIT`, or `ROLLBACK` inside this writer.
  External hook side effects (mail, APIs, third-party caches) cannot be rolled
  back by a database transaction.
- Registration importers must use `b2b_save_event_registration()`. Direct writes
  to WordPress posts bypass the index and require maintenance and migration.

Reservations are intentionally retained after status changes, trashing or manual
record deletion. Releasing reservations/restoring eligibility is outside this
MVP; do not delete index entries as a shortcut. Historical duplicate groups must
be reviewed before any future release workflow is implemented.

## Production deployment and migration

**Do not deploy or migrate production automatically.** Back up the database and
plugin files, and schedule a registration maintenance window first.

1. Pause registration imports and other writers. Deploy all updated plugin files,
   including `includes/registrations/email-identity.php`.
2. Until migration completes, the new writer fails closed. Normal organizer
   access to existing records remains available.
3. Run from the WordPress directory:

   ```sh
   wp eventexpo registrations-migrate
   wp eventexpo registrations-audit
   ```

   Without WP-CLI, an administrator can open **Tools → Registration email review**
   and submit **Run or resume email migration**. Use WP-CLI on large sites to avoid
   web request timeouts.
4. Inspect the report and records needing review. Test a first registration,
   a duplicate, a different address, and an organizer status change.
5. Clear page/asset caches (including SpeedyCache). Exclude registration POST
   responses and authenticated pages from shared caching. Resume registration.

Schema version and migration completion are stored separately as
`b2b_email_schema_version` and `b2b_email_migration_version`. Schema v1 is installed
with WordPress `dbDelta`. Migration uses bounded read batches and per-record
transactions; reruns are safe. An interrupted migration leaves registration
closed until a successful rerun. It never deletes or merges historical records.

### Historical records

Migration reads legacy answers in PHP once and identifies candidate values by
stable keys and email field definitions from snapshots, active fields and
archived fields. It then builds the dedicated identity index. Runtime duplicate
checks never search serialized answers.

- One usable address: add normalized metadata and reserve it.
- Multiple possible addresses: reserve every candidate conservatively and flag
  `ambiguous_email` for manual review; do not guess the attendee's identity.
- No usable address: flag `missing_email`.
- Missing/invalid event association: flag `invalid_event`.
- Existing duplicates: retain every registration. The index references the first
  indexed record; later conflicting records receive `duplicate_email` review
  flags. The whole group continues to block new registrations.

Reports expose registration IDs and review reasons only, via administrator-only
WordPress Tools or WP-CLI. Original IDs, submitted answers, timestamps and
approval statuses are unchanged. Missing historical emails cannot be inferred;
those records cannot block an unknown identity. Legacy email fields deleted
before field archival/snapshots may also require manual review.

### Rollback

If migration fails, keep registration paused, fix permissions/engine issues and
rerun it. Partially completed batches are safe to revisit.

To roll back application code, pause public registration before restoring the
previous plugin version: the old writer has no duplicate protection. Retain the
new index and metadata; old code ignores them. Before enabling the new version
again after any old-version writes, rerun migration. Do not drop tables or remove
historical records as part of application rollback.

## Automated local tests

With the existing Docker WordPress environment running:

```sh
docker compose --env-file .env.local -f compose.local.yaml run --rm cli eventexpo registrations-migrate
docker compose --env-file .env.local -f compose.local.yaml run --rm -T cli eval-file - < tests/registration-email.php
python3 tests/registration-email-http.py
python3 local/smoke.py
```

The new tests create disposable events and remove their posts and claims on exit.
The PHP suite covers normalization, all registration statuses, identity tampering,
protected system fields, failed insert/metadata recovery, repeated migration,
legacy duplicates, missing/ambiguous emails and historical preservation. The HTTP
suite sends **eight parallel submissions from eight separate anonymous clients**,
asserts one post and one claim, checks seven duplicate responses, and tests retries,
authenticated submissions and field editing/deletion.

## Manual checks

1. Open an existing event's Registration Form tab: its adopted email should appear
   once and say **System · Required**. A form with no email gains a system email.
2. Rename that field. Confirm its type/required settings cannot change and Delete
   is unavailable. Confirm extra custom email fields remain editable/deletable.
3. As a guest, submit a valid address. Submit it again in a different letter case
   and with surrounding whitespace: the exact duplicate notice should appear and
   other entered values should remain in the form.
4. Change the original registration to approved, then rejected, and repeat. Both
   should still block. A different event or genuinely different address succeeds.
5. Check missing/invalid email and crafted requests cannot bypass validation.
6. Review old submissions, organizer counts, search and pagination. Migration
   must not change historical records. Inspect **Tools → Registration email review**.
7. Refresh the success URL: it remains a GET and creates no new registration.
