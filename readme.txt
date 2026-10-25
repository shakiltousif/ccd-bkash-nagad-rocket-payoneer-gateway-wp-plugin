=== CodeCareBD - Payment Gateway for WooCommerce ===
Contributors: devshakil, codecarebd, shakvaro
Requires at least: 6.3
Requires PHP: 7.3
Tested up to: 7.1
Stable tag: 1.6.0
WC requires at least: 6.3
WC tested up to: 11.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: bkash, nagad, rocket, upay, bangla qr

bKash, Nagad, Rocket, Upay, CellFin, Bangla QR, bank transfer and Payoneer manual payments for WooCommerce. Block checkout and HPOS ready.

== Description ==

**CodeCareBD - Payment Gateway for WooCommerce** is a free, lightweight plugin that adds **bKash**, **Nagad**, **Rocket**, **Upay**, **CellFin (Islami Bank)**, **Bangla QR**, **bank transfer (BEFTN / NPSB)** and **Payoneer** as manual payment methods to your WooCommerce store. It is built for Bangladeshi e-commerce and F-commerce sellers who want to accept mobile financial service (MFS) and bank payments without expensive merchant API onboarding.

The customer pays you from their own app (send money, cash out, Bangla QR scan or bank transfer), then confirms the payment on the checkout page with their **number and Transaction ID (TrxID)**, and optionally a **screenshot of the payment**. You check the payment and process the order from your WooCommerce dashboard.

= Why choose this plugin? =

* **100% free** - no monthly fees, no per-transaction charges from the plugin.
* **Eight payment methods in one plugin** - bKash, Nagad, Rocket, Upay, CellFin, Bangla QR, bank transfer and Payoneer.
* **Block Checkout ready** - works natively with the WooCommerce Cart & Checkout Blocks (the default checkout for new stores) and the classic shortcode checkout.
* **HPOS compatible** - fully supports WooCommerce High-Performance Order Storage.
* **No API or merchant account required** - use your existing personal, agent or merchant number.
* **Stops fake payment claims** - a Transaction ID that was already used on another order is rejected at checkout.
* **Lightweight and fast** - no bloat, minimal impact on checkout speed.

= Payment methods =

* **bKash, Nagad, Rocket, Upay** - show your number (Personal, Agent or Merchant) with a one-click Copy button and your QR code. Add **as many numbers as you need**: customers search and pick the number they sent money to.
* **CellFin (Islami Bank)** - show your Islami Bank account or CellFin number (one or several).
* **Bangla QR** - show your Bangla QR code; customers pay from any bank or mobile banking app.
* **Bank transfer (BEFTN / NPSB / RTGS)** - add as many bank accounts as you need. With several accounts, customers search and pick the account they paid into, and see that account's number and routing number with Copy buttons.
* **Payoneer** - customers pay to your Payoneer email.

= Key features =

* **Duplicate Transaction ID protection** for every method (classic and block checkout).
* **Payment screenshot / PDF upload** - off, optional or required per method, at checkout or later from the thank-you page and My Account. Files are stored privately on your server; only shop managers can view them.
* **Send money charge** per method - percentage (e.g. bKash 1.85%) or fixed amount, updated live as the customer switches method.
* **Prepayment discount** per method - percentage or fixed, to encourage prepaid orders over cash on delivery.
* **Minimum / maximum order amount** per method, e.g. to respect your wallet's send money limit.
* **Advance / partial payment** - customers pay the delivery charge, or a fixed amount for inside / outside Dhaka (you choose the districts), with an optional minimum order total. Orders get an "Advance Received" status. Great for reducing fake cash-on-delivery orders.
* **Smart number input** - accepts Bangla digits (০১৭...), +880 numbers, spaces and dashes, and 12-digit Rocket account numbers.
* **Settings checks** - wrong receiving numbers, routing numbers, QR links, percentages or limits are caught when you save, so customers never see a broken payment method.
* Number, Transaction ID and payment details on the **admin order screen**, the orders list and the customer's **My Account > Orders**.
* Custom **thank-you page message** and order email instructions per method.
* **Bangla translation included** (বাংলা) for the checkout, emails and settings. Translation ready for other languages.

= বাংলায় =

বিকাশ (bKash), নগদ (Nagad), রকেট (Rocket), উপায় (Upay), সেলফিন (CellFin), বাংলা কিউআর (Bangla QR) ও ব্যাংক ট্রান্সফারে ম্যানুয়াল পেমেন্ট নিন। কাস্টমার টাকা পাঠিয়ে চেকআউটে নম্বর ও ট্রানজেকশন আইডি দেন, চাইলে পেমেন্টের স্ক্রিনশটও। একাধিক বিকাশ/নগদ নম্বর বা ব্যাংক অ্যাকাউন্ট, ঢাকার ভেতরে/বাইরে অগ্রিম ডেলিভারি চার্জ, ডুপ্লিকেট ট্রানজেকশন আইডি প্রতিরোধ এবং পূর্ণ বাংলা অনুবাদ রয়েছে।

= Perfect for =

* Bangladeshi WooCommerce stores and F-commerce (Facebook commerce) sellers.
* Shops that accept bKash, Nagad, Rocket, Upay, Bangla QR or bank payments without a payment gateway contract.
* Stores that want an advance delivery charge to cut down on fake orders.

= How it works =

1. The customer selects a payment method at checkout and sees your number, QR code or bank details.
2. They pay from their own app or bank.
3. They enter their number and Transaction ID (and, if you ask for it, upload a screenshot) and place the order.
4. The order waits as "On hold" (or the status you choose) while you check the payment, then you process it.

= Video guide =

[youtube https://www.youtube.com/watch?v=yrK5dhQpX68]

For suggestions and support, contact us [here](https://codecarebd.com/contact).

== Installation ==

1. Install and activate **WooCommerce**.
2. In WordPress, go to **Plugins > Add New**, search for "CodeCareBD", then install and activate. (Or upload the plugin folder to `/wp-content/plugins/` and activate it.)
3. Go to **WooCommerce > Settings > Payments** and open the methods you want to offer:
    * **bKash / Nagad / Rocket / Upay** - tick Enable, enter your number and account type. Optionally add your QR code with "Select image".
    * **CellFin** - enter your Islami Bank account number or CellFin number.
    * **Bank Transfer** - add one or more accounts (account number required; 9-digit routing number recommended).
    * **Bangla QR** - upload your Bangla QR image and enter your merchant name.
    * **Payoneer** - enter your Payoneer email.
4. Optional, per method: send money charge, prepayment discount, payment screenshot (off / optional / required), minimum and maximum order amount, and the order status after checkout.
5. Optional: **WooCommerce > Advance Payment** to ask for the delivery charge or a fixed inside / outside Dhaka amount in advance.
6. Place a test order to see the checkout as your customers will.

You need your own bKash, Nagad, Rocket, Upay, CellFin, bank or Payoneer account to receive payments.

== Frequently Asked Questions ==

= Is this plugin free? =

Yes, completely. The plugin never charges a fee. The normal send money or cash out charges of bKash, Nagad, Rocket, Upay or your bank still apply to you as the account holder; you can pass them on with the optional charge setting.

= Do I need a merchant account or payment API? =

No. This is a manual payment gateway. You can use your existing personal, agent or merchant number or bank account. Customers pay and submit the Transaction ID, which you verify. No API keys or merchant onboarding required.

= Does it work with the WooCommerce block checkout? =

Yes. It supports the WooCommerce Cart & Checkout Blocks (the default checkout for new stores) and the classic shortcode checkout.

= Is it compatible with HPOS (High-Performance Order Storage)? =

Yes. It declares HPOS compatibility and stores all order data with the WooCommerce order CRUD API, so details display correctly with HPOS or the legacy storage.

= Which number formats do customers have to use? =

Any common format works: 01712345678, +8801712345678, 01712-345678 or Bangla digits (০১৭১২৩৪৫৬৭৮). Numbers are saved in the standard 11-digit format. For Rocket, both the 11-digit mobile number and the 12-digit Rocket account number (mobile number + check digit) are accepted.

= What happens if a customer enters a Transaction ID that was already used? =

The order is not placed and the customer is asked to check the Transaction ID. Failed and cancelled orders are ignored, so a customer can retry after a failed attempt. Developers can turn the check off with the `ccd_block_duplicate_transaction_id` filter (return `false`).

= How do I add my QR code? =

Go to WooCommerce > Settings > Payments, open the method and use the "Select image" button next to "QR Code Image". The QR code appears on the checkout page under your number.

= What is Bangla QR? =

Bangladesh Bank's national interoperable QR code. One QR code can be paid from any participating bank or mobile financial service app. Enable "Bangla QR", add your merchant name and upload your Bangla QR image. The method stays hidden at checkout until a QR image is added.

= Can I add more than one bKash, Nagad, Rocket or Upay number? =

Yes. Open the method under WooCommerce > Settings > Payments and use "+ Add number" under More Numbers (each with an account type and an optional label like "Shop 2"). With more than one number, customers search and choose the number they sent money to, and the order shows it under "Paid To".

= Can customers upload the payment screenshot after placing the order? =

Yes, when Payment Screenshot is set to Optional or Required for that method. The thank-you page and My Account > Orders > View show an upload box while the order is pending, on hold or "Advance Received" (up to 5 uploads per order). A new upload replaces the previous file and adds an order note.

= Is the plugin available in Bangla? =

Yes. A full Bangla (bn_BD) translation is included. Set Settings > General > Site Language to বাংলা and the checkout, emails and settings appear in Bangla.

= Can I add more than one bank account? =

Yes, as many as you need. Under Bank Transfer, use "+ Add account". With more than one account, customers get a searchable list (5 at a time, with "Show more") and choose the account they paid into; the order shows which account it was.

= Where are payment screenshots stored? Can anyone see them? =

In a private folder inside your uploads directory, with random file names and direct access blocked. They never appear in the Media Library. Only users who can manage orders can open them, from the order screen. Uploads that never become an order are deleted after 24 hours. Accepted files: JPG, PNG, WebP and PDF, up to 5 MB (or your server's upload limit if lower).

= Can customers pay only the delivery charge in advance? =

Yes. In WooCommerce > Advance Payment choose either the delivery (shipping) charge or a fixed amount for inside / outside Dhaka, and optionally a minimum order total. The order is marked "Advance Received" and the remaining amount is shown to you and the customer.

= Why does a payment method not show at checkout? =

Check that it is enabled and has a receiving number or account (Bangla QR needs its QR image), and that the order amount is within the method's minimum / maximum. The Payments settings page warns you about enabled methods that customers cannot use.

= Which currencies are supported? =

The plugin works with your WooCommerce currency. It is designed primarily for BDT (Bangladeshi Taka) but does not restrict the store currency.

= Is WooCommerce required? =

Yes. WooCommerce must be installed and active.

== Privacy & Data Sharing ==

**Customer payment data.** The customer's payment number, Transaction ID and any payment screenshot are stored on your own site, attached to the order. Nothing is sent to CodeCareBD or any third party. Screenshots are kept in a private folder; delete them with the order if your policy requires it.

**Optional usage analytics.** This plugin can send usage and diagnostic data to the developer to help fix bugs and prioritise features. **This is disabled by default.** Nothing is collected or sent unless you explicitly opt in via the admin notice shown after activation. Declining (or ignoring) the notice leaves the plugin fully functional.

There are two separate, optional opt-ins:

1. Usage & diagnostics. When enabled, we collect: WordPress, PHP, MySQL and WooCommerce versions; your active theme, locale and whether the site is multisite; the plugin version and activation/deactivation/update events; which of the plugin's features are enabled; and a one-way (SHA-256) hash of your site URL. The raw site URL is never sent.

2. Product update emails (separate checkbox). Only if you tick this, we additionally collect your site administrator email to send occasional product updates.

You can change or withdraw your choice at any time from **Settings → Data Sharing** in your WordPress admin. Turning data sharing off stops all collection and requests deletion of previously collected data. Uninstalling does the same.

Data is sent over HTTPS to https://track.shakvaro.cloud and is handled as described in our privacy policy: https://shakvaro.com/wp-insights/privacy

== Screenshots ==

1. Block checkout: bKash with send money charge note, Copy button, QR code and optional payment screenshot upload.
2. Bank Transfer with several accounts: searchable account list, 5 at a time with "Show more".
3. Bank Transfer after choosing an account: account and routing numbers with Copy buttons.
4. Bangla QR at checkout: pay from any bank or mobile banking app.
5. Gateway settings: receiving number, QR code, charge, payment screenshot, prepayment discount and order limits.
6. Bank Transfer settings: add as many bank accounts as you need.
7. Advance payment: fixed amount for inside / outside Dhaka with a minimum order total.
8. Admin order screen: payment number, Transaction ID and the customer's payment screenshot.
9. Several bKash numbers: customers search and choose the number they sent money to.
10. Upload the payment screenshot after ordering, from the thank-you page or My Account.

== Changelog ==

= 1.6.0 - 2026-11-15 =
* NEW: Multiple receiving numbers for bKash, Nagad, Rocket, Upay and CellFin. With several numbers, customers search and pick the number they sent money to (5 at a time, "Show more"); the order shows it under "Paid To".
* NEW: Customers can upload or replace the payment screenshot after ordering, from the thank-you page or My Account > View order (up to 5 uploads per order, secured by the order key).
* NEW: Bangla (bn_BD) translation for the checkout, emails and settings, including the block checkout.
* IMPROVED: Text domain is now the plugin slug, so translations from translate.wordpress.org load automatically.
* IMPROVED: Settings labels of bKash, Nagad, Rocket and Payoneer are now translatable.
* IMPROVED: The account picker for several bank accounts is shared with the new wallet number picker.

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

= 1.6.0 =
Adds multiple numbers per wallet, payment screenshot upload after ordering and a full Bangla translation. Existing settings are kept.

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
