const { PluginBaseClass } = window;
import HttpClient from 'src/service/http-client.service.js';
import DomAccess from 'src/helper/dom-access.helper';

export default class EmbeddedPlugin extends PluginBaseClass {

    static options = {
        /**
         * @type string
         */
        checkoutKey: '',

        /**
         * @type string
         */
        paymentId: '',

        /**
         * @type string
         */
        containerId: '',

        /**
         * @type string
         */
        handlePaymentUrl: '',

        /**
         * @type string
         */
        confirmOrderFormSelector: '#confirmOrderForm',

        /**
         * @type string
         */
        confirmOrderButtonSelector: 'button[type="submit"]',

        /**
         * @type {string|null}
         */
        targetPath: null
    }

    init() {
        this._confirmForm = DomAccess.querySelector(document, this.options.confirmOrderFormSelector);
        this._confirmOrderFromSubmit = DomAccess.querySelector(this._confirmForm, this.options.confirmOrderButtonSelector);

        this._tosCheckbox = DomAccess.querySelector(document, '.confirm-tos');
        this._client = new HttpClient();

        this._registerCheckout();
        this._hideConfirmOrderFormSubmit();
        this._hideTosCheckbox();
    }

    _registerCheckout() {
        this._checkout = new Dibs.Checkout({
            checkoutKey: this.options.checkoutKey,
            paymentId: this.options.paymentId,
            containerId: this.options.containerId,
            language: this.options.language
        });
        this._checkout.on('pay-initialized', this.onPaymentInitialized.bind(this));
        this._checkout.on('payment-completed', this.onPaymentCompleted.bind(this));
    }

    _hideConfirmOrderFormSubmit() {
        this._confirmOrderFromSubmit.style.display = 'none';
        this._confirmOrderFromSubmit.disabled = true;
    }

    _hideTosCheckbox() {
        this._tosCheckbox.style.display = 'none'
    }

    async onPaymentInitialized(paymentId) {
        if (this.options.targetPath !== null) {
            // If target path is set then payment was already initialized

            // @TODO set targetRoute to AccountEditOrderPage
            this._checkout.send('payment-order-finalized', true);

            return;
        }

        this._handlePayment(paymentId)
            .then((path) => {
                this._updateTargetPath(path);
                this._checkout.send('payment-order-finalized', true);
            })
            .catch((path) => {
                this._checkout.send('payment-order-finalized', false);
                this._updateTargetPath(path)
                this._redirectToTargetPath();
            });
    }

    onPaymentCompleted(response) {
        if (this.options.targetPath !== null) {
            return this._redirectToTargetPath();
        }

        this._handlePayment(response.paymentId)
            .then((path) => {
                this._updateTargetPath(path);
                this._redirectToTargetPath()
            })
            .catch((path) => {
                this._updateTargetPath(path)
                this._redirectToTargetPath();
            });
    }

    _updateTargetPath(targetPath) {
        this.options.targetPath = targetPath;
    }

    _redirectToTargetPath() {
        if (this.options.targetPath === null) {
            throw new Error("Cannot redirect to target path");
        }

        window.location.href = this.options.targetPath;
    }

    async _handlePayment(paymentId) {
        return new Promise((resolve, reject) => {
            this._client.post(
                this.options.handlePaymentUrl,
                JSON.stringify({
                    nexiPaymentId: paymentId,
                }),
                (responseText, request) => {
                    if (request.status >= 400) {
                        const {targetPath} = JSON.parse(responseText);
                        reject(targetPath);
                    }

                    const {targetPath} = JSON.parse(responseText);
                    resolve(targetPath);
                }
            );
        });
    }
}
