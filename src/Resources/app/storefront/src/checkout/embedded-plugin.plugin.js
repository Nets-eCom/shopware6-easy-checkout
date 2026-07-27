const { PluginBaseClass } = window;
import HttpClient from 'src/service/http-client.service.js';
import DomAccess from 'src/helper/dom-access.helper';

export default class EmbeddedPlugin extends PluginBaseClass {

    static options = {
        /** @type {string} */
        checkoutKey: '',

        /** @type {string|null} */
        paymentId: null,

        /** @type {string} */
        containerId: '',

        /** @type {string} */
        handlePaymentUrl: '',

        /** @type {string} */
        confirmOrderFormSelector: '#confirmOrderForm',

        /** @type {string} */
        confirmOrderButtonSelector: 'button[type="submit"]',

        /** @type {string|null} */
        targetPath: null,

        /** @type {boolean} */
        payTypeSplitting: false,

        /** @type {string|null} */
        createEmbeddedPaymentUrl: null,

        /** @type {Array<{value: string, label: string}>} */
        subselections: [],
    }

    init() {
        this._client = new HttpClient();

        if (this.options.payTypeSplitting) {
            this._initSplitMode();
            return;
        }

        this._confirmForm = DomAccess.querySelector(document, this.options.confirmOrderFormSelector);
        this._confirmOrderFromSubmit = DomAccess.querySelector(this._confirmForm, this.options.confirmOrderButtonSelector);
        this._tosCheckbox = DomAccess.querySelector(document, '.confirm-tos');

        this._registerCheckout();
        this._hideConfirmOrderFormSubmit();
        this._hideTosCheckbox();
    }

    // Split payment mode

    _initSplitMode() {
        this._activeSubselection = null;
        this._activeCheckout = null;
        this._loadingSubselection = null;

        const radios = document.querySelectorAll('input[name="nexi_subselection"]');
        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('input[name="paymentMethodId"]').forEach(r => { r.checked = false; });
                this._onSubselectionChange(radio.value);
            });
        });

        document.querySelectorAll('input[name="paymentMethodId"]').forEach(r => {
            r.addEventListener('change', () => {
                document.querySelectorAll('input[name="nexi_subselection"]').forEach(sub => { sub.checked = false; });
                this._collapseActiveMethod();
                this._activeSubselection = null;
                this._loadingSubselection = null;
            });
        });
    }

    async _onSubselectionChange(subselection) {
        if (subselection === this._activeSubselection || subselection === this._loadingSubselection) {
            return;
        }

        this._collapseActiveMethod();
        this._activeSubselection = null;
        this._loadingSubselection = subselection;

        const container = document.getElementById(`nexi-checkout-${subselection}`);
        if (!container) return;

        container.style.display = 'block';

        const loading = container.querySelector('.nexi-split-loading');
        const error = container.querySelector('.alert-danger');

        if (loading) loading.style.display = 'flex';
        if (error) error.style.display = 'none';

        try {
            const paymentId = await this._createPaymentForSubselection(subselection);

            if (this._loadingSubselection !== subselection) {
                return;
            }

            if (loading) loading.style.display = 'none';
            this._activeSubselection = subselection;
            this._loadingSubselection = null;
            this._initCheckoutInContainer(paymentId, container.id);
        } catch (_) {
            if (this._loadingSubselection === subselection) {
                this._loadingSubselection = null;
            }
            if (loading) loading.style.display = 'none';
            if (error) error.style.display = 'block';
        }
    }

    _collapseActiveMethod() {
        const subselection = this._activeSubselection ?? this._loadingSubselection;
        if (subselection === null) return;

        const prev = document.getElementById(`nexi-checkout-${subselection}`);
        if (prev) {
            if (this._activeCheckout) {
                this._activeCheckout.cleanup?.();
                this._activeCheckout = null;
            }
            this._clearCheckoutContent(prev);
            prev.style.display = 'none';

            const loading = prev.querySelector('.nexi-split-loading');
            const error = prev.querySelector('.alert-danger');
            if (loading) loading.style.display = 'none';
            if (error) error.style.display = 'none';
        }

        this._activeSubselection = null;
        this._loadingSubselection = null;
    }

    _clearCheckoutContent(container) {
        Array.from(container.children).forEach(child => {
            if (!child.classList.contains('nexi-split-loading') && !child.classList.contains('alert-danger')) {
                container.removeChild(child);
            }
        });
    }

    _createPaymentForSubselection(subselection) {
        return new Promise((resolve, reject) => {
            this._client.post(
                this.options.createEmbeddedPaymentUrl,
                JSON.stringify({ subselection }),
                (responseText, request) => {
                    if (request.status >= 400) {
                        reject(new Error('Failed to create payment'));
                        return;
                    }
                    const { paymentId } = JSON.parse(responseText);
                    resolve(paymentId);
                }
            );
        });
    }

    _initCheckoutInContainer(paymentId, containerId) {
        this._checkout = new Dibs.Checkout({
            checkoutKey: this.options.checkoutKey,
            paymentId: paymentId,
            containerId: containerId,
            language: this.options.language,
        });
        this._checkout.on('pay-initialized', this.onPaymentInitialized.bind(this));
        this._checkout.on('payment-completed', this.onPaymentCompleted.bind(this));
        this._activeCheckout = this._checkout;
    }

    // Standard (non-split) mode

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

    // Shared event handlers

    async onPaymentInitialized(paymentId) {
        if (this.options.targetPath !== null) {
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
                this._updateTargetPath(path);
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
                this._redirectToTargetPath();
            })
            .catch((path) => {
                this._updateTargetPath(path);
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
                JSON.stringify({ nexiPaymentId: paymentId }),
                (responseText, request) => {
                    if (request.status >= 400) {
                        const { targetPath } = JSON.parse(responseText);
                        reject(targetPath);
                    }
                    const { targetPath } = JSON.parse(responseText);
                    resolve(targetPath);
                }
            );
        });
    }
}
