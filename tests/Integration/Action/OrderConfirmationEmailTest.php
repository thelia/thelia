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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Action\Order as OrderAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;
use Thelia\Tools\URL;

/**
 * What the order confirmation mail has to carry for a buyer with no account.
 *
 * They have no account page the order shows up on, so this mail is the only thing that
 * ever hands them the tracking link. Asserting on the parameters rather than on the
 * rendered text on purpose: what the core owes is the token and a usable URL, and the
 * wording around them belongs to the message template a shop is free to replace.
 */
final class OrderConfirmationEmailTest extends IntegrationTestCase
{
    private RecordingMailerFactory $mailer;

    private OrderAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );

        $this->action = new OrderAction(
            $this->getService(RequestStack::class),
            $this->mailer,
            $this->getService(SecurityContext::class),
            $this->getService(OrderFacade::class),
            $this->getService(GuestOrderAccessService::class),
            $this->getService(URL::class),
        );
    }

    public function testAGuestIsMailedTheLinkThatLeadsBackToTheOrder(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));

        $this->action->sendConfirmationEmail(new OrderEvent($order));

        $parameters = $this->mailer->parametersOfMessagesSent('order_confirmation');
        self::assertCount(1, $parameters);

        $token = $parameters[0]['guest_order_tracking_token'] ?? null;
        self::assertIsString($token, 'A guest order confirmation must carry a tracking token.');

        self::assertSame(
            $order->getId(),
            $this->getService(GuestOrderAccessService::class)->findOrderForToken($token)?->getId(),
            'The token must be one this shop accepts, and it must name this order.',
        );

        $url = $parameters[0]['guest_order_tracking_url'] ?? null;
        self::assertIsString($url);
        self::assertStringStartsWith('http', $url, 'A mail cannot carry a relative link.');
        self::assertStringEndsWith('/order/track/'.$token, $url);
    }

    /**
     * A customer with an account reaches the order from the account pages, and a link
     * that opens it without signing in would be a way around that.
     */
    public function testACustomerWithAnAccountIsMailedNoTrackingLink(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->customer($factory->customerTitle()));

        $this->action->sendConfirmationEmail(new OrderEvent($order));

        $parameters = $this->mailer->parametersOfMessagesSent('order_confirmation');
        self::assertCount(1, $parameters);
        self::assertArrayNotHasKey('guest_order_tracking_token', $parameters[0]);
        self::assertArrayNotHasKey('guest_order_tracking_url', $parameters[0]);
    }
}
