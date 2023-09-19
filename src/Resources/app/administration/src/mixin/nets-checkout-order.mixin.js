const { Mixin } = Shopware;

Mixin.register('nets-checkout-order', {
    methods: {
        getTransactionId(transaction) {
            let result = null;
            if(transaction.hasOwnProperty('customFields') && transaction['customFields']) {
                if(transaction.customFields.hasOwnProperty('nets_easy_payment_details') &&
                    transaction.customFields['nets_easy_payment_details']) {
                    result = transaction.customFields.nets_easy_payment_details.transaction_id;
                }
            }
            return result;
        },

        canCapture(orderStateTechnicalName) {
            if(this.amountAvailableForCapturing > 0 && orderStateTechnicalName != "cancelled") {
                return true;
            }
            return false;
        },

        getSummaryAmounts(order) {
            let me;
            me = this;
            me.isLoading = true;

            if(this.getTransactionId(order.transactions.first())) {
                this.NetsCheckoutApiPaymentService.getSummaryAmounts(order)
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
            } else {
                me.isLoading = false;
            }
        },

        canRefund(orderStateTechnicalName) {
            if(this.refundPendingStatus){
                return false;
            }
            if(this.amountAvailableForRefunding > 0 && this.amountAvailableForCapturing == 0 && orderStateTechnicalName != "cancelled") {
                return true;
            }

            return false;
        },

        capture(paymentId, order) {
            let me = this;
            const orderId = order.id;
            const amount = this.amountAvailableForCapturing;
            me.isLoading = true;
            this.NetsCheckoutApiPaymentService.captureTransaction(orderId, paymentId, amount)
                .then((result) => {
                    this.createNotificationSuccess({
                        title: this.$tc('Nets'),
                        message: this.$tc('Capture processed successfully.')
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts(order);
                })
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: this.$tc('Nets'),
                        message: this.$tc(errorResponse.message)
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts(order);
                });
        },

        refund(paymentId, order) {
            let me = this;
            me.isLoading = true;

            const orderId = order.id;
            const amount = this.amountAvailableForRefunding;

            this.NetsCheckoutApiPaymentService.refundTransaction(orderId, paymentId, amount)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('Nets'),
                        message: this.$tc('Refund processed successfully.')
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts(order);
                })
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: this.$tc('Nets'),
                        message: this.$tc('Error occurred during refund')
                    });
                    me.isLoading = false;
                    this.getSummaryAmounts(order);
                });
        },
    }
});
