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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Thelia\Domain\DataTransfer\Import\Type\ProductStockImport;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;

/**
 * ImportHandler::import() takes an optional language, and the import command hands it
 * whatever the locale it was given resolves to, nothing for a locale the shop does not
 * have. An import without a language reads the default one instead of failing on a
 * property that cannot be null.
 */
final class ImportLanguageTest extends IntegrationTestCase
{
    public function testAnImportGivenNoLanguageReadsTheDefaultOne(): void
    {
        $import = (new ProductStockImport())->setLang(null);

        self::assertSame(Lang::getDefaultLanguage()->getId(), $import->getLang()->getId());
    }
}
