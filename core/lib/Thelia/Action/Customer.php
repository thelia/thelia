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

namespace Thelia\Action;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\LostPasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\CustomerException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\LangQuery;
use Thelia\Service\Model\CustomerService;
use Thelia\Service\Model\LangService;
use Thelia\Tools\Password;

/**
 * customer class where all actions are managed.
 *
 * Class Customer
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Customer extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        protected SecurityContext $securityContext,
        protected MailerFactory $mailer,
        protected CustomerService $customerService,
        protected LangService $langService,
        protected ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function create(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $customer = new CustomerModel();

        $plainPassword = $event->getPassword();

        $this->createOrUpdateCustomer($customer, $event);

        if ($event->getNotifyCustomerOfAccountCreation()) {
            $this->mailer->sendEmailToCustomer(
                'customer_account_created',
                $customer,
                ['password' => $plainPassword],
            );
        }

        $dispatcher->dispatch(
            new CustomerEvent($customer),
            TheliaEvents::SEND_ACCOUNT_CONFIRMATION_EMAIL,
        );
    }

    public function customerConfirmationEmail(
        CustomerEvent $event,
        $eventName,
        EventDispatcherInterface $dispatcher,
    ): void {
        $customer = $event->getModel();

        if (ConfigQuery::isCustomerEmailConfirmationEnable() && null !== $customer->getConfirmationToken()) {
            $this->mailer->sendEmailToCustomer(
                'customer_confirmation',
                $customer,
                ['customer_id' => $customer->getId()],
            );
        }
    }

    /**
     * @throws PropelException
     */
    public function modify(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $plainPassword = $event->getPassword();

        $customer = $event->getCustomer();

        $emailChanged = $customer->getEmail() !== $event->getEmail();

        $this->createOrUpdateCustomer($customer, $event);

        if ($event->getNotifyCustomerOfAccountModification() && (null !== $plainPassword && '' !== $plainPassword && '0' !== $plainPassword || $emailChanged)) {
            $this->mailer->sendEmailToCustomer('customer_account_changed', $customer, ['password' => $plainPassword]);
        }
    }

    /**
     * @throws PropelException
     */
    public function updateProfile(
        CustomerCreateOrUpdateEvent $event,
        $eventName,
        EventDispatcherInterface $dispatcher,
    ): void {
        $customer = $event->getCustomer();

        if (null !== $event->getTitle()) {
            $customer->setTitleId($event->getTitle());
        }

        if (null !== $event->getFirstname()) {
            $customer->setFirstname($event->getFirstname());
        }

        if (null !== $event->getLastname()) {
            $customer->setLastname($event->getLastname());
        }

        if (null !== $event->getEmail()) {
            $customer->setEmail($event->getEmail(), $event->getEmailUpdateAllowed());
        }

        if (null !== $event->getPassword()) {
            $customer->setPassword($event->getPassword());
        }

        if (null !== $event->getReseller()) {
            $customer->setReseller($event->getReseller());
        }

        if (null !== $event->getSponsor()) {
            $customer->setSponsor($event->getSponsor());
        }

        if (null !== $event->getDiscount()) {
            $customer->setDiscount($event->getDiscount());
        }

        if (null !== $event->getLangId()) {
            $customer->setLangId($event->getLangId());
        }

        $customer->save();

        $event->setCustomer($customer);
    }

    /**
     * @throws PropelException
     */
    public function delete(CustomerEvent $event): void
    {
        if (null !== $customer = $event->getModel()) {
            if (true === $customer->hasOrder()) {
                throw new CustomerException(Translator::getInstance()->trans('Impossible to delete a customer who already have orders'));
            }

            $customer->delete();
        }
    }

    /**
     * @throws PropelException
     */
    private function createOrUpdateCustomer(
        ?CustomerModel $customer,
        CustomerCreateOrUpdateEvent $event,
    ): void {
        $customer?->createOrUpdate(
            $event->getTitle() ?? $this->customerService->getDefaultCustomerTitle()?->getId(),
            $event->getFirstname(),
            $event->getLastname(),
            $event->getAddress1(),
            $event->getAddress2(),
            $event->getAddress3(),
            $event->getPhone(),
            $event->getCellphone(),
            $event->getZipcode(),
            $event->getCity(),
            (int) $event->getCountry(),
            $event->getEmail(),
            $event->getPassword(),
            $event->getLangId(),
            $event->getReseller(),
            $event->getSponsor(),
            $event->getDiscount(),
            $event->getCompany(),
            $event->getRef(),
            $event->getEmailUpdateAllowed(),
            $event->getState(),
        );

        $event->setCustomer($customer);
    }

    public function login(CustomerLoginEvent $event): void
    {
        $customer = $event->getCustomer();
        if ($customer === null) {
            return;
        }
        if (method_exists($customer, 'clearDispatcher')) {
            $customer->clearDispatcher();
        }

        $this->securityContext->setCustomerUser($event->getCustomer());

        // Set the preferred customer language
        if ($this->requestStack instanceof RequestStack
            && $customer->getLangId() !== null
            && (null !== $lang = LangQuery::create()->findPk($customer->getLangId()))
        ) {
            $this->langService->setLang($lang);
        }
    }

    /**
     * Perform user logout. The user is redirected to the provided view, if any.
     */
    public function logout(/* @noinspection PhpUnusedParameterInspection */ ActionEvent $event): void
    {
        $this->securityContext->clearCustomerUser();
    }

    /**
     * @throws PropelException
     */
    public function lostPassword(LostPasswordEvent $event): void
    {
        if (null !== $customer = CustomerQuery::create()->filterByEmail($event->getEmail())->findOne()) {
            $password = Password::generateRandom(8);

            $customer
                ->setPassword($password)
                ->save();

            $this->mailer->sendEmailToCustomer('lost_password', $customer, ['password' => $password]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CUSTOMER_CREATEACCOUNT => ['create', 128],
            TheliaEvents::CUSTOMER_UPDATEACCOUNT => ['modify', 128],
            TheliaEvents::CUSTOMER_UPDATEPROFILE => ['updateProfile', 128],
            TheliaEvents::CUSTOMER_LOGOUT => ['logout', 128],
            TheliaEvents::CUSTOMER_LOGIN => ['login', 128],
            TheliaEvents::CUSTOMER_DELETEACCOUNT => ['delete', 128],
            TheliaEvents::LOST_PASSWORD => ['lostPassword', 128],
            TheliaEvents::SEND_ACCOUNT_CONFIRMATION_EMAIL => ['customerConfirmationEmail', 128],
        ];
    }
}
