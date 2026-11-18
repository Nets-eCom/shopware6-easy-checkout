# Checkout for Shopware

This guide describes how to install, configure, and use the Nexi Checkout web shop module for Shopware 6.6.

## Before you start

> Before you start, you need a Checkout Portal account. See the guide [Create account](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/create-a-checkout-portal-account/) for more information about creating a free test account.

## Overview

Our Shopware plugin is the perfect extension to enable the Nexi Checkout to its full potential for your Shopware store. Checkout supports most popular payment methods.

You may see below all the payment methods offered by Checkout. This list refers to all markets.

Depending on your country or region, the list may vary. If you are uncertain about a specific payment method and whether it is available in your country or region, please [contact Support](https://developer.nexigroup.com/nexi-checkout/en-EU/support/) for more information.

![Nexi Payment Methods](./images/nexiPaymentMethods.png)

## Shop features

- A smart mix of payment methods to suit all preferences.

## Administration features

- Quick setup and flexible configuration.
- Intuitive order management with synchronized payments status via webhooks.
- Refund and capture available with new items list feature.
- Compatibility with discounts, tax (VAT), and shipping options.
- Automate various processes with the flow builder functionality.

## Installation

How to install the Checkout module for Shopware 6:

## Using GitHub
1. Connect with an SSH client and navigate to the root directory of your Shopware 6 installation.
2. Clone the repository by running the command: git clone git@github.com:Nets-eCom/shopware6-easy-checkout.git --branch main --single-branch custom/plugins/NetsNexiCheckout

After cloning run those commands: 
1. `bin/console plugin:refresh`
2. `bin/console plugin:install -a NetsNexiCheckout`
3. `bin/build-js.sh`

The module is now installed and ready to be configured for your Checkout account.

### Update from v2.0.4 to v2.1.0

The plugin technical name has changed to `NetsNexiCheckout`.
The previous name `NexiCheckout` is deprecated. After updating, both names will appear in `My extensions` view, or when
running `bin/console plugin:list`.

Update steps:

1. `bin/console plugin:refresh`
2. `bin/console plugin:deactivate NexiCheckout`
3. `bin/console plugin:install -a NetsNexiCheckout`
4. `bin/build-js.sh`

## Configuration

After installing the module, you need to do some basic configuration of the module in Shopware Admin:

1. Navigate to `Extensions > My extensions > Nexi Checkout`
2. Locate the Nexi Group payment plugin and press the button with three dots (...) to access the configuration.
3. Fill out the required fields, such as integration keys (secret keys and checkout keys), and Webhook Code.
4. (Optional) Customize the module according to your needs using the additional settings on the configuration page.

Both integration keys can be found in Checkout Portal. See the following pages for more help:

- [Where can I find my merchant number (merchant ID)?](https://developer.nexigroup.com/nexi-checkout/en-EU/support/where-can-i-find-my-merchant-number-merchant-id/)
- [Access your integration keys](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/access-your-integration-keys/)

## Embedded vs Hosted

To configure Nexi Checkout as payment method for the Shopware 6.7 plugin, please go to the Storefront configuration page under Sales Channels:
Go to Payment methods and using the dropdown, select either Nexi Checkout Embedded or Nexi Checkout Hosted.
Click on Save your changes.

Then go to `Settings > Payment Methods`, find Nexi Checkout Embedded or Nexi Checkout Hosted, click Edit details, change Name to Nexi Checkout, and click on Save.

## Order management

It's possible to manage orders directly in the Shopware administration:

1. Navigate to `Admin > Orders > Overview`.
2. Press on an order line to access order details.
3. Go to the `Details` section, with the new Nexi order management component.

![Nexi Order details](./images/order-details.png)

Now all the actions (Charge, Refund, Cancel) can be done via the new modal component.

![Charge modal](./images/charge-modal.png)

Clicking on `Max. amount` will automatically pass the maximum value to the amount field.

### Partials Charge & Refund

The biggest change from the currently used component is that partial charge and refund will be able to be made by selecting the item from the order list rather than manually typing out the value into the field. The list can be accessed by the checkbox field.

![Partials modal](./images/nexi-partials.png)

All transactions performed by Nexi Group are accessible in Checkout Portal.

## MCP capabilities

The plugin provides Model Context Protocol (MCP) tools so an MCP-compatible assistant can retrieve payment details and manage Nexi Checkout payments through Shopware.

### Requirements

- A Shopware installation with MCP support enabled and an MCP client connected to it.
- The Nexi Checkout plugin installed and configured.
- `order:read` permission to retrieve payment details; both `order:read` and `order:update` permissions to charge, refund, or cancel payments.
- If `shopware.mcp.allowed_tools` is configured with a non-empty list, include the required tool names below.

### Available tools

| Tool | Capability |
| --- | --- |
| `nexi-checkout-payment-details` | Retrieve current Nexi payment details. |
| `nexi-checkout-charge-payment` | Charge all remaining funds or a specified partial amount. |
| `nexi-checkout-refund-payment` | Refund all remaining charged funds or a specified partial amount. |
| `nexi-checkout-cancel-payment` | Cancel a reserved payment. |

Each tool accepts exactly one identifier: the Shopware order ID (`orderId`), order number (`orderNumber`), or Nexi payment ID (`paymentId`).

For charges and refunds, the optional `amount` is a decimal string in the payment currency, such as `"10.00"`. Omit it to use the full amount available for the action.

### Preview and confirmation

Charge, refund, and cancel actions use a preview and confirmation flow:

1. Call the tool without `confirmation` to preview the action.
2. Review the payment, amount, and currency in the preview.
3. Call the same tool with the same action parameters and pass the exact `requiredConfirmation` value from the preview as `confirmation` to execute it.

### Example prompts

```text
Show payment details for order number 10000
Charge 10 EUR for order number 10000
Refund all available funds for payment ID <payment-id>
Cancel the payment for order number 10000
```

## Flow Builder

With Shopware 6.6, our Nexi flow can be aligned with Shopware flow to automate various processes within the payment system.
[Getting Started with Flow Builder](./flow-builder/getting-started.md)

## Klarna

The payment method Klarna requires a phone number to function properly. To ensure that Klarna will appear as a payment option in the Nexi Group payment window, it is essential to configure the phone number field correctly. For more information, please refer to the Klarna guide.

To add the phone number field in Shopware 6, follow these steps:

1. Navigate to `Settings > Shop > Log-in & Sign-up`.
2. Check the box for `Show phone number`.
3. Check the box for `Phone number field required`.

To learn more, visit Shopware 6's documentation.

> We do not support third-party plugins that provide phone number functionality, and we cannot guarantee that their solution will be compatible with the Klarna payment method.

## Apple Pay

Apple Pay configuration for Shopware plugin is described on the [Apple Pay payment method page](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/apple-pay/#build-shopware-65).

## Troubleshooting

Below are some of the most common configuration errors, their cause, and steps that you can follow to solve them.

### Nexi Group payment plugin is not visible as a payment method

- Ensure the Nexi Group module is available in the right Sales Channel in the plugin configuration.
- Under the Sales Channel section, select your Shop Name under General settings. Add the plugin in Payment methods.
- Temporarily switch to the Shopware 6 standard template. Custom templates might need additional changes to ensure correct display. Consult with your web designer or developer.

### Nexi Group payment window is blank

- Ensure your integration keys in the Nexi Group plugin settings are correct and do not contain additional blank spaces.
- Temporarily deactivate third-party plugins that might affect the functionality of the plugin.
- Check if there are any temporary technical inconsistencies: [Operational Status](https://easy-status.developers.nets.eu/)

### Payments in live mode don't work

- Ensure you have an approved Live Checkout account for production.
- Ensure your Live Checkout account is approved for payments with the selected currency.
- Ensure payment method data is correct and supported by your Checkout agreement.

## Go live checklist

For more information, refer to the section [Go-live checklist](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/go-live-checklist/).

## See also

- [Create account](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/create-a-checkout-portal-account/)
- [Test environment](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/test-environment/)
- [Test card processing](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/test-card-processing/)
- [Test invoice & installment processing](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/test-invoice-installment-processing/)
- [Support](https://developer.nexigroup.com/nexi-checkout/en-EU/support/)
