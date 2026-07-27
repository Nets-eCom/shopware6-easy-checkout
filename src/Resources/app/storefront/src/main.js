const {PluginManager} = window;
PluginManager.register('EmbeddedPlugin', () => import('./checkout/embedded-plugin.plugin'), '[data-embedded-plugin]');
PluginManager.register('NexiHostedSplitPlugin', () => import('./checkout/nexi-hosted-split.plugin'), '[data-nexi-hosted-split]');