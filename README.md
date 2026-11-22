# EventExpo B2B Core

WordPress plugin for company profiles, events, and event registrations.

## Folder structure

```text
eventexpo-b2b-core.php       Plugin metadata and module loading
includes/                  PHP handlers, hooks, and business logic
  auth/                    Ultimate Member signup integration and login redirects
  companies/               Company operations, profiles, and directory
  events/                  Event operations, permissions, administration, and pages
  registrations/           Attendee forms, storage, statuses, migration, and mail
  dashboard/               Dashboard handler
  shared/                  Rendering, navigation, and asset helpers
templates/                 Views, grouped by the same features
  companies/
  auth/
  events/
  registrations/
  dashboard/
  admin/                   WordPress administration views
  shared/                  Application shell and common messages
assets/
  css/                     Stylesheets
  js/                      Browser scripts
  fonts/                   Bundled fonts and license
  icons/                   SVG sprite and license
.github/                   Deployment workflow and upload script
```

Load new PHP modules explicitly in `eventexpo-b2b-core.php`. Render views with
their feature path, for example `b2b_render_template( 'companies/company-profile', $context )`.
Asset paths passed to `b2b_asset_url()` are relative to `assets/`.

HTTP handlers parse requests and authorize actions, while feature service
functions perform the existing operations using WordPress APIs. Registration
transactions and migration remain separate from HTTP and email delivery. See
[docs/architecture.md](docs/architecture.md) for the module map and compatibility
constraints. Existing `b2b_*` functions, hooks, shortcodes and metadata remain
available.

## Company registration

With Ultimate Member active and its registration page assigned, the first
administrator visit to WordPress Admin applies a one-time signup update. It
renames the existing page to `/register-company/`, keeps its page and form IDs,
and configures the native name, username, email, password confirmation and company
fields. New accounts use the Subscriber role. Later administrator changes to the
form are preserved; the update does not run again after completion.

The registration page uses the EventExpo form layout. Ultimate Member continues
to validate and save accounts, and the existing registration hook creates the
company. If another page already occupies the target slug, an admin notice is
shown and that page is left untouched. Uploading code alone does not apply this
database configuration until an administrator opens WordPress Admin.

With the local site running and Ultimate Member configured, check the setup with
`./local/wp.sh eval-file - < tests/company-registration-setup.php`. This local-only
check leaves the registration page configured at `/register-company/`.

## Local regression checks

With the existing local WordPress environment and Ultimate Member configured:

```sh
./local/wp.sh eval-file - < tests/registration-email.php
./local/wp.sh eval-file - < tests/company-registration-setup.php
./local/wp.sh eval-file - < tests/mvp-workflows.php
DOCKER_CONTEXT=desktop-linux python3 tests/event-management-http.py
DOCKER_CONTEXT=desktop-linux python3 tests/registration-email-http.py
```

Run these sequentially: the storage test temporarily changes migration state.
The workflow test intercepts mail; the local environment also suppresses outgoing
mail. HTTP tests create and remove disposable users, companies, events,
registrations, email claims and uploaded logos.

See [DEPLOYMENT.md](DEPLOYMENT.md) for deployment setup and
[assets/README.md](assets/README.md) for asset details.
