# NETS A/S - Shopware 6 Payment Module
============================================

|Module | Nets Easy Payment Module for Shopware 6
|------|----------
|Author | `Nets eCom`
|Prefix | `EASY-SW6`
|Shop Version | `6.4+`
|Version | `1.2.0`
|Guide | https://tech.nets.eu/shopmodules
|Github | https://github.com/Nets-eCom/shopware6-easy-checkout


### Note: This version 1.2.0 of Nets Easy Module is supported for shopware 6.4+ version, if you want to get this module installed for other shopware version i.e. 6.3 or lower, please go for Nets Checkout Module released version 1.1.4


## INSTALLATION

### Download / Installation
* Method 1
1. Unzip and upload the plugin file manually to root /custom/plugins OR Upload the zipped plugin file "shopware6-easy-checkout-master.zip" in admin > Settings > Plugins using the 'Upload plugin' function.
2. Clear your cache and update your indexes after a succesful installation in admin > Settings > Caches & Indexes.

* Method 2
1. Connect with a SSH client and navigate to root directory of your Shopware 6 installation and run commands :
bin/console plugin:install NetsCheckout
bin/console plugin:activate NetsCheckout
bin/console cache:clear

### Configuration
1. To configure and setup the plugin navigate to : Admin > Settings > System > Plugins
2. Locate the Nets payment plugin and press the 3 dotted button to access Configuration.

* Settings Description
1. Login to your Nets Easy account (https://portal.dibspayment.eu/). Test and Live Keys can be found in Company > Integration.
2. Payment Environment. Select between Test/Live transactions. Live mode requires an approved account. Testcard information can be found here: https://tech.dibspayment.com/easy/test-information 
3. Checkout Flow. Redirect / Embedded. Select between 2 checkout types. Redirect - Nets Hosted loads a new payment page. Embedded checkout inserts the payment window directly on the checkout page.
4. Enable auto-capture. This function allows you to instantly charge a payment straight after the order is placed.
   NOTE. Capturing a payment before shipment of the order might be lia ble to restrictions based upon legislations set in your country. Misuse can result in your Easy account bei ng forfeit.

### Operations
* Cancel / Capture / Refund
1. Navigate to admin > Orders > Overview. Press on Order number to access order details.
2. Choose your desired action beneath Nets API actions.
3. All transactions by Nets are accessible in our portal : https://portal.dibspayment.eu/login

### Troubleshooting
* Nets payment plugin is not visible as a payment method
- Ensure the Nets plugin is available in the right Sales Channel in the plugin configuration.
- Under Sales Channel section select your Shop Name for General Settings. Add plugin in Payment methods.
- Temporarily switch to Shopware 6 standard template. Custom templates might need addtional changes to ensure correct display. Consult with your webdesigner / developer.

* Nets payment window is blank
- Ensure your keys in Nets plugin Settings are correct and with no additional blank spaces.
- Temporarily deactivate 3.rd party plugins that might effect the functionality of the Nets plugin.
- Check if there is any temporary technical inconsistencies : https://nets.eu/Pages/operational-status.aspx

* Payments in live mode dont work
- Ensure you have an approved Live Easy account for production.
- Ensure your Live Easy account is approved for payments with selected currency.
- Ensure payment method data is correct and supported by your Nets Easy agreement.

### Contact
* Nets customer service
- Nets Easy provides support for both test and live Easy accounts. Contact information can be found here : https://nets.eu/en/payments/customerservice/

** CREATE YOUR FREE NETS EASY TEST ACCOUNT HERE : https://portal.dibspayment.eu/registration **
