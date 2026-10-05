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

namespace Thelia\Tests\Http\BackOffice;

use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Moving several orders to "sent" at once from the order list, the way a merchant does
 * after the carrier picked the parcels up: each customer gets their own shipping e-mail,
 * once.
 */
final class OrderShippingEmailBulkTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        if (ConfigQuery::read('active-admin-template') !== 'default-twig') {
            self::markTestSkipped('The Twig back-office is not the active admin template of the test shop.');
        }

        $templatePath = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath().DS.'order_shipped.html.twig';

        if (!is_file($templatePath)) {
            self::markTestSkipped('The installed mail template has no shipping e-mail yet: nothing would leave.');
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        // Built directly, not through createFixtureFactory(): that helper pushes a
        // synthetic request the security context would then read the session from.
        $this->factory = new FixtureFactory($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testEachOrderMovedToSentFromTheListMailsItsOwnCustomerOnce(): void
    {
        $admin = $this->factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);

        $first = $this->factory->order($this->factory->customer($this->factory->customerTitle()), ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $second = $this->factory->order($this->factory->customer($this->factory->customerTitle()), ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $sent = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_SENT);

        $crawler = $this->client->request('GET', '/admin/orders');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        // After the first request: a configuration written before it loses the session.
        ConfigQuery::write('store_email', 'shop@example.com');
        ConfigQuery::resetCache();

        $this->client->request('POST', '/admin/order/update/status', [
            '_token' => $crawler->filter('[data-testid="order-bulk-status-submit"]')->form()->get('_token')->getValue(),
            'status_id' => $sent->getId(),
            'order_ids' => [$first->getId(), $second->getId()],
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertTrue(OrderQuery::create()->findPk($first->getId())->isSent());
        self::assertTrue(OrderQuery::create()->findPk($second->getId())->isSent());

        $shippingEmails = array_values(array_filter(
            self::getMailerMessages(),
            static fn (object $message): bool => $message instanceof Email
                && (str_contains((string) $message->getSubject(), $first->getRef()) || str_contains((string) $message->getSubject(), $second->getRef())),
        ));
        $recipients = array_map(static fn (Email $email): string => $email->getTo()[0]->getAddress(), $shippingEmails);
        sort($recipients);
        $expected = [$first->getCustomer()->getEmail(), $second->getCustomer()->getEmail()];
        sort($expected);

        self::assertSame($expected, $recipients, 'One shipping e-mail per order, each to its own customer.');
    }
}
