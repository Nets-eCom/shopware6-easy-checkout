import template from "./sw-order-detail-details.html.twig";
import "./style.scss";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: ["nexiNetsPaymentDetailService", "nexiNetsPaymentActionsService"],
  mixins: [],
  data() {
    return {
      isLoading: false,
      disabled: true,
      isCaptureModalVisible: false,
      isRefundModalVisible: false,
      variant: "info",
      hasFetchError: false,
      paymentDetails: {},
      chargeAmount: 0,
      refundAmount: 0,
    };
  },
  created() {
    if (this.isNexiNetsPayment) {
      this.fetchPaymentDetails(this.orderId);
    }
  },
  computed: {
    isNexiNetsPayment() {
      return !!this.transaction?.customFields?.hasOwnProperty("nexi_nets_payment_id");
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
        const netsPaymentId = this.transaction.customFields["nexi_nets_payment_id"];
        this.hasFetchError = true;
        this.variant = "danger";
        console.error(
            `Error while fetching NexiNets payment details for paymentID: ${netsPaymentId}`,
            error,
        );
      } finally {
        this.isLoading = false;
      }
    },

    handleCharge() {
      this.isLoading = true;
      try {
        this.nexiNetsPaymentActionsService.charge(this.order.id, this.chargeAmount);
      } catch (error) {
        console.error("index.js error:", error);
      } finally {
        window.location.reload();
        this.isLoading = false;
      }
    },

    handleRefund() {
      // Implement refund handling logic here
      console.log(this.refundAmount);
    },

    toggleCaptureModal() {
      this.isCaptureModalVisible = !this.isCaptureModalVisible;
    },

    toggleRefundModal() {
      this.isRefundModalVisible = !this.isRefundModalVisible;
    },

    addMaxCharge() {
      this.chargeAmount = this.paymentDetails.remainingCharge;
    },

    addMaxRefund() {
      this.refundAmount = this.paymentDetails.remainingRefund;
    },
  },
});
