* [x] Abstract the QR code generation from the WooCommerce integration to a common class
* [x] GiveWP: On the donation confirmation page, http://localhost:8888/donation-confirmation/, where it says "Payment Pending: Your donation is currently processing." it should display the payment QR code. We need to write an E2E test for this because loading that URL without the context of a new donation does not work. 
* [x] GiveWP: The actual text "Payment Pending: Your donation is currently processing." should say (e.g. for $25 to @testvendor) "Payment Pending: Please send your donation of $25 via Venmo to @testvendor."
* [x] GiveWP: In the text "Payment Pending: Please send your donation of $25 via Venmo to @testvendor.", "$25 via Venmo to @testvendor" should be the same hyperlink the QR code has
* [x] GiveWP: Text saying "Payment from @admin to @testvendor" above the  QR code is unnecessary since it just repeats what was said immediately above.
* [x] GiveWP: The text below the QR code, "$25 to @testvendor" is also redundant
* [x] GiveWP: http://localhost:8888/donate-v3/ the newer donate form's confirmation needs the QR code displayed, similar to above
* [x] GiveWP: http://localhost:8888/donate-v3/ It should not say "Hey Brian, thanks for your donation!" whil donation is pending. Instead, display the payment QR code there. Remove the lower "Scan to pay" QR code. Change "Payment Pending Please send your donation of $10 via Venmo to @testvendor." to just "Payment Pending $10 via Venmo to @testvendor."   
* [x] GiveWP: http://localhost:8888/donate-v3/ Should not say "Success" while payment is pending, instead it should say ~"Please pay $25 via Venmo to @testvendor" which should be a href the same as the QR code
* [x] GiveWP: Donations list page `/wp-admin/edit.php?post_type=give_forms&page=give-payment-history` should have a "mark paid" link underneath "pending" in the Status column for Venmo donations which opens a popup asking for the venmo username they paid with, transaction id, and datepicker with current date selected, timepicker with no preselected time, all fields optional.
* [ ] GiveWP: "new view" needs the "mark paid" button added.
* [x] GiveWP: After a donation is marked paid via the donations list page modal, there should be an admin notice displaying the details and linking to the single donation view (so admins know which item the just updated)
* [x] GiveWP: the top of the settings page should say the last time there was a donation via Venmo complete / pending / abandoned

* [ ] When a Venmo username is entered, on the settings page, donation page, WooCommerce checkout, sanitize it so it always has a leading "@" (strip leading @ and add leading @)

* [ ] GiveWP: "Venmo" should be displayed as "Venmo - @payment_address" around the admin UI.

* [ ] Settings page should have configuration for email. That should largely be defined in the reconciliation library?

* [ ] GiveWP: Donor dashboard, Donation receipt should show metadata for Venmo transaction id linking to Venmo (when complete).
* [ ] GiveWP: The donation received page, while open, should be firing an email check every 60 seconds (assuming IMAP) (slowing down/fibonnacci) or just a status check every 15 seconds (assuming email push via AWS/Cloudflare) and changing any reference to "pending" to "complete" when appropriate.

* [ ] Check now button on orders'/donations'/settings pages when using IMAP.
* 
* [ ] GiveWP & WooCommerce: The email sent by WooCommerce should contain payment instructions.
* [ ] GiveWP & WooCommerce: The payment pending email should delay for ten minutes to give them time to pay naturally.
