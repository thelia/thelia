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

namespace Thelia\Api\Resource;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\Serializer\Annotation\Ignore;

trait PropelResourceTrait
{
    #[Ignore]
    protected array $resourceAddons = [];

    #[Ignore]
    private ?ActiveRecordInterface $propelModel = null;

    public function __get(string $property)
    {
        if (array_key_exists($property, $this->resourceAddons)) {
            return $this->getResourceAddon($property);
        }

        throw new NoSuchPropertyException(sprintf('Can\'t get a way to read the property "%s" in class "%s".', $property, $this::class));
    }

    public function setPropelModel(?ActiveRecordInterface $propelModel = null): PropelResourceInterface
    {
        $this->propelModel = $propelModel;

        return $this;
    }

    public function getPropelModel(): ?ActiveRecordInterface
    {
        return $this->propelModel;
    }

    public function getResourceAddon(string $addonName): ?ResourceAddonInterface
    {
        return $this->resourceAddons[$addonName] ?? null;
    }

    public function setResourceAddon(string $addonName, ?ResourceAddonInterface $addon): PropelResourceInterface
    {
        $this->resourceAddons[$addonName] = $addon;

        return $this;
    }

    public function getResourceAddons(): array
    {
        return $this->resourceAddons;
    }

    public function afterModelToResource(array $context): void
    {
        return;
    }
}
