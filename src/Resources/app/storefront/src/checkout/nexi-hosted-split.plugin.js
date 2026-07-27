const { PluginBaseClass } = window;

export default class NexiHostedSplitPlugin extends PluginBaseClass {
    init() {
        this.el.querySelectorAll('input[name="subselection"]').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('input[name="paymentMethodId"]').forEach(r => { r.checked = false; });
            });
        });
    }
}
