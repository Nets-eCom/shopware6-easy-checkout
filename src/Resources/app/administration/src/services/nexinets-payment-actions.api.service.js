const ApiService = Shopware.Classes.ApiService;

class NexiNetsCheckoutApiPaymentService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, "");
        this.name = "nexiNetsPaymentActionsService";
    }

    charge(orderId, chargeAmount) {
        return this.httpClient
            .put(
                `order/${orderId}/nexinets-payment-charge`,
                {
                    amount: chargeAmount,
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

Shopware.Service().register("nexiNetsPaymentActionsService", (container) => {
    const initContainer = Shopware.Application.getContainer("init");
    return new NexiNetsCheckoutApiPaymentService(initContainer.httpClient, container.loginService);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default nexiNetsPaymentActionsService;
