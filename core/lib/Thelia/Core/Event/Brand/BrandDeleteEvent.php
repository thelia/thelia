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

/**
 * Class BrandDeleteEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandDeleteEvent extends BrandEvent
{
    /**
     * @param int $brand_id
     */
    public function __construct(protected $brand_id)
    {
    }

    /**
     * @return BrandDeleteEvent $this
     */
    public function setBrandId(int $brand_id): static
    {
        $this->brand_id = $brand_id;

        return $this;
    }

    public function getBrandId(): int
    {
        return $this->brand_id;
    }
}
