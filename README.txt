=== DoorDash Drive Integration ===
Contributors: Javy Vila Labrada
Tags: doordash, delivery, woocommerce, logistics, api
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later

== Description ==

Professional integration for DoorDash Drive API v2. This plugin allows businesses to automate their delivery infrastructure by connecting their store directly with DoorDash's fleet of Dashers.

The core focus of this version is **Security** and **Reliability**, featuring AES-256 encryption for sensitive API credentials and a real-time diagnostic system.

== Installation ==

1. Upload the `doordash-drive-integration` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the 'DoorDash' menu in your admin sidebar.
4. Configure your Store Pickup details and API credentials.

== Features Added & Fixed ==

* **AES-256 Encryption:** All API secrets are encrypted before being stored in the database.
* **Professional Admin UI:** A centered 800px layout with a clean, modern interface and custom Dashicons.
* **Intelligent Notice System:** * Ajax-based connection testing with auto-dismissing success messages (5 seconds).
    * Localized messages in English.
    * Proper notice placement to avoid layout shifts.
* **Advanced System Logs:** * Real-time activity monitor with a "Command Line" visual style.
    * Color-coded logging (Green for success, Red for errors).
    * Detailed Field-Level debugging (Know exactly why a request failed).
* **API v2 Optimization:** * Corrected naming conventions for `drop_off_address` and `pickup_phone_number`.
    * Strict E.164 phone number formatting for international compatibility.
    * Custom JWT (JSON Web Token) generation for secure authentication.

== Technical Specifications ==

* **API Version:** DoorDash Drive v2.
* **Security:** AES-256-CBC Encryption via OpenSSL.
* **Logging:** Rotating logs stored in WordPress options with field-specific error reporting.
* **Requirements:** PHP 7.4+ and a valid DoorDash Developer Account.

== Frequently Asked Questions ==

= Why do I see "Validation Failed"? =
DoorDash is very strict. This usually happens if the phone number is not in +16505551111 format or if the address is not recognized by Google Maps. Check the "System Logs" section for the exact field error.

= Is my API Secret safe? =
Yes. We use a custom Cipher class that encrypts your credentials using a unique key defined in your plugin's main file.

== Screenshots ==

1. Main Settings Panel - Professional layout with Store and API configuration.
2. System Logs - The "Terminal-style" console showing real-time API responses.
3. Connection Test - Ajax feedback indicating a successful link with DoorDash.

== Changelog ==

= 1.0.0 =
* Initial professional release.
* Added AES encryption.
* Added Terminal-style logs.
* Fixed v2 API validation bugs.