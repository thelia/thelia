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

namespace Thelia\Tests\Integration\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorResolverInterface;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\Service\AiActorResolver;
use Thelia\Test\IntegrationTestCase;

final class AiActorResolverTest extends IntegrationTestCase
{
    private const string SECRET = 'actor-test-secret';

    /** @var list<string>|null */
    private ?array $argv = null;

    private RequestStack $requests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->argv = $_SERVER['argv'] ?? null;
        $this->requests = new RequestStack();
    }

    protected function tearDown(): void
    {
        $_SERVER['argv'] = $this->argv;

        parent::tearDown();
    }

    public function testTheContainerGivesTheRealResolverBehindTheContractInterface(): void
    {
        self::assertInstanceOf(AiActorResolver::class, $this->getService(AiActorResolverInterface::class));
    }

    public function testWithoutARequestTheActorIsTheCommandThatRuns(): void
    {
        $_SERVER['argv'] = ['bin/console', 'thelia:ai:rotate-keys'];

        $actor = $this->resolver()->resolve();

        self::assertSame(AiActorType::System, $actor->type);
        self::assertSame('thelia:ai:rotate-keys', $actor->identifier);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function argumentsThatNameNoCommand(): iterable
    {
        yield 'nothing' => [['bin/console']];
        yield 'an option' => [['bin/console', '--env=prod']];
        yield 'a path' => [['vendor/bin/phpunit', 'tests/Unit']];
        yield 'a line break' => [['bin/console', "x\ny"]];
        yield 'too long' => [['bin/console', 'a'.str_repeat('b', 80)]];
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('argumentsThatNameNoCommand')]
    public function testAnArgumentThatIsNotACommandNameIsNotCopiedIntoTheActor(array $argv): void
    {
        $_SERVER['argv'] = $argv;

        self::assertEquals(AiActor::system('cli'), $this->resolver()->resolve());
    }

    public function testNoRequestIsNeededAndNoneIsCreated(): void
    {
        $actor = $this->resolver()->resolve();

        self::assertSame(AiActorType::System, $actor->type);
        self::assertNull($this->requests->getMainRequest());
    }

    public function testAnAdministratorIsKnownByHisIdentifier(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $session = $this->requestWithSession();
        $session->setAdminUser($admin);

        $actor = $this->resolver()->resolve();

        self::assertSame(AiActorType::Admin, $actor->type);
        self::assertSame((string) $admin->getId(), $actor->identifier);
    }

    public function testASignedInCustomerIsKnownByHisIdentifier(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $this->requestWithSession()->setCustomerUser($customer);

        $actor = $this->resolver()->resolve();

        self::assertSame(AiActorType::Customer, $actor->type);
        self::assertSame((string) $customer->getId(), $actor->identifier);
    }

    public function testAGuestOfTheCheckoutIsAVisitor(): void
    {
        $factory = $this->createFixtureFactory();
        $guest = $factory->customer($factory->customerTitle());
        $guest->setIsGuest(1)->save();
        $this->requestWithSession()->setCustomerUser($guest);

        self::assertSame(AiActorType::Visitor, $this->resolver()->resolve()->type);
    }

    public function testAVisitorIsKnownByASessionHashNeverByTheSessionItself(): void
    {
        $session = $this->requestWithSession();
        $session->start();

        $actor = $this->resolver()->resolve();

        self::assertSame(AiActorType::Visitor, $actor->type);
        self::assertSame(hash_hmac('sha256', 'visitor:session:'.$session->getId(), self::SECRET), $actor->identifier);
        self::assertStringNotContainsString($session->getId(), $actor->identifier);
    }

    public function testTheSameVisitorIsTheSameActorAndAnotherOneIsNot(): void
    {
        $first = $this->requestWithSession();
        $first->start();
        $again = $this->resolver()->resolve();

        self::assertEquals($again, $this->resolver()->resolve());

        $this->requests = new RequestStack();
        $second = $this->requestWithSession();
        $second->setId('another-session');
        $second->start();

        self::assertNotEquals($again, $this->resolver()->resolve());
    }

    public function testWithoutASessionTheVisitorIsKnownByAnIpHashNeverByTheAddress(): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $this->requests->push($request);

        $actor = $this->resolver()->resolve();

        self::assertSame(AiActorType::Visitor, $actor->type);
        self::assertSame(hash_hmac('sha256', 'visitor:ip:203.0.113.7', self::SECRET), $actor->identifier);
        self::assertStringNotContainsString('203.0.113.7', $actor->identifier);
    }

    public function testTheHashDependsOnTheSecretOfTheApplication(): void
    {
        $this->requests->push(Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']));

        $other = new AiActorResolver($this->requests, new SecurityContext($this->requests), 'another-secret');

        self::assertNotEquals($this->resolver()->resolve(), $other->resolve());
    }

    private function resolver(): AiActorResolver
    {
        return new AiActorResolver($this->requests, new SecurityContext($this->requests), self::SECRET);
    }

    private function requestWithSession(): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $request->setSession($session);
        $this->requests->push($request);

        return $session;
    }
}
