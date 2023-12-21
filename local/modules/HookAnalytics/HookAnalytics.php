<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HookAnalytics;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Module\BaseModule;

class HookAnalytics extends BaseModule
{
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        if (($config = ConfigQuery::read('hookanalytics_trackingcode', ''))
            && version_compare($newVersion, '2.4.4', '>=')
            && version_compare($currentVersion, '2.4.4', '<')) {
            $langs = LangQuery::create()->filterByActive()->find();
            if ($config) {
                foreach ($langs as $lang) {
                    self::setConfigValue('hookanalytics_trackingcode', $config, $lang->getLocale());
                }
            }
        }
    }
}
