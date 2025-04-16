import EmbeddedPlugin from './checkout/embedded-plugin';
import ConfirmFormPlugin from "./checkout/confirm-form-plugin";

const PluginManager = window.PluginManager;
PluginManager.register('EmbeddedPlugin', EmbeddedPlugin, '[data-embedded-plugin]');
PluginManager.register('ConfirmFormPlugin', ConfirmFormPlugin, '[data-confirm-form-plugin]');
