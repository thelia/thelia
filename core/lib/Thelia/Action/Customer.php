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
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\Customer\CustomerGuestCreateEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\Customer\CustomerResetPasswordEvent;
use Thelia\Core\Event\LostPasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Service\CartContext;
use Thelia\Domain\Cart\Service\CartRetriever;
use Thelia\Domain\Customer\Exception\CustomerException;
use Thelia\Domain\Customer\Exception\InvalidPasswordResetTokenException;
use Thelia\Domain\Customer\Service\CustomerCodeManager;
use Thelia\Domain\Customer\Service\CustomerTitleService;
use Thelia\Domain\Customer\Service\PasswordResetService;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\LangQuery;

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
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $dispatcher,
        protected CustomerTitleService $customerTitleService,
        protected LangService $langService,
        protected CartRetriever $cartRetriever,
        protected CartContext $cartContext,
        protected PasswordResetService $passwordResetService,
        protected CustomerCodeManager $customerCodeManager,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function create(CustomerCreateOrUpdateEvent $event): void
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

        $this->dispatcher->dispatch(
            new CustomerEvent($customer),
            TheliaEvents::SEND_ACCOUNT_CONFIRMATION_EMAIL
        );
    }

    public function createMinimal(CustomerCreateOrUpdateMinimalEvent $event): void
    {
        $customer = new CustomerModel();

        $customer->createOrUpdateWithoutAddress(
            titleId: $event->getTitle(),
            firstname: $event->getFirstname(),
            lastname: $event->getLastname(),
            email: $event->getEmail(),
            plainPassword: $event->getPassword(),
            forceEmailUpdate: $event->isForceEmailUpdate(),
            langId: $event->getLangId(),
            reseller: $event->isReseller(),
            sponsor: $event->getSponsor(),
            discount: $event->getDiscount(),
            ref: $event->getRef(),
            enabled: $event->isEnabled()
        );

        $this->dispatcher->dispatch(
            new CustomerEvent($customer),
            TheliaEvents::SEND_ACCOUNT_CONFIRMATION_EMAIL,
        );

        $event->setCustomer($customer);
    }

    /**
     * Open the passwordless account that carries an order placed without one.
     *
     * Nothing is mailed: the visitor asked for no account, so an activation code or a
     * welcome message would announce one they never wanted. What they hear about is
     * their order, through the order confirmation the checkout already sends.
     *
     * @throws PropelException
     */
    public function createGuest(CustomerGuestCreateEvent $event): void
    {
        $titleId = $event->getTitle() ?? $this->customerTitleService->getDefaultCustomerTitle()?->getId();

        if (null === $titleId) {
            throw new CustomerException('No customer title was given, and the shop has no default one to fall back on.');
        }

        $customer = new CustomerModel();
        $customer
            ->setIsGuest(1)
            ->setTitleId($titleId)
            ->setFirstname((string) $event->getFirstname())
            ->setLastname((string) $event->getLastname())
            ->setEmail($event->getEmail())
            ->setEnable(0);

        if (null !== $event->getLangId()) {
            $customer->setLangId($event->getLangId());
        }

        $customer->save();

        $event->setCustomer($customer);
    }

    /**
     * Send the customer what is needed to activate the account.
     *
     * This mails the activation code, which is the only mechanism the shipped
     * front office knows: the `customer_confirmation` mail this used to send
     * carries the Thelia 2 activation link, whose `/customer/confirm/{token}`
     * route belongs to the Front module and disappears with it.
     *
     * A shop that does not confirm addresses sends nothing from here, as it always has.
     * The guest conversion does not go through this listener: it calls the code manager
     * itself, so its activation code is mailed on every shop whatever the setting says.
     *
     * @throws PropelException
     */
    public function customerConfirmationEmail(CustomerEvent $event): void
    {
        $customer = $event->getModel();

        if (!ConfigQuery::isCustomerEmailConfirmationEnable() || null === $customer->getConfirmationToken()) {
            return;
        }

        // A fresh code on every send: the account was just created, or its owner
        // asked for the code again, and the mail must carry the code the database
        // will accept, not the one of an earlier attempt.
        $this->customerCodeManager->createCodeAndSendIt($customer);
    }

    /**
     * @throws PropelException
     */
    public function modify(CustomerCreateOrUpdateEvent $event): void
    {
        $plainPassword = $event->getPassword();

        $customer = $event->getCustomer();

        $emailChanged = $customer->getEmail() !== $event->getEmail();

        $this->createOrUpdateCustomer($customer, $event);

        if ($event->getNotifyCustomerOfAccountModification()
            && ((null !== $plainPassword && '' !== $plainPassword && '0' !== $plainPassword) || $emailChanged)) {
            $this->mailer->sendEmailToCustomer('customer_account_changed', $customer, ['password' => $plainPassword]);
        }
    }

    /**
     * @throws PropelException
     */
    public function updateProfile(CustomerCreateOrUpdateEvent $event): void
    {
        $customer = $event->getCustomer();

        if (null !== $event->getTitle()) {
            $customer->setTitleId((int) $event->getTitle());
        }

        if (null !== $event->getFirstname()) {
            $customer->setFirstname($event->getFirstname());
        }

        if (null !== $event->getLastname()) {
            $customer->setLastname($event->getLastname());
        }

        if (null !== $event->getEmail()) {
            $customer->updateEmail($event->getEmail(), $event->getEmailUpdateAllowed());
        }

        if (null !== $event->getPassword()) {
            $customer->setPassword($event->getPassword());
        }

        if (null !== $event->getReseller()) {
            $customer->setReseller((int) $event->getReseller());
        }

        if (null !== $event->getSponsor()) {
            $customer->setSponsor($event->getSponsor());
        }

        if (null !== $event->getDiscount()) {
            $customer->setDiscount((string) $event->getDiscount());
        }

        if (null !== $event->getLangId()) {
            $customer->setLangId((int) $event->getLangId());
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
        if (null === $customer) {
            $customer = new CustomerModel();
        }
        $customer?->createOrUpdate(
            $event->getTitle() ?? $this->customerTitleService->getDefaultCustomerTitle()?->getId(),
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
            siret: $event->getSiret(),
            vatNumber: $event->getVatNumber(),
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
        if ($this->requestStack instanceof RequestStack
            && $customer->getLangId() !== null
            && (null !== $lang = LangQuery::create()->findPk($customer->getLangId()))
        ) {
            $this->langService->setLang($lang);
        }
        $cart = $this->cartRetriever->fromSession();
        if (null !== $cart) {
            $cart->setCustomerId($customer->getId());
            $cart->save();
            $this->cartContext->addCartSession($cart);
        }
    }

    /**
     * Perform user logout. The user is redirected to the provided view, if any.
     */
    public function logout(/* @noinspection PhpUnusedParameterInspection */ ActionEvent $event): void
    {
        // The remember-me cookie is checked against the token stored on the account: the
        // account forgets it here, so a copy of the cookie kept elsewhere stops working.
        $customer = $this->securityContext->getCustomerUser();

        if ($customer instanceof CustomerModel) {
            $customer->setRememberMeToken(null)->save();
        }

        $this->securityContext->clearCustomerUser();
    }

    /**
     * Mail the owner of the given address a link to choose a new password.
     *
     * The address comes from whoever asked, so this must not act on the account behind
     * it, and must answer the same way whether or not it names one.
     *
     * @throws PropelException
     */
    public function lostPassword(LostPasswordEvent $event): void
    {
        $this->passwordResetService->requestResetLink((string) $event->getEmail());
    }

    /**
     * Give the account named by a password reset link the password its owner chose.
     *
     * @throws InvalidPasswordResetTokenException when the link can no longer be used
     * @throws PropelException
     */
    public function resetPassword(CustomerResetPasswordEvent $event): void
    {
        $event->setCustomer(
            $this->passwordResetService->resetPassword($event->getToken(), $event->getPassword()),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CUSTOMER_CREATEACCOUNT => ['create', 128],
            TheliaEvents::CREATE_CUSTOMER_MINIMAL => ['createMinimal', 128],
            TheliaEvents::CUSTOMER_GUEST_CREATE => ['createGuest', 128],
            TheliaEvents::CUSTOMER_UPDATEACCOUNT => ['modify', 128],
            TheliaEvents::CUSTOMER_UPDATEPROFILE => ['updateProfile', 128],
            TheliaEvents::CUSTOMER_LOGOUT => ['logout', 128],
            TheliaEvents::CUSTOMER_LOGIN => ['login', 128],
            TheliaEvents::CUSTOMER_DELETEACCOUNT => ['delete', 128],
            TheliaEvents::LOST_PASSWORD => ['lostPassword', 128],
            TheliaEvents::CUSTOMER_RESET_PASSWORD => ['resetPassword', 128],
            TheliaEvents::SEND_ACCOUNT_CONFIRMATION_EMAIL => ['customerConfirmationEmail', 128],
        ];
    }
}
