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

namespace Thelia\Core\Event\Brand;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Brand;

/**
 * Class BrandEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\BrandEvent
 */
class BrandEvent extends ActionEvent
{
    public function __construct(protected ?Brand $brand = null)
    {
    }

    public function setBrand(Brand $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    /**
     * check if brand exists.
     */
    public function hasBrand(): bool
    {
        return $this->brand instanceof Brand;
    }
}
