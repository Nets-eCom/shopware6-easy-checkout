const { Mixin } = Shopware;
import template from "./sw-order-detail-details.html.twig";
import "./style.scss";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: ["nexiNetsPaymentDetailService", "nexiNetsPaymentActionsService"],
  mixins: [Mixin.getByName("notification")],
  data() {
    return {
      isLoading: false,
      disabled: true,
      isChargeModalVisible: false,
      isRefundModalVisible: false,
      isCancelModalVisible: false,
      variant: "info",
      hasFetchError: false,
      toggleItemsList: false,
      paymentDetails: {},
      chargeAmount: 0,
      refundAmount: 0,
      reloadKey: 0,
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
      return this.paymentDetails.remainingRefundAmount > 0;
    },

    shouldDisplayChargeBtn() {
      return this.paymentDetails.remainingChargeAmount > 0;
    },

    isNewPayment() {
      return this.paymentDetails.status === "new";
    },

    shouldDisplayButtonsSection() {
      if (this.isNewPayment || this.paymentDetails.status === "pending_refund") {
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
        this.handleFetchError(error);
      } finally {
        this.isLoading = false;
        console.log(this.paymentDetails);
      }
    },

    async handleCharge() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.charge(this.order.id, this.chargeAmount);
        await this.fetchPaymentDetails(this.orderId);
        this.createNotificationSuccess({
          title: "NexiNets - Capture has been initiated",
          message: `You have successfully initiated a capture of ${this.order.currency.symbol}${this.chargeAmount}.`,
        });
        this.closeChargeModal();
        this.reloadKey++;
      } catch (error) {
        this.handleActionError(error);
      } finally {
        this.isLoading = false;
      }
    },

    async handleRefund() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.refund(this.order.id, this.refundAmount);
        await this.fetchPaymentDetails(this.orderId);
        this.createNotificationSuccess({
          title: "NexiNets - The refund has been initiated",
          message:
            "It can take up to 3 bank days before refunds are available on receivers account.",
        });
        this.closeRefundModal();
        this.reloadKey++;
      } catch (error) {
        this.handleActionError(error);
      } finally {
        this.isLoading = false;
      }
    },

    handleFetchError(error) {
      const netsPaymentId = this.transaction.customFields["nexi_nets_payment_id"];
      this.hasFetchError = true;
      this.variant = "danger";
      console.error(
        `Error while fetching NexiNets payment details for paymentID: ${netsPaymentId}`,
        error,
      );
      this.createNotificationError({
        title: "NexiNets - Fetching Payment Details failed",
        message: "See the logs for more information.",
      });
    },

    handleActionError(error) {
      console.error("index.js error:", error);
      this.createNotificationError({
        title: "NexiNets - Action failed",
        message: "An error occurred while processing the action. Please check the logs.",
      });
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

    toggleChargeModal() {
      this.isChargeModalVisible = !this.isChargeModalVisible;
    },

    toggleRefundModal() {
      this.isRefundModalVisible = !this.isRefundModalVisible;
    },

    closeChargeModal() {
      this.isChargeModalVisible = false;
    },

    closeRefundModal() {
      this.isRefundModalVisible = false;
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
