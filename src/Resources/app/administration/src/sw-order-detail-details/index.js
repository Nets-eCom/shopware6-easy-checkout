import template from "./sw-order-detail-details.html.twig";
import "./style.scss";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: ["nexiNetsPaymentDetailService"],
  mixins: [],
  data() {
    return {
      isLoading: false,
      disabled: true,
      isCaptureModalVisible: false,
      variant: "info",
      paymentDetails: {},
    };
  },
  created() {
    this.fetchPaymentDetails(this.orderId);
  },
  computed: {
    refunded() {
      const status = this.paymentDetails.status;

      return status === "refunded" || status === "partially_refunded"; // @todo use PaymentStatusEnum
    },

    statusTcString() {
      const status = this.paymentDetails.status;

      if (!status) {
        return "Undefined";
      }

      return `nexinets-payment-component.payment-details.status.${status}`;
    },
  },
  methods: {
    isNexiNetsPayment(transaction) {
      return transaction?.customFields?.hasOwnProperty("nexi_nets_payment_id") ?? false;
    },

    toggleCaptureModal() {
      this.isCaptureModalVisible = !this.isCaptureModalVisible;
    },

    setPaymentStatusVariant() {
      const status = this.paymentDetails.status;
      const variantMapping = {
        charged: "success",
        partially_charged: "warning",
        refunded: "warning",
        partially_refunded: "warning",
        cancelled: "danger",
      };
      this.variant = variantMapping[status] || "info";
    },

    async fetchPaymentDetails(orderId) {
      this.isLoading = true;
      await this.nexiNetsPaymentDetailService
        .getPaymentDetails(orderId)
        .then((data) => {
          this.paymentDetails = data;
        })
        .finally(() => {
          this.setPaymentStatusVariant();
          this.isLoading = false;
        });
    },
  },
});
