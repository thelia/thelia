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

namespace TheliaSmarty\Template\Plugins;

use TheliaSmarty\Template\SmartyPluginDescriptor;
use TheliaSmarty\Template\AbstractSmartyPlugin;

/**
 * Class TheliaSyntax
 * @package Thelia\Core\Template\Smarty\Plugins
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TheliaSyntax extends AbstractSmartyPlugin
{
    public function dieseCancel($value, $diese)
    {
        if ($value === null) {
            return $diese;
        }

        return $value;
    }

    /**
     * @return SmartyPluginDescriptor[]
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("modifier", "dieseCanceller", $this, "dieseCancel")
        );
    }
}
