<?php

namespace Thelia\Api\Bridge\Propel\Serializer;

use ApiPlatform\Api\IriConverterInterface as LegacyIriConverterInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Thelia\Api\Resource\Cart;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Service\Model\CartService;
use Thelia\TaxEngine\TaxEngine;
use ApiPlatform\Api\ResourceClassResolverInterface as LegacyResourceClassResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;


class CartNormalizer extends AbstractItemNormalizer
{

    public function __construct(
        private TaxEngine $taxEngine,
        private Session $session,
        private RequestStack $requestStack,
        private CartService $cartService,
        PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, LegacyIriConverterInterface|IriConverterInterface $iriConverter, LegacyResourceClassResolverInterface|ResourceClassResolverInterface $resourceClassResolver, ?PropertyAccessorInterface $propertyAccessor = null, ?NameConverterInterface $nameConverter = null, ?ClassMetadataFactoryInterface $classMetadataFactory = null, array $defaultContext = [], ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null, ?ResourceAccessCheckerInterface $resourceAccessChecker = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $iriConverter, $resourceClassResolver, $propertyAccessor, $nameConverter, $classMetadataFactory, $defaultContext, $resourceMetadataCollectionFactory, $resourceAccessChecker);
    }


    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return ($data instanceof Cart && isset($context['operation']) && $context['operation'] instanceof Get);
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return false;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $this->requestStack->getCurrentRequest()->setSession($this->session);//Todo : Quick fix for Call to undefined method Symfony\Component\HttpFoundation\Session\Session::getMethod
        $propelCart = $object->getPropelModel();
        $country = $this->taxEngine->getDeliveryCountry();
        $state = $this->taxEngine->getDeliveryState();
        $postageInfo = $this->cartService->getEstimatedPostageForCountry(
            cart: $propelCart,
            country:  $country,
            state: $state
        );
        $estimatedPostage = $postageInfo['postage'];
        $postageTax = $postageInfo['tax'];
        /** @var Cart $object */
        $object
            ->setTotalWithoutTax($propelCart->getTotalAmount())
            ->setDeliveryTax($postageTax)
            ->setTaxes($propelCart->getTotalVAT($country, null, false))
            ->setDelivery($estimatedPostage)
            ->setTotal($propelCart->getTaxedAmount($country, false, null))
            ->setVirtual($propelCart->isVirtual());

        return parent::normalize($object, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Cart::class => false,
        ];
    }
}
