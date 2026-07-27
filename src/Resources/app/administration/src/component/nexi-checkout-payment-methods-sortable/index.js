import template from './nexi-checkout-payment-methods-sortable.html.twig';
import './nexi-checkout-payment-methods-sortable.scss';

const { Component } = Shopware;

Component.register('nexi-checkout-payment-methods-sortable', {
    template,

    inject: ['nexiCheckoutPaymentMethodsService'],

    data() {
        return {
            configParent: null,
            availableMethods: [],
            sortedMethods: [],
            isLoading: false,
            dragSrcIndex: null,
        };
    },

    mounted() {
        this.findConfigParent();
        this.loadPaymentMethods();
    },

    watch: {
        payTypeSplitting(value) {
            if (value && this.availableMethods.length === 0) {
                this.loadPaymentMethods();
            }
        },
    },

    computed: {
        currentSalesChannelId() {
            return this.configParent?.currentSalesChannelId || null;
        },

        payTypeSplitting: {
            get() {
                const config = this.configParent?.actualConfigData?.[this.currentSalesChannelId];
                return config ? !!config['NetsNexiCheckout.config.payTypeSplitting'] : false;
            },
            set(value) {
                if (!this.configParent?.actualConfigData) {
                    return;
                }
                if (!this.configParent.actualConfigData[this.currentSalesChannelId]) {
                    this.configParent.actualConfigData[this.currentSalesChannelId] = {};
                }
                this.configParent.actualConfigData[this.currentSalesChannelId]['NetsNexiCheckout.config.payTypeSplitting'] = value;
                this.$forceUpdate();
            },
        },
    },

    methods: {
        findConfigParent() {
            let parent = this.$parent;
            while (parent) {
                if (parent.$options.name === 'sw-system-config' || parent.$options.name === 'SwSystemConfig') {
                    this.configParent = parent;
                    break;
                }
                parent = parent.$parent;
            }
        },

        getSavedMethods() {
            const config = this.configParent?.actualConfigData?.[this.currentSalesChannelId];
            const raw = config?.['NetsNexiCheckout.config.payTypeOptions'];
            if (!raw) {
                return [];
            }
            try {
                return JSON.parse(raw);
            } catch {
                return [];
            }
        },

        saveMethods() {
            if (!this.configParent?.actualConfigData) {
                return;
            }
            if (!this.configParent.actualConfigData[this.currentSalesChannelId]) {
                this.configParent.actualConfigData[this.currentSalesChannelId] = {};
            }
            this.configParent.actualConfigData[this.currentSalesChannelId]['NetsNexiCheckout.config.payTypeOptions'] = JSON.stringify(
                this.sortedMethods.map(m => ({
                    name: m.name,
                    paymentType: m.paymentType,
                    enabled: m.enabled,
                }))
            );
        },

        async loadPaymentMethods() {
            this.isLoading = true;
            try {
                const result = await this.nexiCheckoutPaymentMethodsService.getPaymentMethods(this.currentSalesChannelId);
                this.availableMethods = result.paymentMethods || [];
                this.mergeWithSaved();
            } catch {
                this.availableMethods = [];
            } finally {
                this.isLoading = false;
            }
        },

        mergeWithSaved() {
            const saved = this.getSavedMethods();
            const savedMap = {};
            saved.forEach(m => {
                savedMap[m.name] = m;
            });

            const merged = [];

            // Preserve saved order first
            saved.forEach(s => {
                const available = this.availableMethods.find(a => a.name === s.name);
                if (available) {
                    merged.push({ ...available, enabled: s.enabled });
                }
            });

            // Append newly available methods not yet in saved list
            this.availableMethods.forEach(a => {
                if (!savedMap[a.name]) {
                    merged.push({ ...a, enabled: true });
                }
            });

            this.sortedMethods = merged;
        },

        toggleMethod(method) {
            method.enabled = !method.enabled;
            this.saveMethods();
        },

        onDragStart(event, index) {
            this.dragSrcIndex = index;
            event.dataTransfer.effectAllowed = 'move';
        },

        onDragOver(event, index) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        },

        onDrop(event, targetIndex) {
            event.preventDefault();
            if (this.dragSrcIndex === null || this.dragSrcIndex === targetIndex) {
                return;
            }
            const moved = this.sortedMethods.splice(this.dragSrcIndex, 1)[0];
            this.sortedMethods.splice(targetIndex, 0, moved);
            this.dragSrcIndex = null;
            this.saveMethods();
        },

        onDragEnd() {
            this.dragSrcIndex = null;
        },
    },
});
