# CodeCareBD - Payment Gateway for WooCommerce

Manual payment gateways for Bangladeshi WooCommerce stores: **bKash, Nagad, Rocket, Upay, CellFin,
Bangla QR, bank transfer (BEFTN / NPSB) and Payoneer**. Block checkout and HPOS ready.

- WordPress.org: https://wordpress.org/plugins/codecarebd-bkash-nagad-rocket-payoneer-gateway/
- Requires WordPress 6.3+, WooCommerce 6.3+, PHP 7.3+. Tested up to WordPress 7.1 / WooCommerce 11.2.

The user-facing description, FAQ and changelog are in [`readme.txt`](readme.txt).
Release process: [`RELEASING.md`](RELEASING.md).

## Layout

| Path | Purpose |
|---|---|
| `ccd-payment-gateway.php` | Bootstrap, checkout field save/validation, admin order display, advance payment |
| `includes/ccd-features.php` | Gateway config (single source of truth), validation, charges/discounts, min/max, settings checks, account display, review notice |
| `includes/ccd-payment-proof.php` | Private payment screenshot upload, attach, admin viewer, cleanup |
| `includes/classes/CCD_Payment_*.php` | bKash, Nagad, Rocket, Payoneer gateways (settings keys kept from earlier versions) |
| `includes/classes/class-ccd-gateway-base.php` | Base class + Upay, CellFin, Bank Transfer, Bangla QR |
| `includes/blocks/` + `assets/js/blocks/` | Cart & Checkout Blocks integration |
| `assets/js/ccd_scripts.js` | Classic checkout: copy button, uploads, bank account picker |
