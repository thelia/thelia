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

namespace Thelia\Tests\Integration\Domain\CustomerList;

use Thelia\Domain\CustomerList\Service\PurchaseListAccessPolicy;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Test\IntegrationTestCase;

/**
 * Without companies, a purchase list is its owner's alone. The facade already
 * hides the lists of others before asking for write access, so the write rule is
 * held here, where it will change when company sharing arrives.
 */
final class PurchaseListAccessPolicyTest extends IntegrationTestCase
{
    public function testOnlyTheOwnerReadsAndWritesAndNobodyShares(): void
    {
        $policy = $this->getService(PurchaseListAccessPolicy::class);
        $owner = (new Customer())->setId(10);
        $colleague = (new Customer())->setId(11);
        $list = (new CustomerList())->setCustomerId(10);

        self::assertTrue($policy->canRead($owner, $list));
        self::assertTrue($policy->canWrite($owner, $list));
        self::assertFalse($policy->canRead($colleague, $list));
        self::assertFalse($policy->canWrite($colleague, $list));
        self::assertFalse($policy->canShare($owner, $list));
    }
}
