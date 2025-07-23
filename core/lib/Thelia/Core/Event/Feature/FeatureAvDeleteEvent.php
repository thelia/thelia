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

class FeatureAvDeleteEvent extends FeatureAvEvent
{
    protected int $featureAv_id;

    public function __construct(int $featureAv_id)
    {
        $this->setFeatureAvId($featureAv_id);
    }

    public function getFeatureAvId(): int
    {
        return $this->featureAv_id;
    }

    public function setFeatureAvId(int $featureAv_id): static
    {
        $this->featureAv_id = $featureAv_id;

        return $this;
    }
}
