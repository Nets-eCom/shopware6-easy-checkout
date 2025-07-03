const ApiService = Shopware.Classes.ApiService;

class NetsCheckoutApiPaymentService extends ApiService {
  constructor(httpClient, loginService, apiEndpoint = "nets") {
    super(httpClient, loginService, apiEndpoint);
  }

  captureTransaction(orderId, paymentId, amount) {
    const route = "/nets/transaction/charge";
    return this.httpClient
      .post(
        route,
        {
          params: { orderId, paymentId, amount },
        },
        {
          headers: this.getBasicHeaders(),
        },
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }

  refundTransaction(orderId, paymentId, amount) {
    const route = "/nets/transaction/refund";
    return this.httpClient
      .post(
        route,
        {
          params: { orderId, paymentId, amount },
        },
        {
          headers: this.getBasicHeaders(),
        },
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }

  getSummaryAmounts(currentOrder) {
    const transaction = currentOrder.transactions.first();

    const route = "/nets/transaction/summary";

    return this.httpClient
      .post(
        route,
        {
          params: { transaction },
        },
        {
          headers: this.getBasicHeaders(),
        },
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }
}
export default NetsCheckoutApiPaymentService;
