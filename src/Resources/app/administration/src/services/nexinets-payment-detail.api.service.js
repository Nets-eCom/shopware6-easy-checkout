const ApiService = Shopware.Classes.ApiService;

class NexiNetsCheckoutApiPaymentService extends ApiService {
  constructor(httpClient, loginService) {
    super(httpClient, loginService, '');
    this.name = 'nexiNetsPaymentDetailService';
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

Shopware.Service().register('nexiNetsPaymentDetailService', (container) => {
  const initContainer = Shopware.Application.getContainer('init');
  return new NexiNetsCheckoutApiPaymentService(initContainer.httpClient, container.loginService);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default NexiNetsCheckoutApiPaymentService;