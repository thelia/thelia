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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/sale_offset_currencies/{sale}/currencies/{currency}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
)]
#[CompositeIdentifiers(['sale', 'currency'])]
class SaleOffsetCurrency extends AbstractPropelResource
{
    public const GROUP_READ = 'sale_offset_currency:read';
    public const GROUP_READ_SINGLE = 'sale_offset_currency:read:single';
    public const GROUP_WRITE = 'sale_offset_currency:write';

    #[Relation(targetResource: Sale::class)]
    #[Groups([self::GROUP_READ])]
    public Sale $sale;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_READ])]
    public Currency $currency;

    #[Groups([self::GROUP_READ])]
    public float $priceOffsetValue;

    public function getSale(): Sale
    {
        return $this->sale;
    }

    public function setSale(Sale $sale): self
    {
        $this->sale = $sale;

        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getPriceOffsetValue(): float
    {
        return $this->priceOffsetValue;
    }

    public function setPriceOffsetValue(float $priceOffsetValue): self
    {
        $this->priceOffsetValue = $priceOffsetValue;

        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\SaleOffsetCurrency::class;
    }
}
