=== CodeCareBD - Payment Gateway for WooCommerce ===
Contributors: devshakil, codecarebd, shakvaro
Requires at least: 6.3
Requires PHP: 7.3
Tested up to: 7.1
Stable tag: 1.5.0
WC requires at least: 6.3
WC tested up to: 11.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: bkash, nagad, rocket, upay, bangla qr

bKash, Nagad, Rocket, Upay, CellFin, Bangla QR, bank transfer and Payoneer manual payments for WooCommerce. Block checkout and HPOS ready.

== Description ==

**CodeCareBD - Payment Gateway for WooCommerce** is a free, lightweight plugin that adds **bKash**, **Nagad**, **Rocket**, **Upay**, **CellFin (Islami Bank)**, **Bangla QR**, **bank transfer (BEFTN / NPSB)** and **Payoneer** as manual payment methods to your WooCommerce store. It is built for Bangladeshi e-commerce and F-commerce sellers who want to accept mobile financial service (MFS) payments without expensive merchant API onboarding.

When a customer places an order, they send money to your bKash, Nagad or Rocket number (Agent or Personal), then confirm the payment by entering their **mobile number and Transaction ID (TrxID)** right on the checkout page. You verify the payment and process the order from your WooCommerce dashboard. Payoneer is supported the same way using the sender email and transaction ID.

This makes it the perfect **bKash WooCommerce payment gateway** (and Nagad / Rocket / Payoneer gateway) for small and medium online shops, Facebook-commerce stores, and any merchant who collects payments manually.

= Why choose this plugin? =

* **100% free** - no monthly fees, no per-transaction charges from the plugin.
* **Eight payment methods in one plugin** - bKash, Nagad, Rocket, Upay, CellFin, Bangla QR, bank transfer and Payoneer.
* **Block Checkout ready** - works natively with the new WooCommerce Cart & Checkout Blocks (the default checkout for new stores), as well as the classic shortcode checkout.
* **HPOS compatible** - fully supports WooCommerce High-Performance Order Storage (Custom Order Tables).
* **No API or merchant account required** - use your existing personal or agent number.
* **Lightweight and fast** - no bloat, minimal impact on checkout speed.

= Key Features =

* Accept payments via **bKash, Nagad, Rocket, Upay, CellFin, Bangla QR, bank transfer and Payoneer**.
* **Bangla QR** - show your Bangla QR code; customers pay from any bank or mobile banking app.
* **Bank transfer with multiple accounts** - add as many bank accounts as you need; customers search and pick the account they paid into.
* Choose **Agent**, **Personal** or **Merchant** account type for each wallet.
* **Smart number input** - accepts Bangla digits, +880 numbers, spaces and dashes, and 12-digit Rocket account numbers.
* Optional **payment screenshot / PDF upload** (off, optional or required per gateway). Files are stored privately; only shop managers can view them.
* Optional **prepayment discount** per gateway to encourage prepaid orders over cash on delivery.
* **Minimum / maximum order amount** per gateway, e.g. to respect your wallet's send money limit.
* Show your receiving number/email and custom instructions on the checkout page, with a one-click **Copy** button.
* Show your **payment QR code** (bKash, Nagad, Rocket or Bangla QR) at checkout so customers can scan to pay.
* Collect the customer's **sender number and Transaction ID** for easy verification.
* **Duplicate Transaction ID protection** - a Transaction ID that was already used for another order is rejected at checkout, stopping fake or reused payment claims.
* Optional **send money charge** per gateway, as a percentage (e.g. bKash 1.85%) or a fixed amount, added automatically to the order total and updated live on both classic and block checkout.
* **Advance / partial payment** - let customers pay the delivery charge, or a fixed amount for inside / outside Dhaka, in advance, with a custom "Advance Received" order status and an optional minimum order total (great for reducing fake cash-on-delivery orders).
* **Settings checks** - wrong numbers, routing numbers, QR links or limits are caught when you save, so customers never see a broken payment method.
* Transaction ID and account number shown on the **admin order screen** and order list columns.
* Custom **thank-you page message** and order email instructions per gateway.
* Number and Transaction ID validation on both classic and block checkout.
* Translation ready.

= Perfect for =

* Bangladeshi WooCommerce and WordPress stores.
* F-commerce (Facebook commerce) sellers.
* Small and medium businesses accepting bKash, Nagad or Rocket.
* Stores that want to collect an advance delivery charge to cut down on fake orders.

= How it works =

1. The customer selects bKash, Nagad, Rocket or Payoneer at checkout.
2. They send money to your displayed number/email.
3. They enter their sending number and Transaction ID and place the order.
4. You confirm the payment and update the order status from your dashboard.

== How to use ==

[youtube https://www.youtube.com/watch?v=yrK5dhQpX68]

== Please note: ==

- This is a WooCommerce plugin, requiring WooCommerce activation.
- You need accounts with bKash, Nagad, Rocket, or Payoneer to receive payments.

For suggestions and support, contact us [here](https://codecarebd.com/contact).

== Frequently Asked Questions ==

= Is this plugin free? =

Yes. CodeCareBD - Payment Gateway for WooCommerce is completely free. The plugin never charges any fee. Standard bKash/Nagad/Rocket send money or cash out charges from the providers still apply to you as the account holder.

= Do I need a bKash/Nagad merchant account or payment API? =

No. This is a manual payment gateway. You can use your existing personal or agent bKash, Nagad or Rocket number. Customers send money and submit the Transaction ID, which you verify manually. No API keys or merchant onboarding required.

= Does it work with the new WooCommerce block checkout? =

Yes. Version 1.1 adds full support for the WooCommerce Cart & Checkout Blocks, which is the default checkout for new stores. It also keeps working on the classic shortcode checkout.

= Is it compatible with HPOS (High-Performance Order Storage)? =

Yes. The plugin declares HPOS compatibility and stores all order data using the WooCommerce order CRUD API, so transaction details display correctly whether HPOS or the legacy storage is enabled.

= Can customers pay only the delivery charge in advance? =

Yes. Enable the Advance Payment option and customers can pay just the shipping/delivery charge up front. The order is marked "Advance Received" and the remaining amount is shown to you and the customer.

= What happens if a customer enters a Transaction ID that was already used? =

The order is not placed and the customer sees a message asking them to check the Transaction ID. Failed and cancelled orders are ignored, so a customer can retry after a failed attempt. Developers can turn the check off with the `ccd_block_duplicate_transaction_id` filter (return `false`).

= How do I add my QR code? =

Go to WooCommerce > Settings > Payments, open bKash, Nagad or Rocket, and use the "Select image" button next to "QR Code Image". The QR code appears on the checkout page under your number.

= Can I add more than one bank account? =

Yes. Under Bank Transfer, use "+ Add account" to add as many accounts as you need. When there is more than one, customers get a searchable list and choose the account they paid into, and the order shows which account it was.

= Where are payment screenshots stored? Can anyone see them? =

In a private folder inside your uploads directory, with random file names and access blocked. They never appear in the Media Library. Only users who can manage orders can open them, from the order screen. Uploads that never become an order are deleted after 24 hours. The maximum size is 5 MB, or your server's upload limit if that is lower.

= What is Bangla QR? =

Bangladesh Bank's national interoperable QR code. One QR code can be paid from any participating bank or mobile financial service app. Enable "Bangla QR", add your merchant name and upload your Bangla QR image.

= Which currencies are supported? =

The plugin works with your WooCommerce currency. It is designed primarily for BDT (Bangladeshi Taka) but does not restrict the store currency.

= Is WooCommerce required? =

Yes. WooCommerce must be installed and active for this plugin to work.

== Privacy & Data Sharing ==

This plugin can send usage and diagnostic data to the developer to help fix bugs and prioritise features. **This is disabled by default.** Nothing is collected or sent unless you explicitly opt in via the admin notice shown after activation. Declining (or ignoring) the notice leaves the plugin fully functional.

There are two separate, optional opt-ins:

1. Usage & diagnostics. When enabled, we collect: WordPress, PHP, MySQL and WooCommerce versions; your active theme, locale and whether the site is multisite; the plugin version and activation/deactivation/update events; which of the plugin's features are enabled; and a one-way (SHA-256) hash of your site URL. The raw site URL is never sent.

2. Product update emails (separate checkbox). Only if you tick this, we additionally collect your site administrator email to send occasional product updates.

You can change or withdraw your choice at any time from **Settings → Data Sharing** in your WordPress admin. Turning data sharing off stops all collection and requests deletion of previously collected data. Uninstalling does the same.

Data is sent over HTTPS to https://track.shakvaro.cloud and is handled as described in our privacy policy: https://shakvaro.com/wp-insights/privacy

== Screenshots ==

1. Payment Settings (bKash).
2. Payment Settings (Rocket).
3. Payment Settings (Nagad).
4. Payment Settings (Payoneer).
5. Checkout Page.

== Changelog ==

= 1.5.0 - 2026-11-01 =
* NEW: Upay, CellFin (Islami Bank), Bangla QR and Bank Transfer (BEFTN / NPSB / RTGS) payment methods.
* NEW: Bank Transfer supports any number of bank accounts, with a searchable "account you paid into" picker at checkout.
* NEW: Optional payment screenshot / PDF upload per gateway (off, optional or required), stored privately and viewable only by shop managers.
* NEW: Prepayment discount per gateway (percentage or fixed).
* NEW: Minimum / maximum order amount per gateway.
* NEW: Advance payment can be a fixed amount for inside / outside Dhaka (choose the districts) and can require a minimum order total.
* NEW: "Merchant" account type for bKash, Nagad, Rocket and Upay.
* NEW: Settings are checked on save: invalid receiving numbers, routing numbers, QR links, percentages over 100% and min/max conflicts are reported and not saved.
* IMPROVED: Customer numbers in Bangla digits, with +880, spaces or dashes are accepted and saved in a standard format.
* FIXED: Rocket now accepts the 12-digit Rocket account number (mobile number + check digit) as well as the 11-digit mobile number.
* FIXED: The custom thank you page message now shows for bKash, Nagad and Rocket orders (only Payoneer's was shown before).
* FIXED: On block checkout, the advance payment amounts now update when the address, shipping or payment method changes.
* FIXED: Orders no longer stay "Pending payment" when a gateway's order status setting is empty.

= 1.4.0 - 2026-10-25 =
* NEW: Duplicate Transaction ID protection. A Transaction ID already used on another order is rejected at checkout (classic and block checkout). Failed and cancelled orders are ignored.
* NEW: One-click "Copy" button next to your receiving number/email at checkout.
* NEW: QR code image per gateway (bKash, Nagad, Rocket), shown at checkout. Pick it with the Media Library button in the gateway settings.
* NEW: Configurable send money charge per gateway: percentage or fixed amount. Existing settings keep their previous rate (bKash 1.85%, Nagad 1.45%, Rocket 1.8%).
* FIXED: On block checkout, the send money charge now updates as soon as the customer selects a payment method.
* FIXED: The Rocket charge note was shown twice on classic checkout.
* NEW: A one-time, dismissible request to rate the plugin after your store receives 10 orders through it.

= 1.3.3 - 2026-10-25 =
* FIXED: Nagad and Payoneer payment instructions are now included in customer order emails (previously missing, and a PHP warning was logged).
* FIXED: Account No. and Transaction ID columns now appear in the admin Orders list when WooCommerce uses legacy (posts) order storage.
* IMPROVED: Replaced the deprecated WC_Order::reduce_order_stock() call with wc_reduce_stock_levels().
* IMPROVED: Tested with WordPress 7.1 and WooCommerce 11.2.

= 1.3.2 - 2026-10-25 =
* FIXED: PHP warning "Undefined array key order_actions" on the My Account > Orders page. The plugin now uses WooCommerce's correct "order-actions" column key, so the Actions column stays in place.
* FIXED: Account No. and Transaction ID columns on the My Account > Orders page now show their values instead of empty cells.

= 1.3.1 - 2026-06-20 =
* FIXED: Deleting then reinstalling the plugin no longer creates a duplicate record in usage analytics — the install identity is now derived from the site, so a reinstall reuses the same record. (Shakvaro WP Insights SDK 1.2.7.)
* FIXED: Plugin deletion no longer causes a critical error on sites that have another Shakvaro plugin installed (class redeclaration in uninstall.php).

= 1.3 - 2026-06-20 =
* FIXED: Activation event now correctly reported to Shakvaro WP Insights on plugin (re)activation.

= 1.2 - 2026-06-16 =
* NEW: Optional, privacy-first usage analytics (Shakvaro WP Insights) to help us fix bugs and prioritize features. Disabled by default; nothing is collected unless you opt in. See the "Privacy & Data Sharing" section and manage it any time under Settings -> Data Sharing.
* NEW: Deletion/erasure request sent and local data cleaned on uninstall.

= 1.1 - 2026-06-12 =
* NEW: WooCommerce Cart & Checkout Blocks support - all four gateways (bKash, Nagad, Rocket, Payoneer) now appear and work on the block-based checkout (default for new WooCommerce stores).
* NEW: Declared High-Performance Order Storage (HPOS) compatibility.
* FIXED: Order number/Transaction ID are now stored and read via WooCommerce order CRUD, so they display correctly under HPOS.
* IMPROVED: Shared, single-source validation for both classic and block checkout.
* IMPROVED: Added Requires Plugins / WC requires/tested headers; refreshed "Tested up to" for the latest WordPress and WooCommerce.
* IMPROVED: Checkout assets are versioned by plugin version instead of a per-request timestamp.

= 1.0 - 2026-02-03 =
* SECURITY: Fixed $_POST array access without isset() checks
* SECURITY: Fixed array offset warnings when payment gateway settings don't exist
* SECURITY: Added proper sanitization before validation in all payment methods
* SECURITY: Fixed variable naming issues (Payoneer email was incorrectly named)
* IMPROVED: Better input validation with defense in depth (isset + empty checks)
* IMPROVED: Code quality and WordPress coding standards compliance
* FIXED: PHP notices and warnings on checkout page

= 0.9 - 2025-11-28 =
* NEW: Added optional Advance Payment feature
* NEW: Custom order status "Advance Received" for advance payments
* NEW: Customers can pay only delivery/shipping charge as advance payment
* NEW: Settings page for advance payment configuration
* NEW: Customizable advance payment text messages
* NEW: Admin order details show advance payment information
* IMPROVED: Better payment amount calculations
* IMPROVED: Enhanced admin order display with advance payment breakdown
* FIXED: Remaining payment amount calculation accuracy
* Updated tested up to WordPress 6.7.4

= 0.8 - 2024-12-25 =
* Validation Error Fix.

= 0.5 - 2024-02-16 =
* Fixed issues on Single Order Page.

= 0.4 - 2024-02-13 =
* Updated for new WooCommerce version.
* Fixed validation issues.

= 0.3 - 2023-09-01 =
* Bug Fixes.

= 0.2 - 2023-08-24 =
* Small Fixes.

= 0.1 - 2022-09-21 =
* First release.

== Upgrade Notice ==

= 1.5.0 =
Adds Upay, CellFin, Bangla QR and multi-account bank transfer, payment screenshot upload, discounts, order limits and Dhaka zone advance payments. Fixes Rocket 12-digit account numbers and thank you messages. Existing settings are kept.

= 1.4.0 =
Adds duplicate Transaction ID protection, a Copy button and QR code at checkout, and configurable percentage or fixed send money charges. Existing charge settings are kept.

= 1.3.3 =
Fixes missing Nagad/Payoneer instructions in customer emails and missing admin order columns on legacy order storage. Tested with WordPress 7.1 and WooCommerce 11.2.

= 1.3.2 =
Fixes a PHP warning on the My Account > Orders page and shows Account No. / Transaction ID values there.

= 1.3 =
Fixes activation event tracking in Shakvaro WP Insights so (re)activations are correctly reported.

= 1.2 =
Adds optional, opt-in usage analytics (off by default) to help improve the plugin, with full data-sharing controls and erasure on uninstall.

= 1.1 =
Adds WooCommerce block checkout support and HPOS compatibility, and fixes order meta storage under HPOS. Recommended for all users on modern WooCommerce.
