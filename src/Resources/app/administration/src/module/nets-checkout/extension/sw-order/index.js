const { Component, Mixin } = Shopware;
import template from './sw-order.html.twig';

Shopware.Component.override('sw-order-user-card', {
    template,

    inject: ['NetsCheckoutApiPaymentService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    data() {
        return {
            disableCaptureButton: false,
            disableRefundButton: true,
            isLoading: true
        };
    },

    methods: {
        captureOrder(currentOrder) {
            let me = this;
            me.disableCaptureButton = true;
            me.isLoading = true;

            this.NetsCheckoutApiPaymentService.captureTransaction(currentOrder)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('Nets'),
                        message: this.$tc('Capture processed successfully.')
                    });
                    me.disableCaptureButton = true;
                    me.disableRefundButton = false;
                    me.isLoading = false;
               })
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: this.$tc('Nets'),
                        message: this.$tc('Error occurred during capture.')
                    });
                    me.disableCaptureButton = false;
                    me.isLoading = false;
                });
        },

        refundOrder(currentOrder) {
            let me = this;
            me.disableRefundButton = true;
            me.isLoading = true;

            this.NetsCheckoutApiPaymentService.refundTransaction(currentOrder)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('Nets'),
                        message: this.$tc('Refund processed successfully.')
                    });
                    me.disableRefundButton = true;
                    me.isLoading = false;
                })
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: this.$tc('Nets'),
                        message: this.$tc('Error occurred during refund')
                    });
                    me.disableRefundButton = false;
                    me.isLoading = false;
                });
        },

        getTransactionId(currentOrder) {
            var transaction = currentOrder.transactions.first();
            var result = false;
            if(transaction.hasOwnProperty('customFields') && transaction['customFields']) {
                if(transaction.customFields.hasOwnProperty('nets_easy_payment_details') &&
                    transaction.customFields['nets_easy_payment_details']) {
                    result = transaction.customFields.nets_easy_payment_details.transaction_id;
                }
            }
            return result;
        },

        canCapture(currentOrder) {
           let me = this;

           if(me.disableCaptureButton == true ) {
                return false;
            }


           var transaction = currentOrder.transactions.first();
           return transaction.customFields.nets_easy_payment_details.can_capture;
        },

        canRefund(currentOrder) {
            let me = this;
            if(me.disableRefundButton == false) {
                return true;
            }
            if(me.disableRefundButton == true) {
                return false;
            }
            var transaction = currentOrder.transactions.first();
            return transaction.customFields.nets_easy_payment_details.can_refund;
        }
    }

});
