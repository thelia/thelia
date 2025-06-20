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
namespace Thelia\Core\Event\FeatureProduct;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\FeatureProduct;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\FeatureProductEvent
 */
class FeatureProductEvent extends ActionEvent
{
    public function __construct(protected ?FeatureProduct $featureProduct = null)
    {
    }

    public function hasFeatureProduct(): bool
    {
        return $this->featureProduct instanceof FeatureProduct;
    }

    public function getFeatureProduct(): ?FeatureProduct
    {
        return $this->featureProduct;
    }

    public function setFeatureProduct(?FeatureProduct $featureProduct): static
    {
        $this->featureProduct = $featureProduct;

        return $this;
    }
}
