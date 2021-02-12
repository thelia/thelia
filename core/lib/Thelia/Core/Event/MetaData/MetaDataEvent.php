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

namespace Thelia\Core\Event\MetaData;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\MetaData;

/**
 * Class MetaDataEvent.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataEvent extends ActionEvent
{
    protected $metaData;

    public function __construct(MetaData $metaData = null)
    {
        $this->metaData = $metaData;
    }

    /**
     * @param \Thelia\Model\MetaData|null $metaData
     *
     * @return $this
     */
    public function setMetaData($metaData)
    {
        $this->metaData = $metaData;

        return $this;
    }

    /**
     * @return \Thelia\Model\MetaData|null
     */
    public function getMetaData()
    {
        return $this->metaData;
    }
}
