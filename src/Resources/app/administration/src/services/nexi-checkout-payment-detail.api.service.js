const ApiService = Shopware.Classes.ApiService;

class NexiCheckoutApiPaymentService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '');
        this.name = 'nexiCheckoutPaymentDetailService';
    }

    async getPaymentDetails(orderId) {
        return await this.httpClient
            .get(`order/${orderId}/nexi-payment-detail`, {
                headers: this.getBasicHeaders(),
            })
            .then(ApiService.handleResponse.bind(this));
    }
}

Shopware.Service().register('nexiCheckoutPaymentDetailService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new NexiCheckoutApiPaymentService(
        initContainer.httpClient,
        container.loginService
    );
});

export default NexiCheckoutApiPaymentService;
