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

namespace Thelia\Tests\Unit\Domain\Quote;

use PHPUnit\Framework\TestCase;
use Thelia\Api\Security\AdminApiResourcePermissions;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Quote\Enum\QuoteRevisionAuthor;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;

final class QuoteEnumTest extends TestCase
{
    public function testAQuoteStateIsStoredUnderTheNameOfItsStep(): void
    {
        self::assertSame(
            ['draft', 'requested', 'quoted', 'countered', 'accepted', 'rejected', 'expired', 'ordered'],
            array_map(static fn (QuoteState $state): string => $state->value, QuoteState::cases()),
        );
    }

    public function testARevisionIsADraftOrSentAndWrittenByTheBuyerOrTheShop(): void
    {
        self::assertSame(['draft', 'sent'], array_map(static fn (QuoteRevisionState $state): string => $state->value, QuoteRevisionState::cases()));
        self::assertSame(['customer', 'admin'], array_map(static fn (QuoteRevisionAuthor $author): string => $author->value, QuoteRevisionAuthor::cases()));
    }

    public function testEveryStateFitsTheColumnThatStoresIt(): void
    {
        foreach ([...QuoteState::cases(), ...QuoteRevisionState::cases(), ...QuoteRevisionAuthor::cases()] as $case) {
            self::assertLessThanOrEqual(16, \strlen($case->value));
        }
    }

    public function testAQuoteRequestIsARightARoleCanGiveAndTheBackOfficeLabelsIt(): void
    {
        self::assertSame('quote.request', CompanyPermission::QUOTE_REQUEST);
        self::assertArrayHasKey(CompanyPermission::QUOTE_REQUEST, CompanyPermission::labels());
    }

    public function testTheQuotesOfTheApiAnswerToTheQuoteRightOfTheBackOffice(): void
    {
        self::assertSame('admin.quote', AdminResources::QUOTE);

        $permissions = new AdminApiResourcePermissions();

        foreach (['Quote', 'QuoteRevision', 'QuoteItem'] as $resource) {
            self::assertSame(AdminResources::QUOTE, $permissions->resolve('Thelia\\Api\\Resource\\'.$resource));
        }
    }
}
