# NETS A/S - Shopware 6 Payment Module
============================================

|Module | Nets Easy Payment Module for Shopware 6
|------|----------
|Author | `Nets eCom`
|Prefix | `EASY-SW6`
|Shop Version | `6.6+`
|Version | `1.5.7`
|Guide | https://developer.nexigroup.com/nexi-checkout/en-EU/docs/checkout-for-shopware-shopware-6/
|Github | https://github.com/Nets-eCom/shopware6-easy-checkout

## CHANGELOG

### Version 1.5.7 - Released 2024-11-07

* Fix: prevent order transaction marked as paid if charged amount is 0

### Version 1.5.6 - Released 2024-09-04

* Fix: Add support for Custom Products extension

### Version 1.5.5 - Released 2024-08-29

* Fix: prevent order transition if marked as failed

### Version 1.5.4 - Released 2024-08-07

* Fix: check amount mismatch on finalize

### Version 1.5.3 - Released 2024-05-29

* Fix: invoke get payment with sales channel context

### Version 1.5.2 - Released 2024-05-28

* Fix: pass sales channel context on update payment reference

### Version 1.5.1 - Released 2024-05-20

* Update: support Klarna in with embedded flow
* Fix: add missing sales channel argument

### Version 1.5.0 - Released 2024-04-25

* Update: Handle sales channel context
* Update: Add support for version 6.6.1
* Fixed: Allow order refund partially
* Fixed: Update initial types

### Version 1.4.3 - Released 2024-04-10

* update: handle sales channel context

### Version 1.4.2 - Released 2024-03-04

* update package description
* remove github workflow 

### Version 1.4.1 - Released 2024-01-29

* embedded checkout (use proper reference to order id)
* add supported country prefixes
* mark payment as authorized after successful reservation
* translation updates
* improve refund, charge ux in administration panel

### Version 1.4.0 - Released 2023-09-04
* Add compatibility for Shopware 6.5

### Version 1.3.9 - Released 2023-02-08
* Plugin improvements and bug fixes

### Version 1.3.8 - Released 2022-11-14
* Plugin version update notification api
* Add support for order items with no product Id
* Admin Order details page loading icon issue

### Version 1.3.7 - Released 2022-09-06
* Improved A2A Payment Methods compatibility

### Version 1.3.6 - Released 2022-05-13
* Validation Message Fixed for API Test button

### Version 1.3.5 - Released 2022-05-10
* Minor fixes

### Version 1.3.4 - Released 2022-04-29
* Fixed : Issue for order id function for older 6.4 shopware version

### Version 1.3.3 - Released 2022-04-23
* Fixed :
1. Compatibility issues for version 6.4.9.0 and onwards
2. Cancel order on click of back button
* Update : Added Test API button in configuration

### Version 1.2.3 - Released 2021-11-03
* Fixed : Phone number fix for ratepay payment in nets easy checkout

### Version 1.2.2 - Released 2021-10-26
* Fixed : Hosted checkout language fix for nets easy checkout


### Version 1.2.1 - Released 2021-09-06
* Fixed : frontend responsive design
* Update : Minor changes to settings

### Version 1.2.0 - Released 2021-08-23
* Fixed : Partial Capture/Refund of order amount, Support for shopware 6.4+ version

### Version 1.1.4 - Released 2021-05-31
* Fixed : shipping cost fix as per net/gross and multi sales channel

### Version 1.1.3 - Released 2021-05-20
* Fixed : Bugfixes in product net/gross amount and tax calculations
* Update : added merchant terms url for embedded/hosted checkout and cancel url for hosted checkout

### Version 1.1.2 - Released 2021-01-21
* Fixed : Bugfixes in order details section
* Update : Improved layout on Embedded Checkout
* Docs: added license, changelog and readme files

### Version 1.1.1 - Released 2021-01-15
* Update : Ver.no update

### Version 1.1.0 - Released 2021-01-14
* Fixed : Minor misc. bugs
* Update : Settings description
* Update : Improved Language and Currency support
* New : added Embedded Checkout payment window

### Version 1.0.2 - Released 2020-10-04
* Fixed : Minor misc. bugs

### Version 1.0.1 - Released 2020-06-10
* Fixed : Loader deactivation

### Version 1.0.0 - Released 2020-05-26
* New : Nets Easy plugin release with hosted payment page support

