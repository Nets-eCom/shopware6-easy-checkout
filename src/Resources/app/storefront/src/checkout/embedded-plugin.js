import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service.js';

export default class EmbeddedPlugin extends Plugin {

    init() {
        this._checkout = new Dibs.Checkout(this.options);
        this.client = new HttpClient();

        this._registerEvents();
    }

    _registerEvents() {
        this._checkout.on('payment-completed', this._onCompletedPayment.bind(this));
    }

    _onCompletedPayment(response) {
        const handlePaymentPromise = this._postPaymentData(response);

        handlePaymentPromise.then(
            successUrl => window.location.replace(successUrl),
            errorUrl => window.location.replace(errorUrl)
        );
    }

    _postPaymentData(response) {
        return new Promise((resolve, reject) => {
            const request = JSON.stringify({
                paymentId: response.paymentId,
            });

            this.client.post(
                this.options.handlePaymentUrl,
                request,
                (responseText, request) => {
                    if (request.status >= 400) {
                        const {errorUrl} = JSON.parse(responseText);
                        reject(errorUrl);
                    }

                    try {
                        const {redirectUrl} = JSON.parse(responseText);
                        resolve(redirectUrl);
                    } catch (error) {
                        reject(error);
                    }
                }
            );
        });
    }
}
