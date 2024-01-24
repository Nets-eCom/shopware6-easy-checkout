import Plugin from 'src/plugin-system/plugin.class';

export default class ConfirmFormPlugin extends Plugin {
    init() {
        this._shouldRenderCheckoutElements();
        this._hideTosCheckbox();
    }

    _shouldRenderCheckoutElements() {
        const {blockedOrder, isEmbeddedCheckout} = this.options;

        if (isEmbeddedCheckout && !blockedOrder) {
            return;
        }

        this._hideSubmitForm();
        this._hideTosCheckbox();
    }

    _hideSubmitForm() {
        const confirmFormSubmit = document.getElementById('confirmFormSubmit');

        if (confirmFormSubmit) {
            confirmFormSubmit.style.display = 'none';
            confirmFormSubmit.disabled = true;
        }
    }

    _hideTosCheckbox() {
        const tosCheckbox = document.getElementsByClassName('confirm-tos')[0];

        if (tosCheckbox) {
            tosCheckbox.style.display = 'none'
        }
    }
}
