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

namespace Thelia\Core\Event\Feature;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Feature;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\FeatureEvent
 */
class FeatureEvent extends ActionEvent
{
    protected $feature;

    public function __construct(Feature $feature = null)
    {
        $this->feature = $feature;
    }

    public function hasFeature()
    {
        return ! \is_null($this->feature);
    }

    public function getFeature()
    {
        return $this->feature;
    }

    public function setFeature($feature)
    {
        $this->feature = $feature;

        return $this;
    }
}
