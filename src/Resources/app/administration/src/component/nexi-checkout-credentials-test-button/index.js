import template from './nexi-checkout-credentials-test-button.html.twig';
import './nexi-checkout-credentials-test-button.scss';

const { Mixin } = Shopware;

Shopware.Component.register('nexi-checkout-credentials-test-button', {
    template,

    inject: ['nexiCheckoutCredentialsTestService'],

    mixins: [Mixin.getByName('notification')],

    created () {
        this.fetchShowCheckoutUrlInfo()
    },

    data() {
        return {
            isTestingCredentials: false,
            showCheckoutUrlInfo:false
        };
    },

    computed: {
        configRoot() {
            let parent = this.$parent;
            while (parent && parent.$options.name !== 'sw-system-config') {
                parent = parent.$parent;
            }
            return parent;
        },

        currentSalesChannelId() {
            return this.configRoot?.currentSalesChannelId || 'null';
        },

        liveMode: {
            get() {
                const config = this.configRoot?.actualConfigData?.[this.currentSalesChannelId];
                return config ? !!config['NetsNexiCheckout.config.liveMode'] : false;
            },
            set(value) {
                if (!this.configRoot) return;

                if (!this.configRoot.actualConfigData[this.currentSalesChannelId]) {
                    this.configRoot.actualConfigData[this.currentSalesChannelId] = {};
                }

                this.configRoot.actualConfigData[this.currentSalesChannelId]['NetsNexiCheckout.config.liveMode'] = value;
            }
        }
    },

    methods: {
        async fetchShowCheckoutUrlInfo() {
            const result = await this.nexiCheckoutCredentialsTestService.showCheckoutUrlInfo();

            this.showCheckoutUrlInfo = result.showMessage || false;
        },

        async testCredentials() {
            this.isTestingCredentials = true;

            const config = this.configRoot?.actualConfigData?.[this.currentSalesChannelId] || {};
            const isLive = !!config['NetsNexiCheckout.config.liveMode'];

            const credentials = {
                liveMode: isLive,
                salesChannelId: this.configRoot?.currentSalesChannelId || null,
                secretKey: isLive
                    ? (config['NetsNexiCheckout.config.liveSecretKey'] || '')
                    : (config['NetsNexiCheckout.config.testSecretKey'] || '')
            };

            try {
                const result = await this.nexiCheckoutCredentialsTestService.testCredentials(credentials);
                if (result.valid) {
                    this.createNotificationSuccess({ title: 'Success', message: result.message });
                } else {
                    this.createNotificationError({ title: 'Error', message: result.message });
                }
            } catch (e) {
                this.createNotificationError({
                    title: 'System Error',
                    message: this.$tc('nexi-checkout-credentials-test.error')
                });
            } finally {
                this.isTestingCredentials = false;
            }
        }
    }
});