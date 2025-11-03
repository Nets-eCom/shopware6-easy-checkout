# NETS A/S - Shopware 6 Payment Module

|Module | Nets Easy Payment Module for Shopware 6
|------|----------
|Author | `Nets eCom`
|Shop Version | `6.6+ 6.7+`
|Version | `2.0.4`
|Guide | https://developer.nexigroup.com/nexi-checkout/en-EU/docs/checkout-for-shopware-shopware-6/
|Github | https://github.com/Nets-eCom/shopware6-easy-checkout

## CHANGELOG

### Version 2.0.4 - Released 2025-11-03

- fix: cast & rounding on tax per-unit price

### Version 2.0.3 - Released 2025-10-29

- fix: clean cache on update reference

### Version 2.0.2 - Released 2025-10-15

- fix: use tax status flag when calculating gross total
- feat: add loggable fetcher
- fix: add zero items option for charge and refund

### Version 2.0.1 - Released 2025-09-05

- fix: rename routes names to match supported prefixes

### Version 2.0.0 - Released 2025-08-27

- **Separate payment flow** - embedded & hosted are now offered as two distinct payment methods
- **Webhook support**: Full integration for real-time payment status updates.
- **Charge handling**: Added support for order charge, including workflow integration (e.g., charge action within a workflow).
- **Shopware 6.7 compatibility**: Ensures smooth operation with the latest Shopware version.
- **SDK integration**: Built-in support for our official SDK (`nexi-checkout/php-payment-sdk`).
- **PHP 8.4 support**: Stay up to date with the newest PHP release.
- **Layout enhancements**: Improved checkout design for a more seamless customer experience.