import template from "./sw-order-detail-details.html.twig";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: [],
  mixins: [],
  data() {
    return {
      isLoading: true,
      amountAvailableForCapturing: 0,
      amountAvailableForRefunding: 0,
      captureButtonLoading: false,
      refundButtonLoading: false,
      orderState: null,
      refundPendingStatus: false,
      paymentMethod: null,
    };
  },
  beforeMount() {},
});
