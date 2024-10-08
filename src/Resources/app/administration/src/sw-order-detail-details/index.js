import template from "./sw-order-detail-details.html.twig";
import "./style.scss";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: ['nexiNetsPaymentDetailService'],
  mixins: [],
  data() {
    return {
      isLoading: false,
      disabled: true,
      isCaptureModalVisible: false,
      paymentDetails: {},
    };
  },
  created() {
    this.fetchPaymentDetails(this.orderId);
  },
  computed: {
    refunded() {
      const status = this.paymentDetails.status;

      return status === 'refunded' || status === 'partially_refunded'; // @todo use PaymentStatusEnum
    },

    statusTcString() {
      const status = this.paymentDetails.status;

      if (!status) {
        return 'undefined';
      }

      return 'nexinets-payment-component.payment-details.status.' + status;
    },
  },
  methods: {
    isNexiNetsPayment(transaction) {
      return transaction?.customFields?.hasOwnProperty("nexi_nets_payment_id") ?? false;
    },
    toggleCaptureModal() {
      this.isCaptureModalVisible = !this.isCaptureModalVisible;
    },
    async fetchPaymentDetails(orderId) {
      this.isLoading = true;
      await this.nexiNetsPaymentDetailService.getPaymentDetails(orderId)
        .then((data) => {
          this.paymentDetails = data;
        })
        .finally(() => {this.isLoading = false});
    },
  },
});
