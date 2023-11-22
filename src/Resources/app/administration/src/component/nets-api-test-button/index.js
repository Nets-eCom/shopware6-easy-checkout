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
        pluginConfig() {
            let $parent = this.$parent;

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            return $parent.actualConfigData.null;
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;
			this.isUpdate = false; 
            this.netsApiTest.check(this.pluginConfig).then((res) => {
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
        }
    }
})
