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

namespace Thelia\Tests\Http\Flexy;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Domain\Order\Reminder\UnpaidOrderPaymentLink;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * The link of a payment reminder hands the order back to the payment step: straight away
 * for a guest, after signing in for an account, never to someone else's account.
 */
final class UnpaidOrderPaymentLinkTest extends GuestCheckoutTestCase
{
    private const PRODUCT_TITLE = 'A product waiting for its payment';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->getService(RouterInterface::class)->generate('order_payment_resume', ['token' => '1.1.a']);
        } catch (RouteNotFoundException) {
            self::markTestSkipped('The installed theme declares no "order_payment_resume" route.');
        }
    }

    public function testAGuestLandsOnThePaymentStepWithTheCartOfTheOrder(): void
    {
        $fixtures = $this->fixtures();
        $order = $this->unpaidOrderOf($fixtures->guestCustomer($fixtures->customerTitle()));

        $this->client->request('GET', $this->linkOf($order));

        $this->assertResponseRedirectsTo('/checkout/payment');
        $this->forgetHydratedModels();
        $this->client->request('GET', '/checkout/cart');
        self::assertStringContainsString(self::PRODUCT_TITLE, (string) $this->client->getResponse()->getContent());
    }

    public function testAnOrderOfAnAccountAsksForTheAccountFirstAndComesBack(): void
    {
        $fixtures = $this->fixtures();
        $order = $this->unpaidOrderOf($fixtures->customer($fixtures->customerTitle()));
        $link = $this->linkOf($order);

        $this->client->request('GET', $link);

        $this->assertResponseRedirectsTo('/customer/login');
        $location = (string) $this->client->getResponse()->headers->get('Location');
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        self::assertSame($link, $query['redirect'] ?? null, 'Signing in comes back to the link.');
        $this->client->followRedirect();
        self::assertStringContainsString((string) $order->getRef(), (string) $this->client->getResponse()->getContent(), 'The login page says which order waits.');
    }

    public function testTheOwnerSignedInLandsOnThePaymentStep(): void
    {
        $account = $this->signInAsARealAccount();
        $order = $this->unpaidOrderOf($account);

        $this->client->request('GET', $this->linkOf($order));

        $this->assertResponseRedirectsTo('/checkout/payment');
    }

    public function testAnotherAccountIsTurnedAway(): void
    {
        $this->signInAsARealAccount();
        $fixtures = $this->fixtures();
        $order = $this->unpaidOrderOf($fixtures->customer($fixtures->customerTitle()));

        $this->client->request('GET', $this->linkOf($order));

        $this->assertResponseRedirectsTo('/checkout/cart');
        $this->forgetHydratedModels();
        $this->client->request('GET', '/checkout/cart');
        self::assertStringNotContainsString(self::PRODUCT_TITLE, (string) $this->client->getResponse()->getContent());
    }

    public function testASignedInAccountIsNotSwappedForTheGuestOfTheOrder(): void
    {
        $this->signInAsARealAccount();
        $fixtures = $this->fixtures();
        $order = $this->unpaidOrderOf($fixtures->guestCustomer($fixtures->customerTitle()));

        $this->client->request('GET', $this->linkOf($order));

        $this->assertResponseRedirectsTo('/checkout/cart');
        $this->forgetHydratedModels();
        $this->client->request('GET', '/account');
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The account is still signed in.');
    }

    public function testALinkToAnOrderPaidSinceOpensNothing(): void
    {
        $fixtures = $this->fixtures();
        $order = $this->unpaidOrderOf($fixtures->guestCustomer($fixtures->customerTitle()));
        $link = $this->linkOf($order);
        $order->setStatusId((int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId())->save($this->getPropelConnection());

        $this->client->request('GET', $link);

        $this->assertResponseRedirectsTo('/checkout/cart');
    }

    public function testAForgedLinkOpensNothing(): void
    {
        $this->client->request('GET', '/order/pay/1.9999999999.forged');

        $this->assertResponseRedirectsTo('/checkout/cart');
    }

    private function unpaidOrderOf(Customer $customer): Order
    {
        $fixtures = $this->fixtures();
        $order = $fixtures->order($customer, ['postage' => 10, 'statusCode' => OrderStatus::CODE_NOT_PAID]);
        $product = $fixtures->product($fixtures->category(), $fixtures->taxRule(), $fixtures->currency(), ['title' => self::PRODUCT_TITLE]);
        $fixtures->cartItem(CartQuery::create()->findPk($order->getCartId()), $product);

        return $order;
    }

    private function linkOf(Order $order): string
    {
        return '/order/pay/'.$this->getService(UnpaidOrderPaymentLink::class)->createToken($order, time() + 3600);
    }
}
