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

use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\Exception\PurchaseListAccessDeniedException;
use Thelia\Domain\CustomerList\Exception\PurchaseListNotFoundException;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Domain\CustomerList\Service\PurchaseListAccessPolicy;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;

/**
 * A purchase list its owner shares is read by the buyers of the company of its owner, and by
 * nobody else; only its owner changes it.
 */
final class PurchaseListSharingTest extends AddressTestCase
{
    private PurchaseListFacade $lists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lists = $this->getService(PurchaseListFacade::class);
    }

    public function testASharedListIsReadByTheBuyersOfTheCompanyOfItsOwnerInBothDirections(): void
    {
        $world = $this->twoCompanies();
        $sharedByCreator = $this->listOf($world['creator'], 'Shared by Acme', true);
        $privateOfCreator = $this->listOf($world['creator'], 'Private of Acme', false);
        $sharedBySecond = $this->listOf($world['buyerOfSecond'], 'Shared by Globex', true);
        $colleagueList = $this->listOf($world['colleague'], 'Of the colleague', false);

        foreach ([$world['colleague'], $world['administratorOfFirst'], $world['creator']] as $buyerOfFirst) {
            self::assertContains($sharedByCreator->getId(), $this->visibleIds($buyerOfFirst), 'Read by '.$buyerOfFirst->getId());
            self::assertNotContains($sharedBySecond->getId(), $this->visibleIds($buyerOfFirst), 'Not read by '.$buyerOfFirst->getId());
        }

        foreach ([$world['administratorOfSecond'], $world['buyerOfSecond']] as $buyerOfSecond) {
            self::assertContains($sharedBySecond->getId(), $this->visibleIds($buyerOfSecond), 'Read by '.$buyerOfSecond->getId());
            self::assertNotContains($sharedByCreator->getId(), $this->visibleIds($buyerOfSecond), 'Not read by '.$buyerOfSecond->getId());
        }

        self::assertNotContains($privateOfCreator->getId(), $this->visibleIds($world['colleague']), 'A list that is not shared stays its owner\'s.');
        self::assertTrue($this->policy()->canRead($world['colleague'], $sharedByCreator));
        self::assertFalse($this->policy()->canRead($world['colleague'], $privateOfCreator));
        self::assertFalse($this->policy()->canRead($world['buyerOfSecond'], $sharedByCreator));
        self::assertFalse($this->policy()->canRead($world['administratorOfSecond'], $sharedByCreator));
        self::assertFalse($this->policy()->canRead($this->customer(), $sharedByCreator));
        self::assertNotContains($colleagueList->getId(), $this->visibleIds($world['creator']));
        self::assertSame([$sharedByCreator->getId(), $colleagueList->getId()], $this->visibleIds($world['colleague']));
        self::assertSame($sharedByCreator->getId(), $this->lists->getVisible($world['colleague'], (int) $sharedByCreator->getId())->getId());

        $this->expectException(PurchaseListNotFoundException::class);
        $this->lists->getVisible($world['buyerOfSecond'], (int) $sharedByCreator->getId());
    }

    public function testAnAccountWithoutCompanyReadsNoSharedListAndItsOwnAsBefore(): void
    {
        $world = $this->twoCompanies();
        $this->listOf($world['creator'], 'Shared by Acme', true);
        $stranger = $this->customer();
        $own = $this->listOf($stranger, 'Mine', true);

        self::assertSame([$own->getId()], $this->visibleIds($stranger));
        self::assertFalse($this->policy()->canShare($stranger, $own), 'A list of an account without company is shared with nobody.');
    }

    public function testAListSharedByAnOwnerWhoLeftTheCompanyIsReadByNobodyElse(): void
    {
        $world = $this->twoCompanies();
        $list = $this->listOf($world['creator'], 'Shared by Acme', true);
        self::assertContains($list->getId(), $this->visibleIds($world['colleague']));

        $this->facade->detach($world['creator']);

        self::assertNotContains($list->getId(), $this->visibleIds($world['colleague']));
        self::assertFalse($this->policy()->canRead($world['colleague'], $list));
        self::assertSame([$list->getId()], $this->visibleIds($world['creator']));
    }

    public function testOnlyTheOwnerChangesASharedList(): void
    {
        $world = $this->twoCompanies();
        $list = $this->listOf($world['creator'], 'Shared by Acme', true);

        foreach ([$world['colleague'], $world['administratorOfFirst']] as $colleague) {
            self::assertTrue($this->lists->getVisible($colleague, (int) $list->getId())->getShared() > 0);
            self::assertFalse($this->lists->canWrite($colleague, $list));
            self::assertFalse($this->lists->canShare($colleague, $list));

            try {
                $this->lists->rename($colleague, (int) $list->getId(), 'Renamed by '.$colleague->getId());
                self::fail('A colleague renamed a list they only read.');
            } catch (PurchaseListAccessDeniedException) {
                self::assertSame('Shared by Acme', $this->reload($list)->getTitle());
            }

            try {
                $this->lists->replaceItems($colleague, (int) $list->getId(), new ReferenceQuantityLines());
                self::fail('A colleague replaced the lines of a list they only read.');
            } catch (PurchaseListAccessDeniedException) {
                self::addToAssertionCount(1);
            }

            try {
                $this->lists->delete($colleague, (int) $list->getId());
                self::fail('A colleague deleted a list they only read.');
            } catch (PurchaseListAccessDeniedException) {
                self::assertNotNull($this->reload($list));
            }
        }

        self::assertTrue($this->lists->canWrite($world['creator'], $list));
        self::assertTrue($this->lists->canShare($world['creator'], $list));
        self::assertSame('Renamed by owner', $this->lists->rename($world['creator'], (int) $list->getId(), 'Renamed by owner')->getTitle());
    }

    private function listOf(Customer $owner, string $title, bool $shared): CustomerList
    {
        $list = new CustomerList();
        $list->setCustomerId($owner->getId())->setType('purchase')->setTitle($title)->setShared($shared ? 1 : 0);
        $list->save($this->getPropelConnection());

        return $list;
    }

    /**
     * @return list<int>
     */
    private function visibleIds(Customer $customer): array
    {
        $ids = array_map(static fn (CustomerList $list): int => (int) $list->getId(), $this->lists->listVisibleFor($customer));
        sort($ids);

        return $ids;
    }

    private function reload(CustomerList $list): CustomerList
    {
        return \Thelia\Model\CustomerListQuery::create()->findPk($list->getId()) ?? throw new \LogicException('The list is gone.');
    }

    private function policy(): PurchaseListAccessPolicy
    {
        return $this->getService(PurchaseListAccessPolicy::class);
    }
}
