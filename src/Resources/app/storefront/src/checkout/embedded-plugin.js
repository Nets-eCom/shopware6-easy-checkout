import Plugin from 'src/plugin-system/plugin.class';

export default class EmbeddedPlugin extends Plugin {

    init() {
        this._checkout = new Dibs.Checkout(this.options);

        this._registerEvents();
    }

    _registerEvents() {
        this._checkout.on('payment-completed', this._onCompletedPayment.bind(this));
    }

    async _onCompletedPayment(response) {
        const fetchResponse = await fetch(this.options.placeOrderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                paymentId: response.paymentId,
                _csrf_token: this.options.csrfToken,
                tos: "on"
            }),
        });

        window.location.replace(fetchResponse.url);
    }
}
