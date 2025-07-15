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
 * Class BrandUpdateEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandUpdateEvent extends BrandCreateEvent
{
    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $logo_image_id;

    /**
     * @param int $brandId
     */
    public function __construct(protected $brandId)
    {
    }

    /**
     * @return BrandUpdateEvent $this
     */
    public function setChapo(string $chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo(): string
    {
        return $this->chapo;
    }

    /**
     * @return BrandUpdateEvent $this
     */
    public function setBrandId(int $brandId): static
    {
        $this->brandId = $brandId;

        return $this;
    }

    public function getBrandId(): int
    {
        return $this->brandId;
    }

    /**
     * @return BrandUpdateEvent $this
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return BrandUpdateEvent $this
     */
    public function setPostscriptum(string $postscriptum): static
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum(): string
    {
        return $this->postscriptum;
    }

    /**
     * @return $this
     */
    public function setLogoImageId(int $logo_image_id): static
    {
        $this->logo_image_id = $logo_image_id;

        return $this;
    }

    public function getLogoImageId(): int
    {
        return $this->logo_image_id;
    }
}
