/*!
 * Piwik - free/libre analytics platform
 *
 * Screenshot tests for main, top and admin menus.
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("Marketplace", function () {
    this.timeout(0);

    var urlBase = '?module=Marketplace&action=overview&';
    var paidPluginsUrl = urlBase + 'type=paid';
    var freePluginsUrl = urlBase + 'type=free';

    var noLicense = '';
    var expiredLicense = 'expiredLicense';
    var validLicense = 'validLicense';

    function loadPluginDetailPage(page, pluginName, isFreePlugin)
    {
        page.load(isFreePlugin ? freePluginsUrl : paidPluginsUrl);
        page.click('.panel-title [data-pluginname="' + pluginName + '"]');
    }

    function captureSelector(done, screenshotName, test, selector)
    {
        expect.screenshot(screenshotName).to.be.captureSelector(selector, test, done);
    }

    function captureMarketplace(done, screenshotName, test, selector)
    {
        if (!selector) {
            selector = '';
        }

        captureSelector(done, screenshotName, test, '.marketplace' + selector);
    }

    function captureWithNotification(done, screenshotName, test)
    {
        captureMarketplace(done, screenshotName, test, ',#notificationContainer');
    }

    function captureWithDialog(done, screenshotName, test)
    {
        captureMarketplace(done, screenshotName, test, ',.ui-dialog:visible');
    }

    function setEnvironment(mode, consumer)
    {
        if (mode === 'user') {
            testEnvironment.idSitesAdminAccess = [1];
        } else {
            // superuser
            testEnvironment.idSitesAdminAccess = [];
        }

        if (mode === 'multiUserEnvironment') {
            testEnvironment.overrideConfig('General', 'multi_server_environment', '1');
        } else {
            testEnvironment.overrideConfig('General', 'multi_server_environment', '0');
        }

        testEnvironment.overrideConfig('General', 'enable_plugins_admin', '1');

        delete testEnvironment.showExpiredLicenseIfModule;

        testEnvironment.consumer = consumer;
        testEnvironment.mockMarketplaceApiService = 1;
        testEnvironment.save();
    }

    it('should show a message if license is expired and client is trying to access a paid plugin page', function (done) {
        setEnvironment('superuser', expiredLicense);
        testEnvironment.showExpiredLicenseIfModule = 'CustomDimensions';
        testEnvironment.save();

        captureSelector(done, 'expired_license_standalone', function (page) {
            page.load('?module=CustomDimensions&action=manage');
        }, 'body');
    });

    it('embedded should show a message if license is expired and client is trying to access a paid plugin page via XHR', function (done) {
        setEnvironment('superuser', expiredLicense);
        testEnvironment.showExpiredLicenseIfModule = 'CustomDimensions';
        testEnvironment.save();

        captureSelector(done, 'expired_license_embedded', function (page) {
            page.load('?module=CoreHome&action=index&idSite=1&period=day&date=yesterday#?module=CustomDimensions&action=manage&idSite=1&period=day&date=yesterday');
        }, '#content');
    });

    ['superuser', 'user', 'multiUserEnvironment'].forEach(function (mode) {

        if (mode !== 'user') {
            it('should show available updates in plugins page', function (done) {
                setEnvironment(mode, noLicense);

                captureSelector(done, mode + '_updates', function (page) {
                    page.load('?module=CorePluginsAdmin&action=plugins&idSite=1&period=day&date=yesterday&activated=');
                }, '.entityContainer:first');
            });

            it('should show number of available updates in plugins menu item', function (done) {
                captureSelector(done, mode + '_updates_menu_item', function (page) {
                }, '.navbar .item:contains(Plugins)');
            });
        }

        it(mode + ' for a user with valid license key should open paid plugins by default', function (done) {
            setEnvironment(mode, validLicense);

            captureMarketplace(done, mode + '_valid_license_paid_plugins', function (page) {
                page.load(urlBase);
            });
        });

        it(mode + ' for a user with valid license key should be able to open free plugins and see only whitelisted plugins', function (done) {
            setEnvironment(mode, validLicense);

            captureMarketplace(done, mode + '_valid_license_free_plugins', function (page) {
                page.load(freePluginsUrl);
            });
        });

        it(mode + ' for a user without license key should open free plugins by default', function (done) {
            setEnvironment(mode, noLicense);

            captureMarketplace(done, mode + '_no_license_free_plugins', function (page) {
                page.load(urlBase);
            });
        });

        it(mode + ' for a user without license key should be able to open paid plugins', function (done) {
            setEnvironment(mode, noLicense);

            captureMarketplace(done, mode + '_no_license_paid_plugins', function (page) {
                page.load(paidPluginsUrl);
            });
        });

        it(mode + ' for a user with expired license key should open paid plugins by default and show a warning that license is expired', function (done) {
            setEnvironment(mode, expiredLicense);

            captureMarketplace(done, mode + '_expired_license_paid_plugins', function (page) {
                page.load(urlBase);
            });
        });

        it(mode + ' for a user with expired license key should be able to view free plugins, no restrictions in plugins anymore', function (done) {
            setEnvironment(mode, expiredLicense);

            captureMarketplace(done, mode + '_expired_license_free_plugins', function (page) {
                page.load(freePluginsUrl);
            });
        });

        it('should show custom plugin details', function (done) {
            setEnvironment(mode, noLicense);

            captureWithDialog(done, mode + '_free_plugin_details', function (page) {
                var isFree = true;
                loadPluginDetailPage(page, 'TreemapVisualization', isFree);
            });
        });

        it('should show custom plugin details when having valid license', function (done) {
            setEnvironment(mode, validLicense);

            captureWithDialog(done, mode + '_valid_license_paid_plugin_details', function (page) {
                var isFree = false;
                loadPluginDetailPage(page, 'PaidPlugin1', isFree);
            });
        });

        it('should show paid plugin details when having valid license', function (done) {
            setEnvironment(mode, validLicense);

            captureWithDialog(done, mode + '_valid_license_custom_plugin_details', function (page) {
                var isFree = false;
                loadPluginDetailPage(page, 'CustomPlugin1', isFree);
            });
        });
    });

    var mode = 'superuser';

    it('should show an error message when invalid license key entered', function (done) {
        setEnvironment(mode, noLicense);

        captureWithNotification(done, mode + '_invalid_license_key_entered', function (page) {
            page.load(paidPluginsUrl);
            page.sendKeys('#license_key', 'invalid');
            page.click('.marketplace-paid-intro'); // click outside so change event is triggered
            page.click('#submit_license_key');
        });
    });

    it('should be possible to remove a license key', function (done) {
        setEnvironment(mode, validLicense);

        captureWithNotification(done, mode + '_remove_license_key_entered', function (page) {
            page.load(paidPluginsUrl);
            page.callMethod(function () {
                setEnvironment(mode, noLicense);
            });
            page.click('#remove_license_key');
        });
    });

    it('should show a success message when valid license key entered', function (done) {
        setEnvironment(mode, noLicense);

        captureWithNotification(done, mode + '_valid_license_key_entered', function (page) {
            page.load(paidPluginsUrl);
            page.sendKeys('#license_key', 'valid');
            page.callMethod(function () {
                setEnvironment(mode, validLicense);
            });
            page.click('#submit_license_key');
        });
    });

    it('should hide activate / deactivate buttons if plugins admin is disabled', function (done) {
        setEnvironment(mode, noLicense);
        testEnvironment.overrideConfig('General', 'enable_plugins_admin', '0');
        testEnvironment.save();

        captureMarketplace(done, mode + '_enable_plugins_admin', function (page) {
            page.load(freePluginsUrl);
        });
    });

    it('should hide activate / deactivate buttons if plugins admin is disabled when also multi server environment is enabled', function (done) {
        setEnvironment('multiUserEnvironment', noLicense);
        testEnvironment.overrideConfig('General', 'enable_plugins_admin', '0');
        testEnvironment.save();

        captureMarketplace(done, mode + '_enable_plugins_admin_with_multiserver_enabled', function (page) {
            page.load(freePluginsUrl);
        });
    });


});