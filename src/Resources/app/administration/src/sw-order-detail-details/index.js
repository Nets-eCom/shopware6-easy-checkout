const { Mixin } = Shopware;
import template from "./sw-order-detail-details.html.twig";
import "./style.scss";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: ["nexiPaymentDetailService", "nexiPaymentActionsService"],
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
      charge: { amount: 0.00, items: [] },
      refund: { amount: 0.00 },
      reloadKey: 0,
    };
  },
  created() {
    if (this.isNexiPayment) {
      this.fetchPaymentDetails(this.orderId);
    }
  },
  computed: {
    isNexiPayment() {
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

      return `nexi-payment-component.payment-details.status.${status}`;
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
    },

    refundAmountError() {
      if (this.refund.amount > this.paymentDetails.remainingRefundAmount) {
        return { code: "error-refund-max-amount" };
      }
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
      this.paymentDetails = await this.nexiPaymentDetailService.getPaymentDetails(orderId)
          .catch(({response}) => {
            const errors = response.data.errors;
            const netsPaymentId = this.transaction.customFields["nexi_nets_payment_id"];
            this.hasFetchError = true;
            console.error(
                `Error while fetching Nexi payment details for paymentID: ${netsPaymentId}`,
                errors,
            );
            this.handleErrors(errors);
          })
          .finally(() => {
            this.isLoading = false
          });
    },

    async handleCharge() {
      this.isLoading = true;
      await this.nexiPaymentActionsService.charge(this.order.id, this.charge)
          .then(() => {
            this.createNotificationSuccess({
              title: this.$tc("nexi-payment-component.notification.charge-title"),
              message: this.$tc("nexi-payment-component.notification.charge-message"),
            });

            this.closeChargeModal();
            this.reloadComponent();
          })
          .catch(({response}) => {
            this.handleErrors(response.data);
          }).finally(() => this.isloading = false)
    },

    async handleRefund() {
      this.isLoading = true;
      await this.nexiPaymentActionsService.refund(this.order.id, this.refund)
          .then(() => {
            this.createNotificationSuccess({
              title: this.$tc("nexi-payment-component.notification.refund-title"),
              message: this.$tc("nexi-payment-component.notification.refund-message"),
            });
            this.closeRefundModal();
            this.reloadComponent();
          })
          .catch(({response}) => {
            this.handleErrors(response.data);
          })
          .finally(() => this.isLoading = false);
    },

    async handleCancel() {
      this.isLoading = true;
      await this.nexiPaymentActionsService.cancel(this.order.id)
          .then(() => {
            this.closeCancelModal();
            this.reloadComponent();
          })
          .catch(({response}) => {
            this.handleErrors(response);
          })
          .finally(() => this.isLoading = false);
    },

    async reloadComponent() {
      await this.fetchPaymentDetails(this.orderId);
      this.reloadKey++;
    },

    handleErrors({errors}) {
      console.error("index.js error:", errors);
      if (!errors) {
        this.createNotificationError({
          title: this.$t("nexi-payment-component.notification.action-error-title"),
          message: this.$t("nexi-payment-component.notification.action-error-message"),
        });

        return;
      }

      const error = errors[0];

      this.createNotificationError({
        title: this.$t(`nexi-payment-component.notification.${error.code}`),
        message: this.$t(`nexi-payment-component.api.errors.${error.code}`, error.meta.parameters),
      })
    },

  updateRefundItem({ chargeId, reference, grossTotalAmount, quantity, ...rest }, quantityToRefund) {
      const amount = (grossTotalAmount / quantity) * quantityToRefund;

      if (!this.refund[chargeId]) {
        this.refund[chargeId] = {amount: 0.0, items: []};
      }

      const index = this.refund[chargeId].items.findIndex(existing => existing.reference === reference);

      if (index === -1) {
        this.refund[chargeId].items.push({reference, quantity: quantityToRefund, amount, ...rest});
        this.refund[chargeId].amount = this.refund[chargeId].items.reduce(
            (total, { amount }) => total + amount,
            0
        );
        this.calculateRefundAmount();

        return;
      }

      if (quantityToRefund === null || quantityToRefund === 0) {
        this.refund[chargeId].items.splice(index, 1);

        if (!this.refund[chargeId].items.length > 0) {
          delete this.refund[chargeId];
        }
        this.calculateRefundAmount();

        return;
      }

      this.refund[chargeId].items[index] = { reference, quantity: quantityToRefund, amount, ...rest };
      this.refund[chargeId].amount = this.refund[chargeId].items.reduce(
          (total, { amount }) => total + amount,
          0
      );

      this.calculateRefundAmount();
    },

    calculateRefundAmount() {
      const total = Object.keys(this.refund)
          .reduce((sum, key) => {
            const value = this.refund[key];

            if (!(value instanceof Array)) {
              sum += value.amount || 0;
            }

            return sum;
          }, 0.0);

      this.setRefundAmount(parseFloat(total.toFixed(2)));
    },

    setChargeAmount(amount) {
      this.charge.amount = amount;
    },

    updateChargeItem({ reference, grossTotalAmount, quantity }, quantityToCharge) {
      const index = this.charge.items.findIndex(existing => existing.reference === reference);
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
      this.charge = { amount: 0.00, items: [] };
      this.refund = { amount: 0.00 };
    },
  },
});
