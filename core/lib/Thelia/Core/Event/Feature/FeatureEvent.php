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
use Thelia\Model\Feature;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\FeatureEvent
 */
class FeatureEvent extends ActionEvent
{
    public function __construct(protected ?Feature $feature = null)
    {
    }

    public function hasFeature(): bool
    {
        return $this->feature instanceof Feature;
    }

    public function getFeature(): ?Feature
    {
        return $this->feature;
    }

    public function setFeature(?Feature $feature): static
    {
        $this->feature = $feature;

        return $this;
    }
}
