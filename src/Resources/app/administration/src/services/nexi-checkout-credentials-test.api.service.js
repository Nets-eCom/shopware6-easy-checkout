const ApiService = Shopware.Classes.ApiService;

class NexiCheckoutCredentialsTestService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '_action');
        this.name = 'nexiCheckoutCredentialsTestService';
    }

    showCheckoutUrlInfo() {
        return this.httpClient
            .get(
                `nexicheckout/configuration/checkout-url-info`,
                { headers: this.getBasicHeaders() },
            )
            .then(ApiService.handleResponse.bind(this));
    }

    testCredentials({ secretKey, liveMode, salesChannelId }) {
        return this.httpClient
            .post(
                `nexicheckout/test-api-credentials`,
                { secretKey, liveMode, salesChannelId },
                { headers: this.getBasicHeaders() },
            )
            .then(ApiService.handleResponse.bind(this));
    }
}

Shopware.Service().register('nexiCheckoutCredentialsTestService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new NexiCheckoutCredentialsTestService(initContainer.httpClient, container.loginService);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default NexiCheckoutCredentialsTestService;

