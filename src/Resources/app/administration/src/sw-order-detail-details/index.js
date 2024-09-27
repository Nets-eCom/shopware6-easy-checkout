import template from "./sw-order-detail-details.html.twig";

Shopware.Component.override("sw-order-detail-details", {
  template,
  inject: [],
  mixins: [],
  data() {
    return {
      isLoading: false,
      disabled: true,
      isCaptureModalVisible: false,
    };
  },
  methods: {
    toggleCaptureModal() {
      this.isCaptureModalVisible = !this.isCaptureModalVisible;
    },
  },
});
