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
use Thelia\Model\FeatureAv;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\FeatureAvEvent
 */
class FeatureAvEvent extends ActionEvent
{
    protected $featureAv;

    public function __construct(FeatureAv $featureAv = null)
    {
        $this->featureAv = $featureAv;
    }

    public function hasFeatureAv()
    {
        return !\is_null($this->featureAv);
    }

    public function getFeatureAv()
    {
        return $this->featureAv;
    }

    public function setFeatureAv($featureAv)
    {
        $this->featureAv = $featureAv;

        return $this;
    }
}
