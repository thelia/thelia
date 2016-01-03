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

namespace Thelia\Core\Event\Config;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Config;

class ConfigEvent extends ActionEvent
{
    protected $config = null;

    public function __construct(Config $config = null)
    {
        $this->config = $config;
    }

    public function hasConfig()
    {
        return ! is_null($this->config);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }
}
