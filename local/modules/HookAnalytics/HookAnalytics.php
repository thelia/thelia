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

namespace HookAnalytics;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Module\BaseModule;

class HookAnalytics extends BaseModule
{
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null)
    {
        if (version_compare($newVersion, $currentVersion, ">")){
            $langs = LangQuery::create()->filterByActive()->find();
            $config = ConfigQuery::read("hookanalytics_trackingcode", "");
            foreach ($langs as $lang){
                self::setConfigValue('hookanalytics_trackingcode', $config, $lang->getLocale());
            }
        }
    }
}
