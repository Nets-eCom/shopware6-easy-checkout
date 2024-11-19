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
      isCancelModalVisible: false,
      variant: "info",
      hasFetchError: false,
      toggleItemsList: false,
      paymentDetails: {},
      chargeAmount: 0,
      refundAmount: 0,
    };
  },
  created() {
    if (this.isNexiNetsPayment) {
      this.fetchPaymentDetails(this.orderId);
      this.getOrderItems();
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
      return this.paymentDetails.remainingRefundAmount > 0;
    },

    shouldDisplayChargeBtn() {
      return this.paymentDetails.remainingChargeAmount > 0;
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

  watch: {
    toggleItemsList() {
      this.resetAmount();
    },
  },

  methods: {
    getOrderItems() {
      this.paymentDetails.orderItems = [
        { qty: "1", item: "Item A", subtotal: "25.20", qtyCharge: "2" },
        { qty: "3", item: "Item B", subtotal: "25.20", qtyCharge: "3" },
      ];
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

    async handleCharge() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.charge(this.order.id, this.chargeAmount);
        window.location.reload();
      } catch (error) {
        console.error("index.js error:", error);
      } finally {
        this.isLoading = false;
      }
    },

    async handleRefund() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.refund(this.order.id, this.refundAmount);
        window.location.reload();
      } catch (error) {
        console.error("index.js error:", error);
      } finally {
        this.isLoading = false;
      }
    },

    async handleCancel() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.cancel(this.order.id);
        window.location.reload();
      } catch (error) {
        console.error("index.js error:", error);
      } finally {
        this.isLoading = false;
      }
    },

    setChargeAmount(amount) {
      this.chargeAmount = amount;
    },

    setRefundAmount(amount) {
      this.refundAmount = amount;
    },

    toggleCaptureModal() {
      this.isCaptureModalVisible = !this.isCaptureModalVisible;
    },

    toggleRefundModal() {
      this.isRefundModalVisible = !this.isRefundModalVisible;
    },

    toggleCancelModal() {
      this.isCancelModalVisible = !this.isCancelModalVisible;
    },

    onClickMaxCharge() {
      if (!this.toggleItemsList) {
        this.setChargeAmount(this.paymentDetails.remainingChargeAmount);
      }
    },

    onClickMaxRefund() {
      if (!this.toggleItemsList) {
        this.setRefundAmount(this.paymentDetails.remainingRefundAmount);
      }
    },

    resetAmount() {
      this.chargeAmount = 0;
      this.refundAmount = 0;
    },
  },
});
