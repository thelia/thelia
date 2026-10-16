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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Thelia\Domain\DataTransfer\Import\Type\ProductDestinationRestrictionImport;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Model\ProductDestinationRestrictionQuery;
use Thelia\Test\IntegrationTestCase;

final class ProductDestinationRestrictionImportTest extends IntegrationTestCase
{
    public function testARowPutsTheProductIntoTheGroupAndAnUnknownOneIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['ref' => 'IMPORT-RESTRICTED']);
        $group = $this->getService(DestinationRestrictionFacade::class)->save(null, 'import-group', 0, true, []);
        $import = new ProductDestinationRestrictionImport();

        self::assertNull($import->importData(['ref' => 'IMPORT-RESTRICTED', 'restriction_code' => 'import-group']));
        self::assertSame(1, ProductDestinationRestrictionQuery::create()->filterByProductId($product->getId())->filterByDestinationRestrictionId($group->getId())->count());

        self::assertStringContainsString('NO-SUCH-REF', (string) $import->importData(['ref' => 'NO-SUCH-REF', 'restriction_code' => 'import-group']));
        self::assertStringContainsString('no-such-group', (string) $import->importData(['ref' => 'IMPORT-RESTRICTED', 'restriction_code' => 'no-such-group']));
    }
}
