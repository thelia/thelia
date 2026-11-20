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

namespace Thelia\Tests\Integration\Company;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Loop\Delivery;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Form\OrderDelivery;
use Thelia\Form\OrderPayment;
use Thelia\Model\Address;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;

/**
 * Every place that reads an address of the book to put it on an order asks what the customer
 * of the session may use: their own addresses and the ones shared with their company, never the
 * personal ones of a colleague or anything of another company. With nobody in the session the
 * address is read as it always was.
 */
final class AddressReadsTest extends AddressTestCase
{
    /** @var array<string, mixed> */
    private array $world = [];

    private Address $shared;

    private Address $personalOfTheCreator;

    private Address $ownOfTheColleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->world = $this->twoCompanies();
        $this->shared = $this->addressOf($this->world['creator'], $this->world['first'], 'Warehouse');
        $this->personalOfTheCreator = $this->addressOf($this->world['creator'], null, 'Home');
        $this->ownOfTheColleague = $this->addressOf($this->world['colleague'], null, 'Flat');
    }

    protected function tearDown(): void
    {
        $this->session()->clearCustomerUser();

        parent::tearDown();
    }

    public function testTheCartAcceptsAsDeliveryAddressWhatItsCustomerMayUse(): void
    {
        $facade = $this->getService(ShippingFacade::class);
        $cart = $this->cartOf($this->world['colleague']);

        $facade->chooseDeliveryAddress($cart, (int) $this->ownOfTheColleague->getId());
        $facade->chooseDeliveryAddress($cart, (int) $this->shared->getId());

        self::assertSame($this->shared->getId(), $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId());
    }

    public function testTheCartRefusesAsDeliveryAddressTheOnesOfAColleagueAndOfAnotherCompany(): void
    {
        $facade = $this->getService(ShippingFacade::class);

        foreach ([
            [$this->world['colleague'], $this->personalOfTheCreator],
            [$this->world['buyerOfSecond'], $this->shared],
        ] as [$customer, $address]) {
            try {
                $facade->chooseDeliveryAddress($this->cartOf($customer), (int) $address->getId());
                self::fail('The address of someone else was chosen.');
            } catch (\InvalidArgumentException $refusal) {
                self::assertSame(\sprintf('Address #%d not found', $address->getId()), $refusal->getMessage());
            }
        }
    }

    public function testTheCartWithoutCustomerReadsTheAddressAsItAlwaysDid(): void
    {
        $cart = $this->fixtures->cart();

        $this->getService(ShippingFacade::class)->chooseDeliveryAddress($cart, (int) $this->personalOfTheCreator->getId());

        self::assertSame($this->personalOfTheCreator->getId(), $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId());
    }

    public function testTheCarriersOfAnAddressAreAskedForTheSharedOnesOfTheCompanyAndNotForTheOthers(): void
    {
        $facade = $this->getService(ShippingFacade::class);
        $cart = $this->cartOf($this->world['colleague']);

        self::assertSame($this->shared->getId(), $facade->addressOfTheCartCustomer($cart, (int) $this->shared->getId())?->getId());
        self::assertNull($facade->addressOfTheCartCustomer($cart, (int) $this->personalOfTheCreator->getId()));
        self::assertNull($facade->addressOfTheCartCustomer($this->cartOf($this->world['buyerOfSecond']), (int) $this->shared->getId()));
    }

    public function testTheDeliveryFormRefusesWhatTheCustomerOfTheSessionMayNotUse(): void
    {
        $this->signIn($this->world['colleague']);

        self::assertNull($this->violationOf('thelia.order.delivery', 'verifyDeliveryAddress', $this->shared));
        self::assertNull($this->violationOf('thelia.order.delivery', 'verifyDeliveryAddress', $this->ownOfTheColleague));
        self::assertSame('Address ID not found', $this->violationOf('thelia.order.delivery', 'verifyDeliveryAddress', $this->personalOfTheCreator));

        $this->signIn($this->world['buyerOfSecond']);

        self::assertSame('Address ID not found', $this->violationOf('thelia.order.delivery', 'verifyDeliveryAddress', $this->shared));
    }

    public function testTheDeliveryFormReadsTheAddressAsItAlwaysDidWithNobodyInTheSession(): void
    {
        self::assertNull($this->violationOf('thelia.order.delivery', 'verifyDeliveryAddress', $this->personalOfTheCreator));
    }

    public function testThePaymentFormRefusesWhatTheCustomerOfTheSessionMayNotUse(): void
    {
        $this->signIn($this->world['colleague']);

        self::assertNull($this->violationOf('thelia.order.payment', 'verifyInvoiceAddress', $this->shared));
        self::assertSame('Address ID not found', $this->violationOf('thelia.order.payment', 'verifyInvoiceAddress', $this->personalOfTheCreator));

        $this->signIn($this->world['buyerOfSecond']);

        self::assertSame('Address ID not found', $this->violationOf('thelia.order.payment', 'verifyInvoiceAddress', $this->shared));
    }

    public function testThePaymentFormReadsTheAddressAsItAlwaysDidWithNobodyInTheSession(): void
    {
        self::assertNull($this->violationOf('thelia.order.payment', 'verifyInvoiceAddress', $this->personalOfTheCreator));
    }

    public function testTheDeliveryLoopQuotesOnlyForAnAddressTheCustomerOfTheSessionMayUse(): void
    {
        $this->signIn($this->world['colleague']);

        self::assertSame($this->shared->getId(), $this->deliveryLoopAddress($this->shared)?->getId());
        self::assertNull($this->deliveryLoopAddress($this->personalOfTheCreator));

        $this->signIn($this->world['buyerOfSecond']);

        self::assertNull($this->deliveryLoopAddress($this->shared));
    }

    public function testTheDeliveryLoopReadsTheAddressAsItAlwaysDidWithNobodyInTheSession(): void
    {
        self::assertSame($this->personalOfTheCreator->getId(), $this->deliveryLoopAddress($this->personalOfTheCreator)?->getId());
    }

    public function testTheCheckoutSendsBackToTheDeliveryStepWhenTheAddressChosenIsNotTheCustomers(): void
    {
        $this->signIn($this->world['colleague']);

        self::assertNull($this->redirectionOfTheDeliveryGuard($this->shared));
        self::assertNull($this->redirectionOfTheDeliveryGuard($this->ownOfTheColleague));
        self::assertNotNull($this->redirectionOfTheDeliveryGuard($this->personalOfTheCreator));

        $this->signIn($this->world['buyerOfSecond']);

        self::assertNotNull($this->redirectionOfTheDeliveryGuard($this->shared));
    }

    public function testTheCheckoutSendsBackToThePaymentStepWhenTheInvoiceAddressChosenIsNotTheCustomers(): void
    {
        $this->signIn($this->world['colleague']);

        self::assertNull($this->redirectionOfTheInvoiceGuard($this->shared));
        self::assertNotNull($this->redirectionOfTheInvoiceGuard($this->personalOfTheCreator));

        $this->signIn($this->world['buyerOfSecond']);

        self::assertNotNull($this->redirectionOfTheInvoiceGuard($this->shared));
    }

    public function testTheCheckoutGuardsReadTheAddressAsTheyAlwaysDidWithNobodyInTheSession(): void
    {
        self::assertNull($this->redirectionOfTheDeliveryGuard($this->personalOfTheCreator));
        self::assertNull($this->redirectionOfTheInvoiceGuard($this->personalOfTheCreator));
    }

    private function signIn(Customer $customer): void
    {
        $this->session()->setCustomerUser($customer);
        $this->getService('services_resetter')->reset();
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    /**
     * The message a validation callback of a form adds, null when it accepts the address.
     */
    private function violationOf(string $formName, string $callback, Address $address): ?string
    {
        $form = $this->getService(TheliaFormFactory::class)->createForm($formName);
        self::assertTrue($form instanceof OrderDelivery || $form instanceof OrderPayment);

        $messages = [];
        $context = $this->createStub(ExecutionContextInterface::class);
        $context->method('addViolation')->willReturnCallback(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $form->$callback($address->getId(), $context);

        return $messages[0] ?? null;
    }

    private function deliveryLoopAddress(Address $address): ?Address
    {
        $loop = new DeliveryLoopUnderTest(addressAccess: $this->access);
        $loop->init(
            static::getContainer(),
            static::getContainer()->get('request_stack'),
            $this->getService(EventDispatcherInterface::class),
            $this->getService(SecurityContext::class),
            Translator::getInstance(),
            [],
            'test',
        );
        $loop->initializeArgs(['type' => 'delivery', 'address' => $address->getId()]);

        $found = $loop->addressRead();

        return $found instanceof Address ? $found : null;
    }

    private function redirectionOfTheDeliveryGuard(Address $address): ?RedirectException
    {
        $this->session()->setOrder($this->orderChoosing($address, $address));

        return $this->redirectionOf(static fn (ReadingFrontController $controller) => $controller->guardValidDelivery());
    }

    private function redirectionOfTheInvoiceGuard(Address $address): ?RedirectException
    {
        $this->session()->setOrder($this->orderChoosing($address, $address));

        return $this->redirectionOf(static fn (ReadingFrontController $controller) => $controller->guardValidInvoice());
    }

    private function orderChoosing(Address $delivery, Address $invoice): Order
    {
        $moduleId = ModuleQuery::create()->findOne()?->getId();
        self::assertNotNull($moduleId);

        $addresses = $this->getService(CartAddressService::class);
        $cart = $this->fixtures->cart($this->world['colleague']);
        $cart
            ->setAddressDeliveryId($addresses->getOrCreateCartAddressFromAddress($delivery, null)->getId())
            ->setAddressInvoiceId($addresses->getOrCreateCartAddressFromAddress($invoice, null)->getId())
            ->save($this->getPropelConnection());

        return (new Order())
            ->setCartId($cart->getId())
            ->setDeliveryModuleId($moduleId)
            ->setPaymentModuleId($moduleId);
    }

    private function redirectionOf(callable $guard): ?RedirectException
    {
        $container = static::getContainer();
        $controller = new ReadingFrontController();
        $controller->container = $container;
        $controller->securityContext = $container->get(SecurityContext::class);
        $controller->requestStack = $container->get('request_stack');
        $controller->addressAccess = $this->access;

        try {
            $guard($controller);
        } catch (RedirectException $redirect) {
            return $redirect;
        }

        return null;
    }
}

final class ReadingFrontController extends BaseFrontController
{
    public function guardValidDelivery(): void
    {
        $this->checkValidDelivery();
    }

    public function guardValidInvoice(): void
    {
        $this->checkValidInvoice();
    }
}

final class DeliveryLoopUnderTest extends Delivery
{
    public function addressRead(): mixed
    {
        return $this->getDeliveryAddress();
    }
}
