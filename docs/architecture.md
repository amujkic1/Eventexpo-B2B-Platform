# EventExpo architecture

EventExpo is a procedural, feature-based modular monolith. The bootstrap loads
modules explicitly, and WordPress supplies persistence, authentication, request
lifecycle and administration. No autoloader, service container, generic repository
or new dependency is required.

## Module map

```text
eventexpo-b2b-core.php
includes/
  auth/organizer-auth.php
  companies/
    companies.php
    company-service.php
    company-profile.php
    companies-directory.php
    company-public-profile.php
  events/
    events.php
    event-admin.php
    event-service.php
    manage-event.php
    event-public-profile.php
  registrations/
    event-registrations.php
    event-registration-fields.php
    registration-requests.php
    email-identity.php
    email-migration.php
    registration-tools.php
    notifications.php
    registration.php              Compatibility loader
  dashboard/dashboard.php
  shared/presentation.php
templates/
  auth/organizer-signup.php
  admin/email-review.php
  companies/, events/, registrations/, dashboard/, shared/
assets/
tests/
```

| Component | Responsibility |
| --- | --- |
| Bootstrap | Explicit includes, preserving hook registration order. |
| Auth | UM validation, existing one-time signup settings, login redirects, and the UM company-creation hook. |
| Company service | Company creation/link recovery, profile persistence, native media upload and company reads. |
| Company handlers | Session/ownership/nonce checks, request parsing, directory queries and view preparation. |
| Event registration | Native CPT and taxonomy definitions in `events.php`. |
| Event administration | Existing meta boxes and `save_post_event` handling. |
| Event service | Event queries, company permissions, draft/submission operations and frontend detail persistence. |
| Event workspace | Existing HTTP behavior, request validation and view preparation. |
| Registration operations | Native registration CPT/admin details, queries, status changes and save orchestration. |
| Registration fields | Stable schema identities, field mutation, snapshots, archived fields and answer presentation data. |
| Registration requests | Public submission, builder POSTs, status POSTs and public form view preparation. |
| Email identity | Normalization, identity mapping, storage checks and the atomic registration writer. |
| Email migration | Existing legacy inference, migration and safe result recording. |
| Registration tools | Existing administrator/CLI entry points; prepares data for `templates/admin/email-review.php`. |
| Notifications | Existing best-effort `wp_mail()` delivery after successful persistence. |
| Shared presentation | Template rendering, assets, navigation, country suggestions and general display helpers. |

Services receive values from handlers rather than reading `$_POST`. The company
service uses WordPress's `media_handle_upload()` with the existing field name;
the handler decides whether a file was submitted. The WordPress upload API still
owns its transport details. Simple operations use WordPress APIs directly; an
additional repository wrapper would add no useful boundary here.

The event workspace delegates registration status requests to the registrations
module. Its existing `b2b_*` entry points remain available. The dashboard remains
a small composition of company/event reads.

## Compatibility contract

- Preserve existing function signatures, shortcode names, callback names,
  priorities, accepted argument counts and ordering at equal priorities.
- Preserve `company`, `event`, `event_registration`, `industry`, `event_type`,
  native REST exposure, capabilities, metadata keys and table definitions.
- Preserve the current company-author and event-company ownership checks.
- Preserve draft/pending/publish behavior, the pending edit lock, and ordinary
  published edits without reapproval.
- Preserve each handler's nonce checks, validation, response messages and redirect
  behavior. Company/event detail POSTs still render their existing responses;
  lifecycle, builder and successful attendee submissions keep their 303 redirects.
- Preserve frontend versus WordPress Admin validation differences. Unifying these
  policies would be a behavior change and requires separate work.
- Preserve the existing one-time signup marker and migration version options.
  Moving code does not rerun setup or migrate data.
- Preserve the unique `(event_id, email_hash)` key, InnoDB checks, transactions,
  rollback, advisory locks, cache handling and temporary reconnect suppression.
- Mail remains outside the transaction and write lock. Failed mail does not undo
  a saved registration/status. There are no queues or automatic retries.
- `b2b_get_event_registration_fields()` may initialize its stable email mapping;
  it is not a purely read-only getter.

`includes/registrations/registration.php` remains a small compatibility loader for
the former account-registration path. The old signup template path delegates to
`templates/auth/organizer-signup.php`. These contain no duplicated implementation.
The existing `single_template` fallbacks remain alongside the later app document
selection; removing them requires checking external theme/filter integrations.

## Verification

See the README for sequential local test commands. The retained tests cover
storage recovery, legacy migration, eight simultaneous anonymous submissions,
signup configuration, company ownership, lifecycle permissions, status/mail
behavior, real HTTP detail saves and validation failures, logo uploads, native
REST publication denial and cross-company access denial.

The local test environment uses seeded `localadmin`/`organizer` accounts and the
ignored Docker/helper files. It is not yet a self-contained CI environment.
Notification checks intercept WordPress mail; they do not establish real email
delivery. Production PHP/WordPress versions and third-party integrations need
their own acceptance checks. The existing country autocomplete popup still needs
manual browser verification; this refactor does not change it.

For this refactor, all five README suites passed locally (90 reported checks).
The original storage, parallel HTTP and signup-setup tests were run before and
after the changes; the workflow checks also passed before and after moving code.
The final HTTP workspace/registration checks passed again after extracting the
remaining template request context. A baseline comparison preserved all 70
existing function signatures, hook order/priorities/argument counts, CPT and
taxonomy capabilities, and version markers. Executable-token hashes matched for
15 critical functions covering transactions, locks, migration, permissions,
status changes, notifications and registration handlers. The extracted migration
admin view matched the original HTML in normal, success and failure states.

## Deployment and remaining work

This refactor changes files and includes only; it introduces no schema/data
migration or production configuration step. Keep the plugin entry filename.

The existing deployment uploads `assets`, `includes` and `templates` recursively,
then the bootstrap. It does not delete remote files and is not atomic: changed
existing includes can be observed together with older files during upload. Use a
controlled maintenance window or an atomic release process for the eventual
production rollout. Do not push to `main` just to preview: that branch triggers
the existing production workflow.

Remaining debt is intentionally scoped: request error state still uses the
existing request-local globals; native admin/frontend validation differences
remain; legacy template fallbacks remain; local setup is not tracked; and company
directory reads still use the existing unpaginated query. These are separate
decisions, not prerequisites for the current module boundaries. No Meetings
module is added because no meeting implementation exists in this repository.
