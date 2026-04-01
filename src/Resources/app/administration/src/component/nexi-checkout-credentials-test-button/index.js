import template from './nexi-checkout-credentials-test-button.html.twig';
import './nexi-checkout-credentials-test-button.scss';

const { Mixin } = Shopware;

Shopware.Component.register('nexi-checkout-credentials-test-button', {
    template,
    inject: ['nexiCheckoutCredentialsTestService'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isTestingCredentials: false,
            showCheckoutUrlInfo: false,
            configParent: null
        };
    },

    mounted() {
        this.findAndInterceptSave();
    },

    computed: {
        currentSalesChannelId() {
            return this.configParent?.currentSalesChannelId || 'null';
        },

        liveMode: {
            get() {
                const config = this.configParent?.actualConfigData?.[this.currentSalesChannelId];
                return config ? !!config['NetsNexiCheckout.config.liveMode'] : false;
            },
            set(value) {
                if (!this.configParent) {
                    return;
                }

                Shopware.Utils.object.set(
                  this.configParent.actualConfigData[this.currentSalesChannelId],
                  'NetsNexiCheckout.config.liveMode',
                  value
                );

                this.$forceUpdate();
            }
        }
    },

    methods: {
        findAndInterceptSave() {
            let parent = this.$parent;
            while (parent) {
                if (parent.saveAll || parent.$options.name === 'sw-system-config' || parent.$options.name === 'SwSystemConfig') {
                    this.configParent = parent;
                    this.interceptSaveMethod(parent);
                    return;
                }
                parent = parent.$parent;
            }
        },

        interceptSaveMethod(parent) {
            if (parent._nexiIntercepted) {
                return;
            }

            const originalSaveAll = parent.saveAll;

            parent.saveAll = async (...args) => {
                try {
                    const result = await originalSaveAll.apply(parent, args);
                    this.testCredentials();

                    return result;
                } catch (error) {
                    throw error;
                }
            };

            parent._nexiIntercepted = true;
        },

        async testCredentials() {
            if (this.isTestingCredentials) return;

            const parent = this.configParent;
            const config = parent?.actualConfigData?.[this.currentSalesChannelId] || {};
            const isLive = !!config['NetsNexiCheckout.config.liveMode'];

            const credentials = {
                liveMode: isLive,
                salesChannelId: this.currentSalesChannelId,
                secretKey: isLive
                  ? (config['NetsNexiCheckout.config.liveSecretKey'] || '')
                  : (config['NetsNexiCheckout.config.testSecretKey'] || '')
            };

            this.isTestingCredentials = true;

            try {
                const result = await this.nexiCheckoutCredentialsTestService.testCredentials(credentials);
                if (result.valid) {
                    this.createNotificationSuccess({
                        title: 'Nexi Checkout API',
                        message: result.message
                    });
                } else {
                    this.createNotificationError({
                        title: 'Nexi Checkout API Error',
                        message: result.message
                    });
                }
            } catch (e) {
                this.createNotificationError({
                    title: 'System Error',
                    message: this.$tc('nexi-checkout-credentials-test.error')
                });
            } finally {
                this.isTestingCredentials = false;
            }
        },

        async fetchShowCheckoutUrlInfo() {
            try {
                const result = await this.nexiCheckoutCredentialsTestService.showCheckoutUrlInfo();
                this.showCheckoutUrlInfo = result.showMessage || false;
            } catch (e) {}
        }
    }
});
