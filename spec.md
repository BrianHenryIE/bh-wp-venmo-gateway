* [x] Abstract the QR code generation from the WooCommerce integration to a common class
* [x] GiveWP: On the donation confirmation page, http://localhost:8888/donation-confirmation/, where it says "Payment Pending: Your donation is currently processing." it should display the payment QR code. We need to write an E2E test for this because loading that URL without the context of a new donation does not work. 
* [x] GiveWP: The actual text "Payment Pending: Your donation is currently processing." should say (e.g. for $25 to @testvendor) "Payment Pending: Please send your donation of $25 via Venmo to @testvendor."
* [x] GiveWP: In the text "Payment Pending: Please send your donation of $25 via Venmo to @testvendor.", "$25 via Venmo to @testvendor" should be the same hyperlink the QR code has
* [x] GiveWP: Text saying "Payment from @admin to @testvendor" above the  QR code is unnecessary since it just repeats what was said immediately above.
* [x] GiveWP: The text below the QR code, "$25 to @testvendor" is also redundant
* [x] GiveWP: http://localhost:8888/donate-v3/ the newer donate form's confirmation needs the QR code displayed, similar to above
* [x] GiveWP: http://localhost:8888/donate-v3/ It should not say "Hey Brian, thanks for your donation!" whil donation is pending. Instead, display the payment QR code there. Remove the lower "Scan to pay" QR code. Change "Payment Pending Please send your donation of $10 via Venmo to @testvendor." to just "Payment Pending $10 via Venmo to @testvendor."   
* [x] GiveWP: http://localhost:8888/donate-v3/ Should not say "Success" while payment is pending, instead it should say ~"Please pay $25 via Venmo to @testvendor" which should be a href the same as the QR code
