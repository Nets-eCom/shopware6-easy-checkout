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
      variant: "neutral",
      hasFetchError: false,
      isItemsListVisible: false,
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
    isItemsListVisible() {
      this.resetAmount();
    },
  },

  methods: {
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
        this.createNotificationError({
          title: this.$tc("nexinets-payment-component.notification.fetch-error-title"),
          message: this.$tc("nexinets-payment-component.notification.fetch-error-message"),
        });
      } finally {
        this.isLoading = false;
      }
    },

    async handleCharge() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.charge(this.order.id, this.chargeAmount);
        await this.fetchPaymentDetails(this.orderId);
        this.createNotificationSuccess({
          title: this.$tc("nexinets-payment-component.notification.charge-title"),
          message: this.$tc("nexinets-payment-component.notification.charge-message"),
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
          title: this.$tc("nexinets-payment-component.notification.refund-title"),
          message: this.$tc("nexinets-payment-component.notification.refund-message"),
        });
        this.closeRefundModal();
        this.reloadKey++;
      } catch (error) {
        this.handleActionError(error);
      } finally {
        this.isLoading = false;
      }
    },

    async handleCancel() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.cancel(this.order.id);
        await this.fetchPaymentDetails(this.orderId);
        this.closeCancelModal();
        this.reloadKey++;
      } catch (error) {
        this.handleActionError(error);
      } finally {
        this.isLoading = false;
      }
    },

    handleActionError(error) {
      console.error("index.js error:", error);
      this.createNotificationError({
        title: this.$tc("nexinets-payment-component.notification.action-error-title"),
        message: this.$tc("nexinets-payment-component.notification.action-error-message"),
      });
    },

    setPaymentStatusVariant() {
      const status = this.paymentDetails.status;
      const variantMapping = {
        charged: "success",
        partially_charged: "success",
        pending_refund: "warning",
        refunded: "success",
        partially_refunded: "success",
        cancelled: "danger",
      };
      this.variant = variantMapping[status] || "neutral";
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

    toggleCancelModal() {
      this.isCancelModalVisible = !this.isCancelModalVisible;
    },

    closeChargeModal() {
      this.isChargeModalVisible = false;
    },

    closeRefundModal() {
      this.isRefundModalVisible = false;
    },

    closeCancelModal() {
      this.isCancelModalVisible = false;
    },

    onClickMaxCharge() {
      if (!this.isItemsListVisible) {
        this.setChargeAmount(this.paymentDetails.remainingChargeAmount);
      }
    },

    onClickMaxRefund() {
      if (!this.isItemsListVisible) {
        this.setRefundAmount(this.paymentDetails.remainingRefundAmount);
      }
    },

    resetAmount() {
      this.chargeAmount = 0;
      this.refundAmount = 0;
    },
  },
});
