Installation
1. Download zip archive from [release page](https://github.com/coinbase/coinbase-commerce-xcart/releases) and unzip or clone plugin and run `composer install` inside clonned folder
2. Move module to your xcart site. Copy src/classes/XLite/Module/Coinbase to src/classes/XLite/Module/ and skins/admin/modules/Coinbase to skins/admin/modules/.
3. Re-deploy the store. Go to "System tools"/"Cache management" and click Start button in "Re-deploy the store" section
4. Activate module. Go to "My Addons", find Coinbase Commerce Payment Method switch to ON and click Save changes button.
5. Go to "Store setup"/"Payment methods" activate Coinbase Commerce and click Configure
6. Copy "App Key" and "Secret Key" from "Settings" page of Coinbase Commerce Dashboard (https://commerce.coinbase.com/dashboard/settings).
7. Copy Webhook Url from module's configuration page to Coinbase Commerce Dashboard