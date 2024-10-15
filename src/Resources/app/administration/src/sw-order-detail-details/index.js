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
      hasFetchError: false,
      netsPaymentId: null,
      paymentDetails: {},
      maxChargeAmount: "00.00",
    };
  },
  created() {
    if (this.isNexiNetsPayment) {
      this.fetchPaymentDetails(this.orderId);
    }
  },
  computed: {
    isNexiNetsPayment() {
      if (!this.transaction?.customFields?.hasOwnProperty("nexi_nets_payment_id")) {
        return false;
      }
      console.log(this.order);
      this.netsPaymentId = this.transaction.customFields["nexi_nets_payment_id"];
      return true;
    },
    // @todo use PaymentStatusEnum
    shouldDisplayRefundField() {
      return this.paymentDetails.refundedAmount > 0;
    },

    shouldDisplayCancelBtn() {
      return this.paymentDetails.status === "reserved";
    },

    shouldDisplayRefundBtn() {
      return this.paymentDetails.remainingRefund > 0;
    },

    shouldDisplayChargeBtn() {
      return this.paymentDetails.remainingCharge > 0;
    },

    isNewPayment() {
      return this.paymentDetails.status === "new";
    },

    shouldDisplayButtonsSection() {
      if (this.isNewPayment) {
        return;
      }
      return (
        this.shouldDisplayCancelBtn || this.shouldDisplayRefundBtn || this.shouldDisplayChargeBtn
      );
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
      try {
        this.paymentDetails = await this.nexiNetsPaymentDetailService.getPaymentDetails(orderId);
        this.setPaymentStatusVariant();
      } catch (error) {
        this.hasFetchError = true;
        this.variant = "danger";
        console.error(
          `Error while fetching NexiNets payment details for paymentID: ${this.netsPaymentId}`,
          error,
        );
      } finally {
        this.isLoading = false;
      }
    },

    toggleCaptureModal() {
      this.isCaptureModalVisible = !this.isCaptureModalVisible;
    },

    addMaxCharge() {
      this.maxChargeAmount = this.paymentDetails.remainingCharge;
    },
  },
});
