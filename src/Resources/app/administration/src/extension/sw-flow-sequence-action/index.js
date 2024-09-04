import { ACTION, GROUP } from '../../constant/nexinets-charge-action.constant';

const { Component } = Shopware;

Component.override('sw-flow-sequence-action', {
    methods: {
        getActionDescriptions(sequence) {
            if(sequence.actionName === ACTION.NEXINETS_CHARGE){
                return this.$tc('nexinets-charge-action.description')
            }

            return this.$super('getActionDescriptions', sequence)
        },

        getActionTitle(actionName) {
            if (actionName === ACTION.NEXINETS_CHARGE) {
                return {
                    value: actionName,
                    icon: 'regular-shopping-bag-alt',
                    label: this.$tc('nexinets-charge-action.title'),
                    group: GROUP,
                }
            }

            return this.$super('getActionTitle', actionName);
        },

        openDynamicModal(value) {
            if (!value) {
                return;
            }

            const actionName = ACTION.NEXINETS_CHARGE;

            if (value === actionName) {
                this.selectedAction = actionName;
                this.onSaveActionSuccess({});

                return;
            }

            return this.$super('openDynamicModal', value);
        },
    },
});