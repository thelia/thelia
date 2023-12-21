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

namespace Thelia\Action;

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
    /** @var SecurityContext */
    protected $securityContext;

    /** @var MailerFactory */
    protected $mailer;

    /** @var RequestStack */
    protected $requestStack;

    public function __construct(SecurityContext $securityContext, MailerFactory $mailer, RequestStack $requestStack = null)
    {
        $this->securityContext = $securityContext;
        $this->mailer = $mailer;
        $this->requestStack = $requestStack;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function create(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $customer = new CustomerModel();

        $plainPassword = $event->getPassword();

        $this->createOrUpdateCustomer($customer, $event, $dispatcher);

        if ($event->getNotifyCustomerOfAccountCreation()) {
            $this->mailer->sendEmailToCustomer(
                'customer_account_created',
                $customer,
                ['password' => $plainPassword]
            );
        }

        $dispatcher->dispatch(
            new CustomerEvent($customer),
            TheliaEvents::SEND_ACCOUNT_CONFIRMATION_EMAIL
        );
    }

    public function customerConfirmationEmail(CustomerEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $customer = $event->getModel();

        if (ConfigQuery::isCustomerEmailConfirmationEnable() && $customer->getConfirmationToken() !== null) {
            $this->mailer->sendEmailToCustomer(
                'customer_confirmation',
                $customer,
                ['customer_id' => $customer->getId()]
            );
        }
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function modify(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $plainPassword = $event->getPassword();

        $customer = $event->getCustomer();

        $emailChanged = $customer->getEmail() !== $event->getEmail();

        $this->createOrUpdateCustomer($customer, $event, $dispatcher);

        if ($event->getNotifyCustomerOfAccountModification() && (!empty($plainPassword) || $emailChanged)) {
            $this->mailer->sendEmailToCustomer('customer_account_changed', $customer, ['password' => $plainPassword]);
        }
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateProfile(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $customer = $event->getCustomer();

        if ($event->getTitle() !== null) {
            $customer->setTitleId($event->getTitle());
        }

        if ($event->getFirstname() !== null) {
            $customer->setFirstname($event->getFirstname());
        }

        if ($event->getLastname() !== null) {
            $customer->setLastname($event->getLastname());
        }

        if ($event->getEmail() !== null) {
            $customer->setEmail($event->getEmail(), $event->getEmailUpdateAllowed());
        }

        if ($event->getPassword() !== null) {
            $customer->setPassword($event->getPassword());
        }

        if ($event->getReseller() !== null) {
            $customer->setReseller($event->getReseller());
        }

        if ($event->getSponsor() !== null) {
            $customer->setSponsor($event->getSponsor());
        }

        if ($event->getDiscount() !== null) {
            $customer->setDiscount($event->getDiscount());
        }

        if ($event->getLangId() !== null) {
            $customer->setLangId($event->getLangId());
        }

        $customer->save();

        $event->setCustomer($customer);
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
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
     * @throws \Propel\Runtime\Exception\PropelException
     */
    private function createOrUpdateCustomer(CustomerModel $customer, CustomerCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $customer->createOrUpdate(
            $event->getTitle(),
            $event->getFirstname(),
            $event->getLastname(),
            $event->getAddress1(),
            $event->getAddress2(),
            $event->getAddress3(),
            $event->getPhone(),
            $event->getCellphone(),
            $event->getZipcode(),
            $event->getCity(),
            $event->getCountry(),
            $event->getEmail(),
            $event->getPassword(),
            $event->getLangId(),
            $event->getReseller(),
            $event->getSponsor(),
            $event->getDiscount(),
            $event->getCompany(),
            $event->getRef(),
            $event->getEmailUpdateAllowed(),
            $event->getState()
        );

        $event->setCustomer($customer);
    }

    public function login(CustomerLoginEvent $event): void
    {
        $customer = $event->getCustomer();

        if (method_exists($customer, 'clearDispatcher')) {
            $customer->clearDispatcher();
        }

        $this->securityContext->setCustomerUser($event->getCustomer());

        // Set the preferred customer language
        if (null !== $this->requestStack
            && !empty($customer->getLangId())
            && (null !== $lang = LangQuery::create()->findPk($customer->getLangId()))
        ) {
            $this->requestStack->getCurrentRequest()->getSession()->setLang($lang);
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
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function lostPassword(LostPasswordEvent $event): void
    {
        if (null !== $customer = CustomerQuery::create()->filterByEmail($event->getEmail())->findOne()) {
            $password = Password::generateRandom(8);

            $customer
                ->setPassword($password)
                ->save()
            ;

            $this->mailer->sendEmailToCustomer('lost_password', $customer, ['password' => $password]);
        }
    }

    public static function getSubscribedEvents()
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
