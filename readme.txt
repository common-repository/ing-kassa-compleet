=== ING Kassa Compleet-Woocommerce Extension for iDEAL, Banktransfer, Cash on Delivery and Creditcard ===
Tags: ING, Kassa Compleet, PSP, iDEAL, Banktransfer, CreditCard, Visa, Mastercard
Contributors: kassacompleet
Requires at least: 3.3
Tested up to: 3.5.1
Stable tag: 1.0.7
License: The MIT License (MIT)
License URI: https://opensource.org/licenses/MIT

This extension installs the following payment methods; iDEAL, Banktransfer, Cash on Delivery and Creditcard

== Description ==

ING Kassa Compleet extension for WooCommerce tested with WooCommerce 2.1.10 

Kassa Compleet: The ING register for your web shop. Apply now for your free test account and experience the benefits of Kassa Compleet within minutes.

Most importantly, your customers can pay in an easy and trusted manner. With Kassa Compleet, your web shop is immediately equipped with all relevant Dutch payment methods.

View all incoming orders, completed payments and revenue at a glance on your mobile phone or tablet. Of course you will have quick access to your funds. Should you so desire, ING can remit the funds the next day. These and many other options can be easily configured in your account.

Easily and quickly integrate Kassa Compleet in your web shop. Should you have any questions, our support desk is there to help you.

== Installation ==
1. Upload `ingkassacompleet` to the `/wp-content/plugins/` directory
OR
1. Install the zip from the console.

2. Activate the plugin through the 'Plugins' menu in WordPress

After that configure the Webhook URL in the ING Kassa Compleet Merchant portal. The webhook URL should be:
https://www.example.com/?wc-api=woocommerce_ingkassacompleet
If you visit the webhook URL in your browser you should see:
"Only work to do if the status changed"

### For the API key:
Go to: WooCommerce > Settings > Checkout > ING Kassa Compleet Payments
And set the API key. (the API key can be found in the Kassa Compleet portal https://portal.kassacompleet.nl )

#### For iDEAL
Go to: WooCommerce > Settings > Checkout > iDEAL - ING Kassa Compleet Payments and enable / disable and set the title.
 
### For BankTransfer:
Go to: WooCommerce > Settings > Checkout > Banktransfer - ING Kassa Compleet Payments and enable / disable and set the title.
 
### For CreditCard:
Go to: WooCommerce > Settings > Checkout > Creditcard and enable / disable and set the title.