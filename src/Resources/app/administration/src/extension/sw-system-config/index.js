Shopware.Component.override('sw-system-config', {
    provide() {
        return {
            getSystemConfigAllConfigs: () => this.allConfigs,
            getSystemConfigCurrentSalesChannelId: () => this.currentSalesChannelId,
        };
    },
});
