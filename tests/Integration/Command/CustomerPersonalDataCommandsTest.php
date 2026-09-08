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

namespace Thelia\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The two commands a shop answers a personal-data request with, on an email address that
 * carries more than one row.
 *
 * Ordering without an account opens a row of its own, and registering later opens
 * another: the account is not the guest row, and neither is the whole of what the shop
 * knows about that address. A command that reads one row answers half the request —
 * silently, since it has no way of saying it stopped at the first.
 */
final class CustomerPersonalDataCommandsTest extends IntegrationTestCase
{
    private const EMAIL = 'two-rows-on-one-address@test.com';

    public function testAnonymizingAnAddressReachesEveryRowItCarries(): void
    {
        [$account, $guest] = $this->anAddressCarryingAnAccountAndAGuestRow();

        $tester = $this->runCommand('customer:anonymize', ['email' => self::EMAIL, '--force' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('2', $tester->getDisplay(), 'The command has to say how many rows it found.');

        self::assertTrue($this->isAnonymized($account), 'The account on that address must be anonymized.');
        self::assertTrue($this->isAnonymized($guest), 'The guest row on that address must be too.');
    }

    public function testExportingAnAddressReadsEveryRowItCarries(): void
    {
        [$account, $guest] = $this->anAddressCarryingAnAccountAndAGuestRow();

        $tester = $this->runCommand('customer:export-personal-data', ['email' => self::EMAIL]);

        self::assertSame(0, $tester->getStatusCode());

        $exported = $tester->getDisplay();

        self::assertStringContainsString(
            (string) $account->getRef(),
            $exported,
            'The account on that address must be in what the buyer is handed.',
        );
        self::assertStringContainsString(
            (string) $guest->getRef(),
            $exported,
            'So must the orders they placed before opening it.',
        );
    }

    /**
     * @return array{0: Customer, 1: Customer}
     */
    private function anAddressCarryingAnAccountAndAGuestRow(): array
    {
        $fixtures = $this->createFixtureFactory();
        $title = $fixtures->customerTitle();

        $guest = $fixtures->guestCustomer($title, ['email' => self::EMAIL]);
        $account = $fixtures->customer($title, ['email' => self::EMAIL]);

        return [$account, $guest];
    }

    private function isAnonymized(Customer $customer): bool
    {
        return null !== CustomerQuery::create()->findPk($customer->getId())?->getAnonymizedAt();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(string $command, array $input): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find($command));
        $tester->execute($input);

        return $tester;
    }
}
