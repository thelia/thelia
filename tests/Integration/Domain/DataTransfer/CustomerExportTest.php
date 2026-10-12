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

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Export\Type\CustomerExport;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;

final class CustomerExportTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // getDataJsonCache() writes its row cache there without creating it.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
    }

    public function testTheFirstAndLastNamesAreUnderTheirOwnColumns(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['firstname' => 'Ada', 'lastname' => 'Lovelace']);

        $lang = Lang::getDefaultLanguage();
        $export = new CustomerExport();
        $export->setLang($lang);

        $row = null;

        foreach ($export as $data) {
            if (($data['customer_ref'] ?? null) === $customer->getRef()) {
                $row = $export->applyOrderAndAliases($data);
            }
        }

        self::assertNotNull($row, 'The customer is missing from the export.');

        $translator = Translator::getInstance();
        self::assertSame('Ada', $row[$translator->trans('first_name', [], null, $lang->getLocale())]);
        self::assertSame('Lovelace', $row[$translator->trans('last_name', [], null, $lang->getLocale())]);
    }
}
