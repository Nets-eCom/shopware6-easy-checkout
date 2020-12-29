const ApiService = Shopware.Classes.ApiService;

class NetsCheckoutApiPaymentService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nets') {
        super(httpClient, loginService, apiEndpoint);
    }

    captureTransaction(currentOrder) {
        const transaction = currentOrder.transactions.first();
        const orderId = currentOrder.id;
        const route = '/nets/transaction/charge';
        return this.httpClient
            .post(
                route,
                {
                    params: {transaction, orderId}
                },
                {
                    headers: this.getBasicHeaders()
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    refundTransaction(currentOrder) {
        const transaction = currentOrder.transactions.first();
        const orderId = currentOrder.id;
        const route = '/nets/transaction/refund';
        return this.httpClient
            .post(
                route,
                {
                    params: {transaction, orderId}
                },
                {
                    headers: this.getBasicHeaders()
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getSummaryAmounts(currentOrder) {
        const transaction = currentOrder.transactions.first();

        const route = '/nets/transaction/summary';

        return this.httpClient
            .post(
                route,
                {
                    params: {transaction}
                },

                {
                    headers: this.getBasicHeaders()
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

}
export default NetsCheckoutApiPaymentService;
