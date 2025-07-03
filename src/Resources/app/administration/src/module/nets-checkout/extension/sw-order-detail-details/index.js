const { Mixin } = Shopware;
import template from './sw-order-detail-details.html.twig';

/**
 * This is only used from 6.5 onwards
 */
Shopware.Component.override('sw-order-detail-details', {
    template,

    inject: ['NetsCheckoutApiPaymentService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('nets-checkout-order')
    ],

    data() {
        return {
            isLoading: true,
            amountAvailableForCapturing: 0,
            amountAvailableForRefunding: 0,
            captureButtonLoading: false,
            refundButtonLoading: false,
            transactionState: null,
            refundPendingStatus:false,
            paymentMethod: null
        };
    },

    watch: {
        transactionState(newState) {
            if (this.transaction.stateMachineState.technicalName !== newState.technicalName) {
                this.transaction.stateMachineState = newState
            }
        }
    },

    beforeMount(){
        this.getSummaryAmounts(this.order);
    },
});
