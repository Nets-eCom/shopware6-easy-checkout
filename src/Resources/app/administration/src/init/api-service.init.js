import NetsCheckoutApiPaymentService
    from '../api/nets-checkout-api-payment-service';

const { Application } = Shopware;

Application.addServiceProvider('NetsCheckoutApiPaymentService', (container) => {
    const initContainer = Application.getContainer('init');
    return new NetsCheckoutApiPaymentService(initContainer.httpClient, container.loginService);
});
