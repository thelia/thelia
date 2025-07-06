<?php

declare(strict_types=1);

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
    public function __construct(protected ?FeatureAv $featureAv = null)
    {
    }

    public function hasFeatureAv(): bool
    {
        return $this->featureAv instanceof FeatureAv;
    }

    public function getFeatureAv(): ?FeatureAv
    {
        return $this->featureAv;
    }

    public function setFeatureAv(?FeatureAv $featureAv): static
    {
        $this->featureAv = $featureAv;

        return $this;
    }
}
