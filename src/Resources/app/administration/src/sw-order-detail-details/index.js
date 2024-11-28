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
      hasFetchError: false,
      isItemsListVisible: false,
      paymentDetails: {},
      charge: { amount: 0.0, items: [] },
      refund: { amount: 0.0, items: [] },
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

    statusVariant() {
      if (this.hasFetchError) {
        return "danger";
      }

      const status = this.paymentDetails.status;
      const variantMapping = {
        charged: "success",
        partially_charged: "success",
        pending_refund: "warning",
        refunded: "success",
        partially_refunded: "success",
        cancelled: "danger",
      };

      return variantMapping[status] || "neutral";
    },

    chargeAmountError() {
      if (this.charge.amount > this.paymentDetails.remainingChargeAmount) {
        return { code: "error-charge-max-amount" };
      }

      // if (this.charge.amount <= 0) {
      //   return { code: "error-charge-min-amount" };
      // }
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
      } catch (error) {
        const netsPaymentId = this.transaction.customFields["nexi_nets_payment_id"];
        this.hasFetchError = true;
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
        console.log(this.paymentDetails);
      }
    },

    async handleCharge() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.charge(this.order.id, this.charge);
        this.createNotificationSuccess({
          title: this.$tc("nexinets-payment-component.notification.charge-title"),
          message: this.$tc("nexinets-payment-component.notification.charge-message"),
        });
        this.closeChargeModal();
        await this.reloadComponent();
      } catch (error) {
        this.handleActionError(error);
      } finally {
        this.isLoading = false;
      }
    },

    async handleRefund() {
      this.isLoading = true;
      try {
        await this.nexiNetsPaymentActionsService.refund(this.order.id, this.refund);
        this.createNotificationSuccess({
          title: this.$tc("nexinets-payment-component.notification.refund-title"),
          message: this.$tc("nexinets-payment-component.notification.refund-message"),
        });
        this.closeRefundModal();
        await this.reloadComponent();
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
        this.closeCancelModal();
        await this.reloadComponent();
      } catch (error) {
        this.handleActionError(error);
      } finally {
        this.isLoading = false;
      }
    },

    async reloadComponent() {
      await this.fetchPaymentDetails(this.orderId);
      this.reloadKey++;
    },

    handleActionError(error) {
      console.error("index.js error:", error);
      this.createNotificationError({
        title: this.$tc("nexinets-payment-component.notification.action-error-title"),
        message: this.$tc("nexinets-payment-component.notification.action-error-message"),
      });
    },

    setChargeAmount(amount) {
      this.charge.amount = amount;
    },

    updateChargeItem({ reference, grossTotalAmount, quantity }, quantityToCharge) {
      const index = this.charge.items.findIndex((existing) => existing.reference === reference);
      const amount = (grossTotalAmount / quantity) * quantityToCharge;

      if (index === -1) {
        this.charge.items.push({ reference, quantity: quantityToCharge, amount });
        this.calculateChargeAmount();

        return;
      }

      if (quantityToCharge === null || quantityToCharge === 0) {
        this.charge.items.splice(index, 1);
        this.calculateChargeAmount();

        return;
      }

      this.charge.items[index] = { reference, quantity: quantityToCharge, amount };
      this.calculateChargeAmount();
    },

    calculateChargeAmount() {
      const total = this.charge.items.reduce((total, item) => {
        return total + item.amount;
      }, 0.0);

      this.setChargeAmount(parseFloat(total.toFixed(2)));
    },

    setRefundAmount(amount) {
      this.refund.amount = amount;
    },

    toggleChargeModal() {
      this.isChargeModalVisible = !this.isChargeModalVisible;
      this.resetAmount();
      this.isItemsListVisible = false;
    },

    toggleRefundModal() {
      this.isRefundModalVisible = !this.isRefundModalVisible;
      this.resetAmount();
      this.isItemsListVisible = false;
    },

    toggleCancelModal() {
      this.isCancelModalVisible = !this.isCancelModalVisible;
      this.resetAmount();
      this.isItemsListVisible = false;
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
      this.charge = { amount: 0.0, items: [] };
      this.refund = { amount: 0.0, items: [] };
    },
  },
});
