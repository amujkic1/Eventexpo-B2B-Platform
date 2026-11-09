# EventExpo B2B Core

WordPress plugin for company profiles, events, and event registrations.

## Folder structure

```text
eventexpo-b2b-core.php       Plugin metadata and module loading
includes/                  PHP handlers, hooks, and business logic
  companies/               Company records, profiles, and directory
  events/                  Event records, public pages, and management
  registrations/           Account registration and event registrations
  dashboard/               Dashboard handler
  shared/                  Rendering, navigation, and asset helpers
templates/                 Views, grouped by the same features
  companies/
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

See [DEPLOYMENT.md](DEPLOYMENT.md) for deployment setup and
[assets/README.md](assets/README.md) for asset details.
