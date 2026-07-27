const ApiService = Shopware.Classes.ApiService;

class NexiCheckoutPaymentMethodsService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '_action');
        this.name = 'nexiCheckoutPaymentMethodsService';
    }

    getPaymentMethods(salesChannelId) {
        const params = salesChannelId ? { salesChannelId } : {};
        return this.httpClient
            .get(
                `nexicheckout/payment-methods`,
                { params, headers: this.getBasicHeaders() },
            )
            .then(ApiService.handleResponse.bind(this));
    }
}

Shopware.Service().register('nexiCheckoutPaymentMethodsService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new NexiCheckoutPaymentMethodsService(initContainer.httpClient, container.loginService);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default NexiCheckoutPaymentMethodsService;
