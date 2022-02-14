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
            isLoading: true,
            amountAvailableForCapturing: 0,
            amountAvailableForRefunding: 0,
            captureButtonLoading: false,
            refundButtonLoading: false,
            orderState: null,
            refundPendingStatus:false,
			paymentMethod : null
        };
    },

    beforeMount(){
        this.getSummaryAmounts();
    },

    props: ['currentOrder'],

    methods: {

        getTransactionId(currentOrder) {
            const transaction = currentOrder.transactions.first();
            let result = null;
            if(transaction.hasOwnProperty('customFields') && transaction['customFields']) {
                if(transaction.customFields.hasOwnProperty('nets_easy_payment_details') &&
                    transaction.customFields['nets_easy_payment_details']) {
                    result = transaction.customFields.nets_easy_payment_details.transaction_id;
                }
            }
            return result;
        },

        canCapture() {

            if(this.amountAvailableForCapturing > 0 && this.orderState != "cancelled") {
                return true;
            }
            return false;
        },

        getSummaryAmounts() {
            let me;
            me = this;
            me.isLoading = true;

            if(this.getTransactionId(this.currentOrder)) {
                this.NetsCheckoutApiPaymentService.getSummaryAmounts(this.currentOrder)
                    .then((response) => {
                        //
                        me.amountAvailableForCapturing = response.amountAvailableForCapturing;
                        me.amountAvailableForRefunding = response.amountAvailableForRefunding;
                        me.isLoading = false;
                        me.orderState = response.orderState;
                        me.refundPendingStatus = response.refundPendingStatus; 
						me.paymentMethod = response.paymentMethod;
                    })
                    .catch((errorResponse) => {
                        //
                        me.isLoading = false;
                    });
            }
        },

        canRefund() {
            if(this.refundPendingStatus){
                return false;
            }
            if(this.amountAvailableForRefunding > 0 && this.amountAvailableForCapturing == 0 && this.orderState != "cancelled") {
                return true;
            }

            return false;
        },

        capture(paymentId) {
            let me = this;
            const orderId = this.currentOrder.id;
            const amount = this.amountAvailableForCapturing;
            me.isLoading = true;
            this.NetsCheckoutApiPaymentService.captureTransaction(orderId, paymentId, amount)
                .then((result) => {
                    this.createNotificationSuccess({
                        title: this.$tc('Nets'),
                        message: this.$tc('Capture processed successfully.')
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts();
                })
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: this.$tc('Nets'),
                        message: this.$tc(errorResponse.message)
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts();
                });
        },

        refund(paymentId) {
            let me = this;
            me.isLoading = true;

            const orderId = this.currentOrder.id;
            const amount = this.amountAvailableForRefunding;

            this.NetsCheckoutApiPaymentService.refundTransaction(orderId, paymentId, amount)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('Nets'),
                        message: this.$tc('Refund processed successfully.')
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts();
                })
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: this.$tc('Nets'),
                        message: this.$tc('Error occurred during refund')
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts();
                });
        },
    },
});
