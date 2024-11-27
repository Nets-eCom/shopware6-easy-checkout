const ApiService = Shopware.Classes.ApiService;

class NexiNetsCheckoutApiPaymentService extends ApiService {
  constructor(httpClient, loginService) {
    super(httpClient, loginService, "");
    this.name = "nexiNetsPaymentActionsService";
  }

  charge(orderId, { amount, items }) {
    return this.httpClient
      .put(
        `order/${orderId}/nexinets-payment-charge`,
        { amount, items },
        {
          headers: this.getBasicHeaders(),
        },
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }

  refund(orderId, { amount, items }) {
    return this.httpClient
      .put(
        `order/${orderId}/nexinets-payment-refund`,
        {
          amount,
        },
        {
          headers: this.getBasicHeaders(),
        },
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }

    cancel(orderId) {
      return this.httpClient
        .put(
          `order/${orderId}/nexinets-payment-cancel`,
          {
            headers: this.getBasicHeaders(),
          },
        )
        .then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

Shopware.Service().register("nexiNetsPaymentActionsService", (container) => {
  const initContainer = Shopware.Application.getContainer("init");
  return new NexiNetsCheckoutApiPaymentService(initContainer.httpClient, container.loginService);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default nexiNetsPaymentActionsService;
