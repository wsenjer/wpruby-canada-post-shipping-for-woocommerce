=== WPRuby Canada Post Shipping for WooCommerce ===
Contributors: waseem_senjer, wprubyplugins
Donate link: https://wpruby.com/plugin/woocommerce-canada-post-extension-pro/?utm_source=canadapost-lite&utm_medium=donatelink&utm_campaign=freetopro
Tags: canada post, shipping rates, woocommerce shipping, canada, shipping calculator
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Live Canada Post rates at WooCommerce checkout: Regular Parcel, Expedited Parcel, Xpresspost, Priority, US and international, from your own account.

== Description ==

Show your customers live Canada Post shipping rates at checkout, quoted from your own Canada Post account. Rates come from Canada Post's Rating API for every order, so they follow the weight and size of the cart and the customer's address.

= What the free version does =

* **Live rates for Canada, the US and the world:** Regular Parcel, Expedited Parcel, Xpresspost and Priority in Canada; Expedited Parcel USA, Xpresspost USA, Tracked Packet USA and Small Packet USA Air; Xpresspost International, Tracked Packet International, International Parcel and Small Packet International (air and surface).
* **Your prices:** business rates for commercial contract accounts, retail rates for Solutions for Small Business, or choose per shipping zone.
* **Choose and rename services:** tick the services each zone offers and change the name customers see.
* **Delivery estimates:** show "(est. 2 business days)" next to each service.
* **Handling fees:** add a fixed amount, a percentage, or both. Negative values give a discount.
* **Correct tax:** Canada Post's prices include GST/HST/PST. When shipping is taxable, the rate is shown before tax and WooCommerce adds your tax once.
* **Simple packing:** items go together in as few parcels as the 30 kg limit allows, or one parcel per item, with default weights and sizes for products that have none.
* **Test mode:** connect a Canada Post Test app; its sample rates are shown only to shop managers.
* **Block and classic checkout:** rates appear in both, and the plugin is compatible with WooCommerce's order storage (HPOS).

= Need more? Canada Post Pro =

[Canada Post Pro](https://wpruby.com/plugin/woocommerce-canada-post-extension-pro/?utm_source=canadapost-lite&utm_medium=readme&utm_campaign=freetopro&utm_content=pro) adds everything after checkout:

* Print Canada Post labels from the order screen, one order at a time or in bulk.
* End-of-day manifests, pickups and return labels.
* Tracking numbers and status updates in order emails and My Account.
* Delivery to a post office chosen at checkout, and AddressComplete address search.
* Your own boxes with 3D packing, free shipping thresholds, per-service price adjustments and a fallback rate when Canada Post is down.

Your shipping zones keep their settings when you upgrade.

= Links =

* [How to show Canada Post shipping rates at WooCommerce checkout](https://wpruby.com/canada-post-shipping-rates-woocommerce/?utm_source=canadapost-lite&utm_medium=readme&utm_campaign=freetopro&utm_content=guide)
* [Canada Post Pro](https://wpruby.com/plugin/woocommerce-canada-post-extension-pro/?utm_source=canadapost-lite&utm_medium=readme&utm_campaign=freetopro&utm_content=links)
* [Support forum](https://wordpress.org/support/plugin/wpruby-canada-post-shipping-for-woocommerce/)

== Installation ==

= From your WordPress dashboard =

1. Go to Plugins → Add New.
2. Search for "WPRuby Canada Post Shipping".
3. Click Install Now, then Activate.

= After activation =

1. On the [Canada Post Developer Portal](https://developer-developpeur.canadapost-postescanada.ca/devportal-portaildesdeveloppeurs/), create an app and add the **Rating** API product to it.
2. In WooCommerce → Settings → Shipping → Canada Post, enter the app's API key and secret, your customer number and your ship-from postal code. Choose whether it's a Production or a Test app, then save.
3. Click **Test connection**. It asks Canada Post for a Regular Parcel rate from your postal code to itself.
4. Go to WooCommerce → Settings → Shipping, edit a shipping zone (for example "Canada"), add the **Canada Post** method and choose its services.

Products need a weight for accurate rates, and a size if Canada Post bills your parcels by volume.

== Frequently Asked Questions ==

= What do I need from Canada Post? =

A Canada Post business account (Solutions for Small Business or a commercial contract) with a customer number, and an app on the Canada Post Developer Portal with the Rating API product. The app gives you an API key and secret.

= Why don't I see any Canada Post rates at checkout? =

Check that:

* the API key, secret and customer number are saved, and Test connection succeeds;
* the zone that matches the customer's address has the Canada Post method, with at least one service ticked for that destination;
* the address has a postal code (Canada) or ZIP code (US);
* the app is a Production app: rates from a Test app are shown to shop managers only.

Turn on Debug mode in the Canada Post settings to see Canada Post's error on the cart and checkout (shop managers only) and in WooCommerce → Status → Logs.

= Are the rates before or after tax? =

Canada Post's price includes GST/HST/PST. When the zone's tax status is "Taxable", the plugin removes Canada Post's tax and WooCommerce charges your own tax on shipping. When it's "None", customers pay Canada Post's price including tax.

= My store isn't in Canadian dollars. =

Canada Post quotes in CAD. Set the CAD exchange rate in the Canada Post settings, and rates are multiplied by it.

= Can I print shipping labels? =

Not with the free version. [Canada Post Pro](https://wpruby.com/plugin/woocommerce-canada-post-extension-pro/?utm_source=canadapost-lite&utm_medium=readme&utm_campaign=freetopro&utm_content=faq-labels) prints labels from the order screen, in bulk, with manifests, tracking and returns.

= How do I upgrade to Pro? =

Install and activate Canada Post Pro. While Pro is active, this free plugin stops running, and Pro uses your existing Canada Post shipping zones. Enter your API key and secret in Pro, then deactivate and delete the free plugin.

= Can I keep the API key out of the database? =

Yes. Define `WPRUBY_CPS_API_KEY` and `WPRUBY_CPS_API_SECRET` in wp-config.php and the settings fields are locked.

== Screenshots ==

1. The Canada Post settings: account, ship-from postal code and parcels.
2. Choosing and renaming the services a shipping zone offers.
3. Canada Post rates with delivery estimates at checkout.

== External services ==

This plugin connects to Canada Post's API to get shipping rates. It is needed to show Canada Post rates at checkout.

* **When:** when WooCommerce calculates shipping for a cart or checkout whose zone uses the Canada Post method, and when a shop manager clicks Test connection. Rates are cached for 10 minutes and access tokens until they expire, so repeated identical requests aren't sent again.
* **What is sent:** your API key and secret (to get an access token), your customer number and contract number, your ship-from postal code, the customer's destination country and postal or ZIP code, the weight and size of each parcel, and the services to quote. No customer names, emails, street addresses or order contents are sent.
* **Where:** `api.canadapost-postescanada.ca`, run by Canada Post Corporation.

Canada Post's [terms of use](https://www.canadapost-postescanada.ca/cpc/en/support/kb/company-policies/terms-conditions/legal-terms-of-use-and-conditions.page) and [privacy policy](https://www.canadapost-postescanada.ca/cpc/en/our-company/transparency-and-trust/privacy-centre/privacy-policy.page).

== Source code ==

The plugin ships no compiled or minified code. The PHP, JavaScript and CSS in the plugin are the source.

== Changelog ==

= 1.0.0 =
* Added: Live Canada Post rates for Canada, the US and international destinations, from Canada Post's Rating API.
* Added: Per-zone services with custom names, business or retail rates, handling fees and delivery estimates.
* Added: Test connection button, Test app mode for shop managers, debug logging and wp-config.php constants for the API key and secret.

== Upgrade Notice ==

= 1.0.0 =
First release.

== Disclaimer ==

Canada Post and the Canada Post services named here are trademarks of Canada Post Corporation. This plugin is made by WPRuby and is not affiliated with or endorsed by Canada Post.
