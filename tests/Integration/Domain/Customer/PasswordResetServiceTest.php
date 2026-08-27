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

namespace Thelia\Tests\Integration\Domain\Customer;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Customer\Exception\InvalidPasswordResetTokenException;
use Thelia\Domain\Customer\Service\CustomerEmailRequestLimiter;
use Thelia\Domain\Customer\Service\PasswordResetService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;

/**
 * The whole point of a reset link is to be the only thing that gives access to an
 * account whose password is lost: it must reach the owner and nobody else, work once,
 * stop working shortly after, and never carry a password of its own.
 */
final class PasswordResetServiceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private RecordingMailerFactory $mailer;

    private PasswordResetService $service;

    private RequestStack $requestStack;

    private ?string $previousClientIp = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();

        // Give this test its own caller address, so the per-client window it spends is
        // its own and neither the rest of the suite nor an earlier run is in the way.
        $this->requestStack = $this->getService(RequestStack::class);
        $request = $this->requestStack->getMainRequest();
        $this->previousClientIp = $request?->server->get('REMOTE_ADDR');
        $request?->server->set('REMOTE_ADDR', '203.0.113.'.random_int(1, 254));

        $this->mailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );

        $this->service = new PasswordResetService(
            $this->mailer,
            $this->getService(CustomerEmailRequestLimiter::class),
            (string) static::getContainer()->getParameter('kernel.secret'),
        );
    }

    protected function tearDown(): void
    {
        $this->requestStack->getMainRequest()?->server->set('REMOTE_ADDR', $this->previousClientIp);

        parent::tearDown();
    }

    public function testTheMailCarriesAWayBackInAndNeverAPassword(): void
    {
        $customer = $this->customer();
        $storedPassword = $customer->getPassword();

        $this->service->requestResetLink((string) $customer->getEmail());

        $messages = $this->mailer->parametersOfMessagesSent('lost_password');
        self::assertCount(1, $messages, 'The owner of the address must be mailed exactly once.');
        self::assertArrayNotHasKey('password', $messages[0], 'A reset mail must never carry a password.');
        self::assertNotEmpty($messages[0]['token'] ?? null);

        self::assertSame(
            $storedPassword,
            $this->reload($customer)->getPassword(),
            'Sending a link must leave the account usable with the password its owner already has.',
        );
    }

    public function testAnUnknownAddressIsAnsweredWithTheSameSilence(): void
    {
        $this->service->requestResetLink('unknown-'.bin2hex(random_bytes(8)).'@example.com');

        self::assertSame([], $this->mailer->customerMessages);
    }

    public function testTheCapStopsTheMailWithoutSayingSo(): void
    {
        $customer = $this->customer();
        $email = (string) $customer->getEmail();

        // Spend the address window through the limiter itself, so the request below is
        // the first one over the cap.
        $limiter = $this->getService(CustomerEmailRequestLimiter::class);
        self::assertTrue($limiter->allows($email));
        self::assertTrue($limiter->allows($email));
        self::assertTrue($limiter->allows($email));
        self::assertFalse($limiter->allows($email));

        $this->service->requestResetLink($email);

        self::assertSame([], $this->mailer->customerMessages);
    }

    public function testTheTokenFromTheMailSetsTheChosenPassword(): void
    {
        $customer = $this->customer();

        $this->service->requestResetLink((string) $customer->getEmail());
        $token = $this->mailer->parametersOfMessagesSent('lost_password')[0]['token'];

        $this->service->resetPassword($token, 'a-brand-new-password');

        self::assertTrue(
            $this->reload($customer)->checkPassword('a-brand-new-password'),
            'The owner must end up with the password chosen behind the link.',
        );
    }

    public function testALinkStopsWorkingOnceItHasBeenUsed(): void
    {
        $customer = $this->customer();
        $token = $this->service->createToken($customer);

        $this->service->resetPassword($token, 'a-brand-new-password');

        $this->expectException(InvalidPasswordResetTokenException::class);
        $this->service->resetPassword($token, 'yet-another-password');
    }

    public function testALinkStopsWorkingWhenThePasswordChangesElsewhere(): void
    {
        $customer = $this->customer();
        $token = $this->service->createToken($customer);

        $customer->setPassword('changed-in-the-account-page')->save();

        $this->expectException(InvalidPasswordResetTokenException::class);
        $this->service->resetPassword($token, 'a-brand-new-password');
    }

    public function testALinkStopsWorkingWhenTheAddressChanges(): void
    {
        $customer = $this->customer();
        $token = $this->service->createToken($customer);

        $customer->setEmail('moved-'.bin2hex(random_bytes(8)).'@example.com')->save();

        $this->expectException(InvalidPasswordResetTokenException::class);
        $this->service->resetPassword($token, 'a-brand-new-password');
    }

    public function testAnExpiredLinkIsRejected(): void
    {
        $customer = $this->customer();
        $token = $this->service->createToken($customer, -1);

        $this->expectException(InvalidPasswordResetTokenException::class);
        $this->service->resetPassword($token, 'a-brand-new-password');
    }

    public function testALinkGivenALongerLifeThanItWasSignedForIsRejected(): void
    {
        $customer = $this->customer();
        [$customerId, $expiresAt, $signature] = explode('.', $this->service->createToken($customer));

        $stretched = \sprintf('%s.%d.%s', $customerId, (int) $expiresAt + 86400, $signature);

        self::assertNull($this->service->findCustomerForToken($stretched));
    }

    public function testALinkPointedAtAnotherAccountIsRejected(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        [, $expiresAt, $signature] = explode('.', $this->service->createToken($customer));

        $moved = \sprintf('%d.%s.%s', $other->getId(), $expiresAt, $signature);

        self::assertNull($this->service->findCustomerForToken($moved));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokenProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no separator' => ['nonsense'];
        yield 'too few parts' => ['1.2'];
        yield 'too many parts' => ['1.2.3.4'];
        yield 'non numeric account' => ['abc.2000000000.deadbeef'];
        yield 'non numeric expiry' => ['1.later.deadbeef'];
        yield 'unknown account' => ['999999999.2000000000.deadbeef'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedTokenProvider')]
    public function testATokenThisShopDidNotIssueIsRejected(string $token): void
    {
        self::assertNull($this->service->findCustomerForToken($token));
    }

    private function customer(): Customer
    {
        return $this->factory->customer(
            $this->factory->customerTitle(),
            ['email' => 'reset-password-'.bin2hex(random_bytes(8)).'@example.com'],
        );
    }

    private function reload(Customer $customer): Customer
    {
        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertInstanceOf(Customer::class, $reloaded);

        return $reloaded;
    }
}
