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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule\Audience;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Audience\AudienceResolverInterface;
use Thelia\Domain\Pricing\Rule\Audience\CustomerAudience;
use Thelia\Model\Customer;

/**
 * The reserved rules a customer is entitled to are the union of what every audience
 * mode answers, each rule once, asked once per customer until the service is reset.
 */
final class CustomerAudienceTest extends TestCase
{
    public function testTheRulesOfEveryAudienceModeAddUpEachOnce(): void
    {
        $audience = new CustomerAudience([
            $this->resolver([3, 5]),
            $this->resolver([5, 8]),
        ]);

        self::assertSame([3, 5, 8], $audience->entitledRuleIds($this->customer(42)));
    }

    public function testTheModesAreAskedOncePerCustomerUntilTheServiceIsReset(): void
    {
        $resolver = $this->createMock(AudienceResolverInterface::class);
        $resolver->expects(self::exactly(3))->method('entitledRuleIds')->willReturnCallback(
            static fn (Customer $customer): array => 42 === $customer->getId() ? [3] : [7, 9],
        );
        $audience = new CustomerAudience([$resolver]);

        $first = $audience->entitledRuleIds($this->customer(42));
        $again = $audience->entitledRuleIds($this->customer(42));
        $another = $audience->entitledRuleIds($this->customer(43));
        $audience->reset();
        $afterReset = $audience->entitledRuleIds($this->customer(42));

        self::assertSame([3], $first);
        self::assertSame([3], $again);
        self::assertSame([7, 9], $another);
        self::assertSame([3], $afterReset);
    }

    public function testAVisitorWithoutAnAccountAndAGuestAreEntitledToNothing(): void
    {
        $resolver = $this->createMock(AudienceResolverInterface::class);
        $resolver->expects(self::never())->method('entitledRuleIds');
        $audience = new CustomerAudience([$resolver]);

        self::assertSame([], $audience->entitledRuleIds(null));
        self::assertSame([], $audience->entitledRuleIds($this->customer(42)->setIsGuest(1)));
    }

    /**
     * @param list<int> $ruleIds
     */
    private function resolver(array $ruleIds): AudienceResolverInterface
    {
        $resolver = $this->createStub(AudienceResolverInterface::class);
        $resolver->method('entitledRuleIds')->willReturn($ruleIds);

        return $resolver;
    }

    private function customer(int $id): Customer
    {
        return (new Customer())->setId($id);
    }
}
