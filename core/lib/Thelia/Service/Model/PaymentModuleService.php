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

namespace Thelia\Service\Model;

use OpenApi\Events\OpenApiEvents;
use OpenApi\Events\PaymentModuleOptionEvent;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Resource\ModuleI18n;
use Thelia\Api\Resource\PaymentModule;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Cart;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

readonly class PaymentModuleService
{
    public function __construct(
        private Request $request,
        private EventDispatcherInterface $dispatcher,
        private ContainerInterface $container,
    ) {
    }

    public function getPaymentModules($moduleId = null): array
    {
        $request = $this->request;
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

        $modules = $moduleQuery->find();

        // Return formatted valid payment
        return
            array_map(
                fn ($module): PaymentModule => $this->getPaymentModule($dispatcher, $module, $cart, $lang),
                iterator_to_array($modules)
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
            TheliaEvents::MODULE_PAYMENT_IS_VALID
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
                OpenApiEvents::MODULE_PAYMENT_GET_OPTIONS
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
