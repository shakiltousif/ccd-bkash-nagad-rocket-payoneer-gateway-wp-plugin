# Releasing

WordPress.org SVN is the distribution channel; this GitHub repository is the source.

## Release queue

`.github/releases.json` (on `main`) lists scheduled releases:

| Version | Branch | Date (UTC) |
|---|---|---|
| 1.5.0 | `release/1.5.0` | 2026-11-01 |
| 1.6.0 | `release/1.6.0` | 2026-11-15 |
| 1.7.0 | `release/1.7.0` | 2026-12-01 |

`.github/workflows/scheduled-release.yml` (on `main`) runs every day at 04:00 UTC (10:00 Bangladesh time):

1. Picks the release whose date is today. Does nothing on other days.
2. Skips it if `tags/<version>` already exists on WordPress.org (never publishes twice).
3. Refuses to publish if an earlier version in the queue is not live yet (keeps the order).
4. Pre-flight on the release branch: plugin header version, `CCD_PAYMENT_GATEWAY_VERSION`, readme
   `Stable tag` and changelog all equal the version; every PHP file passes `php -l`.
5. Deploys with `10up/action-wordpress-plugin-deploy`: syncs the branch to SVN `trunk` (files in
   `.distignore` are left out), copies it to `tags/<version>`, commits. `.wordpress-org/` is synced to
   SVN `assets/` **with deletion**: anything not in that folder (banner, icon, screenshot) is removed
   from WordPress.org, so keep every asset in it.
6. Confirms the tag exists on WordPress.org and pushes git tag `v<version>`.

### Secrets

Repository secrets (Settings -> Secrets and variables -> Actions): `SVN_USERNAME` (`devshakil`) and
`SVN_PASSWORD` (WordPress.org **SVN password**: Profile -> Account & Security -> SVN credentials ->
Generate Password; not the login password). Generating a new SVN password invalidates the old one,
so update the secret (and any local SVN cache) whenever you do.

### Test, cancel, move

- **Dry run:** Actions -> "Scheduled WordPress.org release" -> Run workflow -> enter the version
  (dry run is ticked by default). Builds the SVN commit without sending it.
- **Publish early:** Run workflow with the version and dry run unticked.
- **Cancel one release:** remove its line from `releases.json`. **Cancel all:** disable the workflow.
- **Move a release:** change its date in `releases.json`.

## Preparing the next release

1. Branch `release/X.Y.Z` from the previous release branch; bump `Version:`,
   `CCD_PAYMENT_GATEWAY_VERSION`, readme `Stable tag`; add changelog and upgrade notice.
2. If a hotfix ships in between (e.g. 1.5.1), merge it into the next release branch.
3. Add `{ "version", "branch", "date" }` to `.github/releases.json` on `main`.
4. Translations: regenerate `languages/*.pot` with `wp i18n make-pot . languages/codecarebd-bkash-nagad-rocket-payoneer-gateway.pot --exclude=vendor,.wordpress-org,.github`, update the `.po`, then
   `wp i18n make-mo languages` and `wp i18n make-json languages --no-purge`.
5. After the release, merge the release branch into `main`.
