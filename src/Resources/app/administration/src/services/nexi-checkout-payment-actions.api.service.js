const ApiService = Shopware.Classes.ApiService;

class NexiCheckoutApiPaymentService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '');
        this.name = 'nexiCheckoutPaymentActionsService';
    }

    charge(orderId, { amount, items }) {
        return this.httpClient
            .put(
                `order/${orderId}/nexi-payment-charge`,
                { amount: amount.toString(), items },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(ApiService.handleResponse.bind(this));
    }

    refund(orderId, { amount, ...charges }) {
        return this.httpClient
            .put(
                `order/${orderId}/nexi-payment-refund`,
                {
                    amount: amount.toString(),
                    charges
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(ApiService.handleResponse.bind(this));
    }

    cancel(orderId) {
        return this.httpClient
            .put(
                `order/${orderId}/nexi-payment-cancel`,
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(ApiService.handleResponse.bind(this));
    }
}

Shopware.Service().register('nexiCheckoutPaymentActionsService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new NexiCheckoutApiPaymentService(initContainer.httpClient, container.loginService);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default NexiCheckoutApiPaymentService;
