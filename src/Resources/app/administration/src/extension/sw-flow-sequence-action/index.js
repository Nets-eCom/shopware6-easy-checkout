import { ACTION, GROUP } from '../../constant/nexi-checkout-charge-action.constant';

const { Component } = Shopware;

Component.override('sw-flow-sequence-action', {
    methods: {
        getActionDescriptions(sequence) {
            if(sequence.actionName === ACTION.NEXI_CHECKOUT_CHARGE){
                return this.$tc('nexicheckout-charge-action.description')
            }

            return this.$super('getActionDescriptions', sequence)
        },

        getActionTitle(actionName) {
            if (actionName === ACTION.NEXI_CHECKOUT_CHARGE) {
                return {
                    value: actionName,
                    icon: 'regular-shopping-bag-alt',
                    label: this.$tc('nexicheckout-charge-action.title'),
                    group: GROUP,
                }
            }

            return this.$super('getActionTitle', actionName);
        },

        openDynamicModal(value) {
            if (!value) {
                return;
            }

            const actionName = ACTION.NEXI_CHECKOUT_CHARGE;

            if (value === actionName) {
                this.selectedAction = actionName;
                this.onSaveActionSuccess({});

                return;
            }

            return this.$super('openDynamicModal', value);
        },
    },
});