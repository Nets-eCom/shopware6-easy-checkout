const ApiService = Shopware.Classes.ApiService;

class NexiCheckoutApiPaymentService extends ApiService {
  constructor(httpClient, loginService) {
    super(httpClient, loginService, '');
    this.name = 'nexiPaymentDetailService';
  }

  async getPaymentDetails(orderId) {
    return await this.httpClient.get(
        `order/${orderId}/nexinets-payment-detail`,
        {
          headers: this.getBasicHeaders(),
        },
    ).then(ApiService.handleResponse.bind(this));
  }
}

Shopware.Service().register('nexiPaymentDetailService', (container) => {
  const initContainer = Shopware.Application.getContainer('init');
  return new NexiCheckoutApiPaymentService(initContainer.httpClient, container.loginService);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default NexiCheckoutApiPaymentService;