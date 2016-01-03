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

namespace Thelia\Core\Event\MetaData;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\MetaData;

/**
 * Class MetaDataEvent
 * @package Thelia\Core\Event\MetaData
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataEvent extends ActionEvent
{
    protected $metaData = null;

    public function __construct(MetaData $metaData = null)
    {
        $this->metaData = $metaData;
    }

    /**
     * @param null|\Thelia\Model\MetaData $metaData
     *
     * @return $this
     */
    public function setMetaData($metaData)
    {
        $this->metaData = $metaData;

        return $this;
    }

    /**
     * @return null|\Thelia\Model\MetaData
     */
    public function getMetaData()
    {
        return $this->metaData;
    }
}
