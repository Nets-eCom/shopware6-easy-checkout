import './init/api-service.init';
import './module/nets-checkout';
import './api/nets-checkout-api-payment-service';
import './service/netsApiTestService';
import './component/nets-api-test-button';

import localeDE from './snippet/de_DE.json';
import localeEN from './snippet/en_GB.json';
Shopware.Locale.extend('de-DE', localeDE);
Shopware.Locale.extend('en-GB', localeEN);