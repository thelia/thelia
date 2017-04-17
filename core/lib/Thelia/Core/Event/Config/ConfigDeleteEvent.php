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

class ConfigDeleteEvent extends ConfigEvent
{
    /** @var int */
    protected $config_id;

    /**
     * @param int $config_id
     */
    public function __construct($config_id)
    {
        $this->setConfigId($config_id);
    }

    public function getConfigId()
    {
        return $this->config_id;
    }

    public function setConfigId($config_id)
    {
        $this->config_id = $config_id;

        return $this;
    }
}
