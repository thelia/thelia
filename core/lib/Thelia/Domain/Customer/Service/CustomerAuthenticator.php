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

namespace Thelia\Domain\Customer\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Customer\Exception\CustomerNotEnabledException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

readonly class CustomerAuthenticator
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private CustomerRememberMeService $customerRememberMeService,
    ) {
    }

    /**
     * @throws CustomerNotEnabledException
     */
    public function processLogin(Customer $customer): void
    {
        // The shop's confirmation setting decides, as it always has. A converted guest
        // does not slip through it: its row stays is_guest = 1 until the activation code
        // is answered, and a guest row is not something the user providers ever return,
        // so it never reaches this point at all.
        if (ConfigQuery::isCustomerEmailConfirmationEnable() && !$customer->getEnable()) {
            throw new CustomerNotEnabledException(Translator::getInstance()->trans('Customer account is disabled'));
        }

        $this->eventDispatcher->dispatch(new CustomerLoginEvent($customer), TheliaEvents::CUSTOMER_LOGIN);
    }

    public function processLogout(): void
    {
        $this->eventDispatcher->dispatch(new DefaultActionEvent(), TheliaEvents::CUSTOMER_LOGOUT);
        $this->customerRememberMeService->clearRememberMeCookie($this->customerRememberMeService->getRememberMeCookieName());
    }
}
