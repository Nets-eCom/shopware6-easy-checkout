const { Component, Mixin } = Shopware;
import template from './nets-api-test-button.html.twig';

Component.register('nets-api-test-button', {
    template,

    props: ['label'],
    inject: ['netsApiTest'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
			isUpdate : true
        };
    },

    computed: {
        config() {
            const parent = this._findParentWithConfigData(this.$parent);

            if (parent === null) {
                return {};
            }

            return {
                ...parent.actualConfigData.null,
                ...parent.actualConfigData[parent.currentSalesChannelId]
            };
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;
			this.isUpdate = false; 
            this.netsApiTest.check(this.config).then((res) => {
                if (res.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('nets-api-test-button.title'),
                        message: this.$tc('nets-api-test-button.success')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('nets-api-test-button.title'),
                        message: this.$tc('nets-api-test-button.error')
                    });
                }

                this.isLoading = false;
            });
        },

        _findParentWithConfigData(parent) {
            if (parent === null) {
                return null;
            }

            if (parent.actualConfigData !== undefined && parent.currentSalesChannelId !== undefined) {
                return parent;
            }

            return this._findParentWithConfigData(parent.$parent);
        }
    }
})
