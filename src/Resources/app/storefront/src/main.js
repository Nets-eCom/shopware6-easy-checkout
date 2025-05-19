const {PluginManager} = window;
PluginManager.register('EmbeddedPlugin', () => import('./checkout/embedded-plugin.plugin'), '[data-embedded-plugin]');