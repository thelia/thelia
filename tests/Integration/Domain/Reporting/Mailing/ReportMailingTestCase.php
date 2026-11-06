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

namespace Thelia\Tests\Integration\Domain\Reporting\Mailing;

use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Reporting\Mailing\ReportMailStorage;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\ReportMailing;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Mailer\CapturingMailerFactory;

/**
 * What the tests of the e-mailed reports share: a shop that can send, a mailing over the
 * orders of yesterday, a mail factory that tells what it was asked to render.
 */
abstract class ReportMailingTestCase extends IntegrationTestCase
{
    use MailerAssertionsTrait;

    protected FixtureFactory $factory;

    protected ?CapturingMailerFactory $mailerFactory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->mailerFactory = null;

        ConfigQuery::write('store_email', 'shop@example.com');
        ConfigQuery::write('store_name', 'Report shop');
        // A config row written here is rolled back with the test; the static cache is not.
        ConfigQuery::resetCache();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        (new Filesystem())->remove(ReportMailStorage::directory());

        parent::tearDown();
    }

    /**
     * The real factory, which also remembers the parameters of each e-mail.
     */
    protected function mailerFactory(): CapturingMailerFactory
    {
        return $this->mailerFactory ??= new CapturingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            self::getContainer()->get('mailer.transports'),
        );
    }

    /**
     * @return list<array<string, mixed>> the parameters each e-mail was rendered with
     */
    protected function renderedWith(): array
    {
        return $this->mailerFactory()->renderedWith;
    }

    protected function sentEmail(int $index): Email
    {
        $message = self::getMailerMessages()[$index];
        self::assertInstanceOf(Email::class, $message);

        return $message;
    }

    /**
     * @param list<string> $recipients
     */
    protected function mailing(array $recipients = ['one@example.com'], string $exportRef = 'thelia.export.orders', string $locale = 'en_US', string $period = 'yesterday'): ReportMailing
    {
        $mailing = (new ReportMailing())
            ->setTitle('Weekly sales')
            ->setExportRef($exportRef)
            ->setPeriod($period)
            ->setFrequency('daily')
            ->setSendHour(0)
            ->setLocale($locale)
            ->setRecipients(implode(',', $recipients))
            ->setEnabled(1);
        $mailing->save($this->getPropelConnection());

        return $mailing;
    }

    /**
     * An order placed yesterday at noon, worth quantity x 10.00 with no tax and paid by default, in a currency worth `$currencyRate` units per unit of the default one.
     */
    protected function orderYesterday(float $currencyRate = 1.0, int $quantity = 2, string $statusCode = OrderStatus::CODE_PAID): Order
    {
        $order = $this->factory->order(null, ['currencyRate' => $currencyRate, 'statusCode' => $statusCode]);
        $order->setRef('RPT-'.uniqid());
        $order->setCreatedAt(new \DateTime('yesterday 12:00'));
        $order->save($this->getPropelConnection());
        $this->factory->orderProduct($order, ['quantity' => $quantity, 'price' => '10.000000', 'promoPrice' => '10.000000']);

        return $order;
    }

    protected function yesterday(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }
}
