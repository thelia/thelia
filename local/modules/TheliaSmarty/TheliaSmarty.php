<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace TheliaSmarty;

use Thelia\Module\BaseModule;
use TheliaSmarty\Compiler\RegisterParserPluginPass;

class TheliaSmarty extends BaseModule
{
    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */

    public static function getCompilers()
    {
        return [
            new RegisterParserPluginPass()
        ];
    }

    /**
     * Define how module services are loaded
     *
     * @return array
     *
     * autoload => if true all php file in you module directory will be loaded as services
     * autoloadExclude => to exclude some path of service autoloading (like I18n folders)
     * autowire => if true all your services parameters will be autowired with good services
     * autoconfigure => if true all your service that extend an interface will be tagged if this interface need specific tag (like EventSubscriberInterface => kernel.event_subscriber)
     *
     * @inheritdoc
     *
     */
    public static function serviceLoaderConfig(): array
    {
        return [
            "autoload" => true,
            "autoloadExclude" => [THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"],
            "autowire" => true,
            "autoconfigure" => true
        ];
    }
}
