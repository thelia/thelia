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

namespace Thelia\Domain\Payment\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Api\Bridge\Propel\Event\PaymentModuleOptionEvent;
use Thelia\Api\Resource\PaymentModuleOptionGroup;
use Thelia\Api\Resource\PaymentModuleStoredMethodOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;

/**
 * Offers the buyer the payment methods they stored through a module, next to that
 * module, as the option group "stored_methods": one option per method still usable,
 * the default one first, then the option of a new method.
 *
 * The buyer is the customer of the cart. The session is asked only for a cart that
 * names nobody yet, and only when there is one: the listing runs without a browser too.
 * A guest stores nothing, and a module that does not offer stored methods, or no
 * longer does, gets no group; neither does a buyer with no usable method, who has
 * nothing to choose between.
 */
final readonly class StoredPaymentMethodOptionsListener
{
    public const GROUP_CODE = 'stored_methods';

    public function __construct(
        private CustomerPaymentMethodService $customerPaymentMethods,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MODULE_PAYMENT_GET_OPTIONS)]
    public function offerTheStoredMethods(PaymentModuleOptionEvent $event): void
    {
        $customer = $event->getCart()?->getCustomer() ?? $this->securityContext->getCustomerUser();

        if (!$customer instanceof Customer || $customer->isGuest()) {
            return;
        }

        $module = $event->getModule();

        if (!$this->customerPaymentMethods->offersStoredMethods($module)) {
            return;
        }

        $today = new \DateTimeImmutable('today');
        $usableMethods = array_filter(
            $this->customerPaymentMethods->listFor($customer, throughModule: $module),
            static fn (CustomerPaymentMethod $method): bool => !$method->isExpiredOn($today),
        );

        if ([] === $usableMethods) {
            return;
        }

        $group = (new PaymentModuleOptionGroup())
            ->setCode(self::GROUP_CODE)
            ->setTitle($this->translator->trans('Saved payment methods'))
            ->setMinimumSelectedOptions(1)
            ->setMaximumSelectedOptions(1);

        foreach ($usableMethods as $method) {
            $group->appendPaymentModuleOption(PaymentModuleStoredMethodOption::ofStoredMethod($method));
        }

        $group->appendPaymentModuleOption(PaymentModuleStoredMethodOption::ofANewMethod(
            $this->translator->trans('Use a new payment method'),
        ));

        $event->appendPaymentModuleOptionGroups($group);
    }
}
