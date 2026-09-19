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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Cart as CartResource;
use Thelia\Api\Resource\CheckoutDeliveryAddressInput;
use Thelia\Api\Resource\CheckoutDeliveryModuleInput;
use Thelia\Api\Resource\CheckoutInvoiceAddressInput;
use Thelia\Api\Resource\CheckoutPaymentModuleInput;
use Thelia\Api\Security\CheckoutCartLocator;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Exception\GuestCheckoutNotAllowedException;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * The four choices a buyer makes, each posted on its own and each answered with the cart
 * as the shop now sees it — postage included, since choosing a carrier or an address is
 * what moves it.
 *
 * Nothing about money is taken from the body: the operations carry an address id or a
 * module id and nothing else, and the amounts come back computed.
 *
 * A choice the cart already holds is not made again. The domain has no reason to
 * short-circuit — a theme only ever posts a step the buyer just filled in — but a client
 * of the API replays a whole checkout, and re-quoting the postage means asking every
 * carrier module for a price, sometimes over the network.
 */
final readonly class CheckoutSelectionProcessor implements ProcessorInterface
{
    private const UNKNOWN_ADDRESS_MESSAGE = 'No such address.';

    public function __construct(
        private CheckoutCartLocator $cartLocator,
        private CheckoutFacade $checkoutFacade,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws PropelException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $cart = $this->cartLocator->ownedCart($uriVariables);

        try {
            match (true) {
                $data instanceof CheckoutDeliveryAddressInput => $this->selectDeliveryAddress($cart, $data),
                $data instanceof CheckoutInvoiceAddressInput => $this->selectInvoiceAddress($cart, $data),
                $data instanceof CheckoutDeliveryModuleInput => $this->selectDeliveryModule($cart, $data),
                $data instanceof CheckoutPaymentModuleInput => $this->selectPaymentModule($cart, $data),
                default => throw new UnprocessableEntityHttpException('Unsupported checkout selection.'),
            };
        } catch (GuestCheckoutNotAllowedException $refusal) {
            throw new AccessDeniedHttpException($refusal->getMessage(), $refusal);
        }

        // The selection wrote through events and services that hold their own instances
        // of these rows: the postage, the cart addresses and the totals all move behind
        // the object this method is holding, and the caller is answered with the cart as
        // the shop now sees it.
        $cart->clearAllReferences();
        $cart->reload(true);

        return $this->transformer->modelToResource(
            resourceClass: CartResource::class,
            propelModel: $cart,
            context: $operation->getNormalizationContext() ?? [],
        );
    }

    /**
     * @throws PropelException
     */
    private function selectDeliveryAddress(Cart $cart, CheckoutDeliveryAddressInput $input): void
    {
        $address = $this->addressOfTheAccount($cart, $input->addressId);

        if ($this->isAlreadyCopiedOnto($cart->getCartAddressRelatedByAddressDeliveryId(), $address)) {
            return;
        }

        $this->checkoutFacade->selectDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $address->getId()));
    }

    /**
     * @throws PropelException
     */
    private function selectInvoiceAddress(Cart $cart, CheckoutInvoiceAddressInput $input): void
    {
        $address = $this->addressOfTheAccount($cart, $input->addressId);

        if ($this->isAlreadyCopiedOnto($cart->getCartAddressRelatedByAddressInvoiceId(), $address)) {
            return;
        }

        $this->checkoutFacade->selectInvoiceAddress(new CheckoutDTO($cart, invoiceAddressId: $address->getId()));
    }

    /**
     * @throws PropelException
     */
    private function selectDeliveryModule(Cart $cart, CheckoutDeliveryModuleInput $input): void
    {
        $module = $this->activatedModule($input->deliveryModuleId, BaseModule::DELIVERY_MODULE_TYPE, 'delivery');

        if ($cart->getDeliveryModuleId() === $module->getId()) {
            return;
        }

        $this->checkoutFacade->selectDeliveryModule(new CheckoutDTO($cart, deliveryModuleId: $module->getId()));
    }

    /**
     * @throws PropelException
     */
    private function selectPaymentModule(Cart $cart, CheckoutPaymentModuleInput $input): void
    {
        $module = $this->activatedModule($input->paymentModuleId, BaseModule::PAYMENT_MODULE_TYPE, 'payment');

        if ($cart->getPaymentModuleId() === $module->getId()) {
            return;
        }

        $this->checkoutFacade->selectPaymentModule(new CheckoutDTO($cart, paymentModuleId: $module->getId()));
    }

    /**
     * An address of the authenticated account, or nothing at all.
     *
     * The selection service of the domain answers an address it does not recognise by
     * doing nothing, which is safe and silent: the caller is told its choice was taken
     * and the cart still ships where it did. Refusing here is what makes the answer
     * honest, and refusing with the same sentence for an address that does not exist and
     * for one that belongs to somebody else is what keeps it from being a directory of
     * the addresses of the shop.
     *
     * @throws PropelException
     */
    private function addressOfTheAccount(Cart $cart, ?int $addressId): Address
    {
        if (null === $addressId || $addressId <= 0) {
            throw new UnprocessableEntityHttpException('An address identifier is required.');
        }

        $address = AddressQuery::create()
            ->filterByCustomerId($cart->getCustomerId())
            ->filterById($addressId)
            ->findOne();

        if (!$address instanceof Address) {
            throw new NotFoundHttpException(self::UNKNOWN_ADDRESS_MESSAGE);
        }

        return $address;
    }

    /**
     * @throws PropelException
     */
    private function activatedModule(?int $moduleId, int $moduleType, string $what): Module
    {
        if (null === $moduleId || $moduleId <= 0) {
            throw new UnprocessableEntityHttpException(\sprintf('A %s module identifier is required.', $what));
        }

        $module = ModuleQuery::create()
            ->filterById($moduleId)
            ->filterByType($moduleType)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->findOne();

        if (!$module instanceof Module) {
            throw new UnprocessableEntityHttpException(\sprintf('No such %s module.', $what));
        }

        return $module;
    }

    /**
     * Whether the cart already ships to, or is already billed to, this very address.
     *
     * The cart does not point at the address: it holds a copy of it, frozen in
     * `cart_address` at the moment the choice was made, so that an address edited — or
     * deleted — afterwards cannot rewrite where an order was sent. "The same choice" is
     * therefore not "the same id": an account that corrected its postcode has a copy
     * that no longer says what the address says, and skipping the selection would leave
     * the cart quoting postage for a place the buyer has moved away from.
     *
     * Every field the copy is made of is compared, rather than a timestamp: the two rows
     * are saved a moment apart, and a comparison of dates with a second of resolution
     * would call a stale copy fresh.
     */
    private function isAlreadyCopiedOnto(?CartAddress $copy, Address $address): bool
    {
        if (!$copy instanceof CartAddress || $copy->getAddressId() !== $address->getId()) {
            return false;
        }

        return $this->copiedFieldsOf($copy) === $this->copiedFieldsOf($address);
    }

    /**
     * Everything `cart_address` copies from `address`, read off whichever of the two is
     * handed over.
     *
     * One list rather than one per class: two lists drift, and a field added to the copy
     * but forgotten in one of them makes "the same address" quietly true for an address
     * that changed in that very field. The only getter the two rows disagree on is the
     * title, so it is the only one named twice.
     *
     * @return array<string, mixed>
     */
    private function copiedFieldsOf(CartAddress|Address $address): array
    {
        return [
            'titleId' => $address instanceof CartAddress ? $address->getCustomerTitleId() : $address->getTitleId(),
            'company' => $address->getCompany(),
            'siret' => $address->getSiret(),
            'vatNumber' => $address->getVatNumber(),
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'address1' => $address->getAddress1(),
            'address2' => $address->getAddress2(),
            'address3' => $address->getAddress3(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'phone' => $address->getPhone(),
            'cellphone' => $address->getCellphone(),
            'countryId' => $address->getCountryId(),
            'stateId' => $address->getStateId(),
        ];
    }
}
