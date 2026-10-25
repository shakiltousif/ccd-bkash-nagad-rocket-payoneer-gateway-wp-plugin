# Releasing

WordPress.org SVN is the distribution channel; this GitHub repository is the source.

## Scheduled release (current: 1.5.0 on 2026-11-01, 10:00 Bangladesh time)

`.github/workflows/scheduled-release.yml` (on `main`) deploys the `release/1.5.0` branch to
WordPress.org with `10up/action-wordpress-plugin-deploy`:

1. Skips unless today (UTC) is `RELEASE_DATE`, and skips if `tags/1.5.0` already exists on WordPress.org.
2. Pre-flight: plugin header version, `CCD_PAYMENT_GATEWAY_VERSION`, readme `Stable tag` and changelog
   all equal `RELEASE_VERSION`; every PHP file passes `php -l`.
3. Syncs the branch to SVN `trunk` (files listed in `.distignore` are left out), copies it to
   `tags/1.5.0`, commits.
4. Confirms the tag exists on WordPress.org and pushes git tag `v1.5.0`.

### One-time setup

Repository secrets (Settings -> Secrets and variables -> Actions):

- `SVN_USERNAME` = `devshakil`
- `SVN_PASSWORD` = WordPress.org **SVN password** (Profile -> Account & Security -> SVN credentials ->
  Generate Password). This is not the account login password.

With GitHub CLI:

```sh
gh secret set SVN_USERNAME -R shakiltousif/ccd-bkash-nagad-rocket-payoneer-gateway-wp-plugin --body devshakil
gh secret set SVN_PASSWORD -R shakiltousif/ccd-bkash-nagad-rocket-payoneer-gateway-wp-plugin
```

### Test, cancel, move

- **Dry run now:** Actions -> "Scheduled WordPress.org release" -> Run workflow (dry run is ticked by
  default). It builds the SVN commit without sending it.
- **Publish early:** Run workflow with dry run unticked.
- **Cancel:** Actions -> the workflow -> "..." -> Disable workflow (or delete the file).
- **Move the date:** change `RELEASE_DATE` and the `cron` line (both UTC).

## Next releases

1. Branch `release/X.Y.Z` from `main`, bump `Version:`, `CCD_PAYMENT_GATEWAY_VERSION`, readme
   `Stable tag`, add changelog and upgrade notice.
2. Update `RELEASE_VERSION`, `RELEASE_BRANCH`, `RELEASE_DATE` and `cron` in the workflow on `main`.
3. After the release, merge `release/X.Y.Z` into `main`.
