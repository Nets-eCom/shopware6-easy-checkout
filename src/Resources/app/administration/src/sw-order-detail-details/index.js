import template from "./sw-order-detail-details.html.twig";
import "./style.scss";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: [],
  mixins: [],
  data() {
    return {
      isLoading: false,
      disabled: true,
      isCaptureModalVisible: false,
    };
  },
  methods: {
    getPaymentMethod(paymentDetails) {
      return paymentDetails?.customFields?.hasOwnProperty("nexi_nets_payment_id") ?? false;
    },
    toggleCaptureModal() {
      this.isCaptureModalVisible = !this.isCaptureModalVisible;
    },
  },
});
