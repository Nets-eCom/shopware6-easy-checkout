const { Component, Mixin } = Shopware;
import template from './sw-order.html.twig';

/**
 * This is only used pre-6.5
 */
Shopware.Component.override('sw-order-user-card', {
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
            orderState: null,
            refundPendingStatus:false,
			paymentMethod : null
        };
    },

    beforeMount(){
        this.getSummaryAmounts(this.currentOrder);
    },

    props: ['currentOrder'],
});
