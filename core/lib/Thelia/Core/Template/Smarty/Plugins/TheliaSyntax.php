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

namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;

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
