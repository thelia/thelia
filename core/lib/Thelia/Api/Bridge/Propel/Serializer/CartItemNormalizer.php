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

namespace Thelia\Api\Bridge\Propel\Serializer;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Thelia\Api\Resource\CartItem;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\CartItem as PropelCartItem;
use Thelia\Model\Country;

class CartItemNormalizer extends AbstractItemNormalizer
{
    public function __construct(
        private readonly TaxEngine $taxEngine,
        private readonly Session $session,
        private readonly RequestStack $requestStack,
        PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        PropertyMetadataFactoryInterface $propertyMetadataFactory,
        IriConverterInterface $iriConverter,
        ResourceClassResolverInterface $resourceClassResolver,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?NameConverterInterface $nameConverter = null,
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        array $defaultContext = [],
        ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
        ?ResourceAccessCheckerInterface $resourceAccessChecker = null,
    ) {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $iriConverter, $resourceClassResolver, $propertyAccessor, $nameConverter, $classMetadataFactory, $defaultContext, $resourceMetadataCollectionFactory, $resourceAccessChecker);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CartItem && isset($context['root_operation']) && $context['root_operation'] instanceof Get;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return false;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $this->requestStack->getMainRequest()->setSession($this->session); // Todo : Quick fix for Call to undefined method Symfony\Component\HttpFoundation\Session\Session::getMethod
        $propelCartItem = $object->getPropelModel();
        $country = $this->taxationCountryOf($propelCartItem);
        /* @var CartItem $object */
        $object
            ->setCalculatedTotalPrice($propelCartItem->getTotalPrice())
            ->setCalculatedTotalPromoPrice($propelCartItem->getTotalPromoPrice())
            ->setCalculatedTotalTaxedPrice($propelCartItem->getTotalTaxedPrice($country))
            ->setCalculatedTotalPromoTaxedPrice($propelCartItem->getTotalTaxedPromoPrice($country))
            ->setCalculatedRealPrice($propelCartItem->getRealPrice())
            ->setCalculatedRealTaxedPrice($propelCartItem->getRealTaxedPrice($country))
            ->setCalculatedRealTotalPrice($propelCartItem->getTotalRealPrice())
            ->setCalculatedRealTotalTaxedPrice($propelCartItem->getTotalTaxedPrice($country))
            ->setIsPromo((bool) $propelCartItem->getPromo());

        return parent::normalize($object, $format, $context);
    }

    /**
     * Where this line is taxed: the country of the delivery address its cart carries,
     * the very rule the totals of that cart follow in {@see CartNormalizer}.
     *
     * The tax engine reads the cart of the SESSION, and an API request has none: there it
     * falls back to the shop's default country, and the lines of a cart delivered abroad
     * no longer added up to its own total. The engine stays the answer for a cart that
     * names no delivery address yet.
     */
    private function taxationCountryOf(PropelCartItem $propelCartItem): Country
    {
        $country = $propelCartItem->getCart()?->getCartAddressRelatedByAddressDeliveryId()?->getCountry();

        return $country instanceof Country ? $country : $this->taxEngine->getDeliveryCountry();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            CartItem::class => false,
        ];
    }
}
