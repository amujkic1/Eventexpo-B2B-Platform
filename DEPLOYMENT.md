# Automatic deployment

Pushes to `main` upload the plugin to the existing hosting folder over explicit
FTPS (FTP with TLS, usually port 21). The hosting server must support FTPS and
present a trusted TLS certificate matching the hostname you supply.

## One-time setup

1. In cPanel, open **FTP Accounts → Configure FTP Client** for the deployment
   account. Find the FTP hostname and username. Set/reset the FTP password there
   if needed. Use the certificate-matching hostname rather than the server IP.
2. In the GitHub repository, open **Settings → Secrets and variables → Actions →
   New repository secret** and add:

   | Secret | Value |
   | --- | --- |
   | `FTP_HOST` | FTP server hostname from your hosting provider |
   | `FTP_USERNAME` | `b2bdev@eventexpo.ba` (confirm in cPanel) |
   | `FTP_PASSWORD` | Password for that FTP account |
   | `FTP_REMOTE_PATH` | Existing plugin directory as seen by that FTP account |
   | `FTP_PORT` | Optional; defaults to `21` |

   The editor configuration uses `/wp-content/plugins/eventexpo-b2b-core`.
   Confirm this path: an FTP account restricted directly to the plugin folder
   may need `/`, while a full hosting account may need
   `/public_html/wp-content/plugins/eventexpo-b2b-core`.
3. Back up the live plugin once, then commit and merge the workflow, script, and
   plugin changes into `main`.
4. Open the repository's **Actions → Deploy WordPress plugin** to inspect the run.
   You can also use **Run workflow** on `main` to retry a deployment.

The workflow checks PHP syntax before uploading the entry point and files under
`assets`, `includes`, and `templates`. It excludes local editor settings, Git
metadata, credentials, and development dependencies in `vendor`. Commit any new
plugin files (including `assets`) so GitHub can deploy them.

Uploads replace matching files but do not delete remote files. A failed upload
can leave a partially updated plugin; fix the error and rerun the workflow.
Renamed or removed files must be cleaned up separately. This workflow updates
plugin code only; it does not migrate the WordPress database.
