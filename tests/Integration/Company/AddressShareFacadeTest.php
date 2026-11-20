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

namespace Thelia\Tests\Integration\Company;

use Thelia\Domain\Company\Exception\AddressSharingRefusedException;
use Thelia\Model\AddressQuery;
use Thelia\Model\CompanyQuery;

/**
 * Any buyer shares an address they created; the sharing is taken back by its creator or by a
 * buyer who manages the shared ones, never by a colleague without that right nor by another
 * company; the address a company invoices stays shared.
 */
final class AddressShareFacadeTest extends AddressTestCase
{
    public function testABuyerSharesTheAddressTheyCreatedAndTheColleagueUsesItWithoutChangingIt(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], null, 'Warehouse');

        $this->facade->shareAddress($world['creator'], $warehouse, true);

        self::assertSame($world['first']->getId(), $this->companyIdOf($warehouse->getId()));
        self::assertTrue($this->checker->canUseAddress($world['colleague'], AddressQuery::create()->findPk($warehouse->getId())));
        self::assertFalse($this->checker->canEditAddress($world['colleague'], AddressQuery::create()->findPk($warehouse->getId())));
        self::assertTrue($this->checker->canEditAddress($world['administratorOfFirst'], AddressQuery::create()->findPk($warehouse->getId())));
        self::assertFalse($this->checker->canUseAddress($world['buyerOfSecond'], AddressQuery::create()->findPk($warehouse->getId())));
    }

    public function testANonMemberSharesNothing(): void
    {
        $alone = $this->customer();
        $home = $this->addressOf($alone, null, 'Home');

        $this->expectException(AddressSharingRefusedException::class);

        try {
            $this->facade->shareAddress($alone, $home, true);
        } finally {
            self::assertNull($this->companyIdOf($home->getId()));
        }
    }

    public function testNobodySharesAnAddressOfAnotherAccountNotEvenTheAdministrator(): void
    {
        $world = $this->twoCompanies();
        $personal = $this->addressOf($world['creator'], null, 'Personal');

        foreach ([$world['colleague'], $world['administratorOfFirst'], $world['administratorOfSecond']] as $actor) {
            try {
                $this->facade->shareAddress($actor, $personal, true);
                self::fail('Only the creator shares an address.');
            } catch (AddressSharingRefusedException) {
                self::assertNull($this->companyIdOf($personal->getId()));
            }
        }
    }

    public function testTheCreatorTakesTheSharingBackAndTheAddressStaysTheirs(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');

        $this->facade->shareAddress($world['creator'], $warehouse, false);

        self::assertNull($this->companyIdOf($warehouse->getId()));
        self::assertSame($world['creator']->getId(), AddressQuery::create()->findPk($warehouse->getId())?->getCustomerId());
    }

    public function testTheAdministratorTakesTheSharingBackOfABuyersAddress(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');

        $this->facade->shareAddress($world['administratorOfFirst'], $warehouse, false);

        self::assertNull($this->companyIdOf($warehouse->getId()));
    }

    public function testAColleagueWithoutTheRightAndAnotherCompanyDoNotTakeTheSharingBack(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');

        foreach ([$world['colleague'], $world['administratorOfSecond'], $world['buyerOfSecond']] as $actor) {
            try {
                $this->facade->shareAddress($actor, $warehouse, false);
                self::fail('The sharing is not theirs to take back.');
            } catch (AddressSharingRefusedException) {
                self::assertSame($world['first']->getId(), $this->companyIdOf($warehouse->getId()));
            }
        }
    }

    public function testTheBillingAddressOfTheCompanyStaysShared(): void
    {
        $world = $this->twoCompanies();
        $head = $this->addressOf($world['administratorOfFirst'], $world['first'], 'Head office');
        CompanyQuery::create()->filterById($world['first']->getId())->update(['BillingAddressId' => $head->getId()]);

        try {
            $this->facade->shareAddress($world['administratorOfFirst'], $head, false);
            self::fail('The address the company invoices cannot stop being shared.');
        } catch (AddressSharingRefusedException) {
            self::assertSame($world['first']->getId(), $this->companyIdOf($head->getId()));
        }
    }

    public function testAskingForWhatIsAlreadySoChangesNothing(): void
    {
        $world = $this->twoCompanies();
        $personal = $this->addressOf($world['creator'], null, 'Personal');
        $shared = $this->addressOf($world['creator'], $world['first'], 'Shared');

        $this->facade->shareAddress($world['creator'], $personal, false);
        $this->facade->shareAddress($world['creator'], $shared, true);

        self::assertNull($this->companyIdOf($personal->getId()));
        self::assertSame($world['first']->getId(), $this->companyIdOf($shared->getId()));
    }

    private function companyIdOf(int $addressId): ?int
    {
        $companyId = AddressQuery::create()->filterById($addressId)->select('CompanyId')->findOne();

        return null === $companyId ? null : (int) $companyId;
    }
}
