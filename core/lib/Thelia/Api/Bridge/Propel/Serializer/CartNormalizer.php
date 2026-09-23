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
use Thelia\Api\Resource\Cart;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Shipping\Service\PostageEstimator;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Cart as PropelCart;
use Thelia\Model\Country;
use Thelia\Model\State;

class CartNormalizer extends AbstractItemNormalizer
{
    public function __construct(
        private readonly TaxEngine $taxEngine,
        private readonly Session $session,
        private readonly RequestStack $requestStack,
        private readonly PostageEstimator $postageEstimator,
        private readonly VatExemptionResolver $vatExemptionResolver,
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

    /**
     * Every payload that hands back a whole cart, and no other.
     *
     * The group is what decides, rather than the kind of operation: the totals, the taxes
     * and the estimated postage are only ever declared in the "read a single cart" group,
     * so computing them for a payload that cannot show them was work thrown away — and
     * keying on `Get` left out the checkout operations, which hand back the very cart a
     * choice has just changed and are the ones a buyer reads a delivery cost off.
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Cart
            && \in_array(Cart::GROUP_FRONT_READ_SINGLE, (array) ($context['groups'] ?? []), true);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return false;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $this->requestStack->getMainRequest()?->setSession($this->session); // Todo : Quick fix for Call to undefined method Symfony\Component\HttpFoundation\Session\Session::getMethod
        $propelCart = $object->getPropelModel();
        [$country, $state] = $this->taxationPlaceOf($propelCart);
        [$estimatedPostage, $postageTax] = $this->deliveryCostOf($propelCart, $country, $state);
        /* @var Cart $object */
        $object
            ->setTotalWithoutTax($propelCart->getTotalAmount())
            ->setDeliveryTax($postageTax)
            ->setTaxes($propelCart->getTotalVAT($country, null, false))
            ->setDelivery($estimatedPostage)
            ->setTotal($propelCart->getTaxedAmount($country, false, null))
            ->setVirtual($propelCart->isVirtual())
            ->setIsVatExempted($this->vatExemptionResolver->isExemptedForCart($propelCart));

        return parent::normalize($object, $format, $context);
    }

    /**
     * Where this cart is taxed: the country and the state of the delivery address it
     * carries.
     *
     * The tax engine reads the cart of the SESSION, and an API request has none: there it
     * falls back to the shop's default country, so a cart delivered abroad was answered
     * with the taxes of the shop — right after the checkout operation that had recorded
     * the buyer's address. The cart being normalized is the one to read. The engine
     * stays the answer for a cart that names no delivery address yet.
     *
     * @return array{0: Country, 1: State|null}
     */
    private function taxationPlaceOf(PropelCart $propelCart): array
    {
        $deliveryAddress = $propelCart->getCartAddressRelatedByAddressDeliveryId();
        $country = $deliveryAddress?->getCountry();

        if ($country instanceof Country) {
            return [$country, $deliveryAddress->getState()];
        }

        return [$this->taxEngine->getDeliveryCountry(), $this->taxEngine->getDeliveryState()];
    }

    /**
     * What the delivery of this cart costs, and what asking costs.
     *
     * Estimating means asking every carrier module that can serve the country for a price,
     * one dispatch each, some of them over the network to a carrier's API — and it was
     * done on every single payload carrying a cart, including the four answers of the
     * checkout, where the buyer has already picked a carrier and the price it quoted is
     * written on the cart. That is a round of quotes per request to recompute a number
     * the shop had already settled and will bill.
     *
     * So: a cart that names its carrier and carries its postage answers with the postage
     * it carries. An estimate is for the cart that has not chosen yet — the cheapest of
     * what could serve it — and that is exactly the cart where nothing is written to read
     * instead. The postage is compared to null and not to zero: free shipping is a price
     * the shop settled, not the absence of one.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function deliveryCostOf(PropelCart $propelCart, Country $country, ?State $state): array
    {
        if (null !== $propelCart->getDeliveryModuleId() && null !== $propelCart->getPostage()) {
            return [(float) $propelCart->getPostage(), (float) $propelCart->getPostageTax()];
        }

        $postageInfo = $this->postageEstimator->estimatePostageForCountry(
            cart: $propelCart,
            country: $country,
            state: $state,
        );

        return [$postageInfo->getBestPostageAmount(), $postageInfo->getBestPostageTax()];
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Cart::class => false,
        ];
    }
}
