<?php
/**
 * Drip Custom Event plugin for Craft CMS 3.x
 *
 * Add or update subscribers based on custom events.
 *
 * @link      https://craftquest.io
 * @copyright Copyright (c) 2018 Ryan Irelan
 */

namespace mijingo\dripcustomevent;

use mijingo\dripcustomevent\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Ryan Irelan
 * @package   DripCustomEvent
 * @since     1.0.0
 *
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class DripCustomEvent extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * DripCustomEvent::$plugin
     *
     * @var DripCustomEvent
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * DripCustomEvent::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;
        /*
         * Using the EVENT_AFTER_SAVE_ELEMENT event andn then checking for an instance of User element being saved.
         * - connect to the Drip API
         * - add a new subscriber
         * - add event that they created a new CraftQuest account
         */

        Event::on(\craft\services\Elements::class, \craft\services\Elements::EVENT_AFTER_SAVE_ELEMENT, function(Event $event) {
            if ($event->element instanceof \craft\elements\User) {

                /*
                 * Get the Drip info from Plugin Settings
                 */
                $settings = $this::getInstance()->getSettings();
                $accountId = $settings->accountId;
                $apiToken = $settings->apiToken;
                $eventName = $settings->eventName;

                /*
                 * Connect to Drip API via GuzzleHttp to create new subscriber
                 */
                $client = new \GuzzleHttp\Client();
                $url = 'https://api.getdrip.com/v2/'.$accountId.'/subscribers';


                $email = $event->element->email;
                $name = $event->element->firstName . ' ' . $event->element->lastName;

                // Make the API request to Drip and pass in new user account information
                $client->request('POST', $url, [
                    'auth' => [$apiToken, ''],
                    'headers' => ['content-type' => 'application/vnd.api+json'],
                    'body' => json_encode([
                        'subscribers' => [
                            [
                                'email' => $email,
                                'custom_fields' => [
                                    'name' => $name,
                                    'accountType' => 'Free',
                                ],
                            ]
                        ]
                    ])
                ]);

                /*
                * Connect to Drip API via GuzzleHttp to create new subscriber
                */
                $client = new \GuzzleHttp\Client();
                $url = 'https://api.getdrip.com/v2/'.$accountId.'/events';

                // Make the API request to Drip and pass in custom event information
                $client->request('POST', $url, [
                    'auth' => [$apiToken, ''],
                    'headers' => ['content-type' => 'application/vnd.api+json'],
                    'body' => json_encode([
                        'events' => [
                            [
                                'email' => $email,
                                'action' => $eventName
                            ]
                        ]
                    ])
                ]);
            }
        });

        /**
         * Logging in Craft involves using one of the following methods:
         *
         * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
         * Craft::info(): record a message that conveys some useful information.
         * Craft::warning(): record a warning message that indicates something unexpected has happened.
         * Craft::error(): record a fatal error that should be investigated as soon as possible.
         *
         * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
         *
         * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
         * the category to the method (prefixed with the fully qualified class name) where the constant appears.
         *
         * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
         * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
         *
         * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
         */
        Craft::info(
            Craft::t(
                'drip-custom-event',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {

        // Get and pre-validate the settings
        $settings = $this->getSettings();
        $settings->validate();

        $overrides = Craft::$app->getConfig()->getConfigFromFile(strtolower($this->handle));
        return Craft::$app->view->renderTemplate(
            'drip-custom-event/settings',
            [
                'settings' => $this->getSettings(),
                'overrides' => array_keys($overrides),

            ]
        );
    }
}
