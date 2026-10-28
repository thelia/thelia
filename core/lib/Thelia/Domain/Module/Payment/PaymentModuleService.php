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

namespace Thelia\Domain\Module\Payment;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\PaymentModuleOptionEvent;
use Thelia\Api\Resource\ModuleI18n;
use Thelia\Api\Resource\PaymentModule;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Model\Cart;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

readonly class PaymentModuleService
{
    public function __construct(
        private RequestStack $requestStack,
        private EventDispatcherInterface $dispatcher,
        private ContainerInterface $container,
        private CustomerGroupPaymentPolicy $customerGroupPaymentPolicy,
    ) {
    }

    public function getPaymentModules($moduleId = null): array
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();
        if (null === $request) {
            return [];
        }
        $dispatcher = $this->dispatcher;
        $cart = $request->getSession()->getSessionCart($dispatcher);
        $lang = $request->getSession()->getLang();

        $moduleQuery = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->orderByPosition();

        if (null !== $moduleId) {
            $moduleQuery->filterById($moduleId);
        }

        $customer = $cart->getCustomer();

        // A module the customer's group is shut out of is not offered at all, whatever
        // the listeners of the event would answer.
        $modules = array_filter(
            iterator_to_array($moduleQuery->find()),
            fn (Module $module): bool => $this->customerGroupPaymentPolicy->isAllowed((int) $module->getId(), $customer),
        );

        // Return formatted valid payment
        return
            array_map(
                fn ($module): PaymentModule => $this->getPaymentModule($dispatcher, $module, $cart, $lang),
                array_values($modules),
            );
    }

    /**
     * @throws PropelException
     */
    protected function getPaymentModule(
        EventDispatcherInterface $dispatcher,
        Module $paymentModule,
        Cart $cart,
        Lang $lang,
    ): PaymentModule {
        $paymentModule->setLocale($lang->getLocale());
        $moduleInstance = $paymentModule->getPaymentModuleInstance($this->container);

        $isValidPaymentEvent = new IsValidPaymentEvent($moduleInstance, $cart);
        $dispatcher->dispatch(
            $isValidPaymentEvent,
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
        );

        /** @var PaymentModule $paymentModule */
        $paymentModuleApi = new PaymentModule();

        $paymentModuleApi->setId($paymentModule->getId());

        $paymentModuleApi->setValid($isValidPaymentEvent->isValidModule())
            ->setCode($moduleInstance->getCode())
            ->setMinimumAmount($isValidPaymentEvent->getMinimumAmount())
            ->setMaximumAmount($isValidPaymentEvent->getMaximumAmount());

        if ($isValidPaymentEvent->isValidModule()) {
            $paymentModuleOptionEvent = new PaymentModuleOptionEvent($paymentModule, $cart);

            $dispatcher->dispatch(
                $paymentModuleOptionEvent,
                TheliaEvents::MODULE_PAYMENT_GET_OPTIONS,
            );

            $paymentModuleApi
                ->setOptionGroups($paymentModuleOptionEvent->getPaymentModuleOptionGroups());
        }

        foreach ($paymentModule->getModuleI18ns() as $i18n) {
            $paymentModuleApi->addI18n(new ModuleI18n($i18n->toArray()), $i18n->getLocale());
        }

        return $paymentModuleApi;
    }
}
