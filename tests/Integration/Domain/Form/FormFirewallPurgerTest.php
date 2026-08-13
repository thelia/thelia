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

namespace Thelia\Tests\Integration\Domain\Form;

use Thelia\Domain\Form\Service\FormFirewallPurger;
use Thelia\Model\FormFirewall;
use Thelia\Model\FormFirewallQuery;
use Thelia\Test\IntegrationTestCase;

final class FormFirewallPurgerTest extends IntegrationTestCase
{
    private FormFirewallPurger $purger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purger = new FormFirewallPurger();
        FormFirewallQuery::create()->deleteAll();
    }

    public function testPurgeDeletesRecordsOlderThanRetention(): void
    {
        $this->createRecord('203.0.113.1', '-10 days');

        self::assertSame(1, $this->purger->purgeExpiredEntries(1));
        self::assertSame(0, FormFirewallQuery::create()->count());
    }

    public function testPurgeKeepsRecordsInsideRetention(): void
    {
        $this->createRecord('203.0.113.2', '-2 hours');

        self::assertSame(0, $this->purger->purgeExpiredEntries(1));
        self::assertSame(1, FormFirewallQuery::create()->count());
    }

    public function testPurgeNeverDeletesRecordsStillInsideTheWaitingWindow(): void
    {
        $this->createRecord('203.0.113.3', '-30 minutes');

        // A retention of 0 days would otherwise wipe every record, including the
        // ones the firewall still uses to block an IP address.
        self::assertSame(0, $this->purger->purgeExpiredEntries(0));
        self::assertSame(1, FormFirewallQuery::create()->count());
    }

    private function createRecord(string $ipAddress, string $age): void
    {
        (new FormFirewall())
            ->setFormName('thelia_customer_login')
            ->setIpAddress($ipAddress)
            ->setAttempts(1)
            ->setUpdatedAt(new \DateTime($age))
            ->save();
    }
}
