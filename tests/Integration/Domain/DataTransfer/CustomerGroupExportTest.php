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
use Thelia\Domain\DataTransfer\Export\Type\CustomerExport;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class CustomerGroupExportTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // getDataJsonCache() writes its row cache there without creating it.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
    }

    public function testTheExportCarriesTheCodeAndTheTitleOfTheGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $this->groupTitledInTwoLanguages($factory);
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $group]);

        $row = $this->rowOf($customer->getRef(), Lang::getDefaultLanguage());

        self::assertSame('pro', $row['customer_group']);
        self::assertSame('Professionals', $row['customer_group_title']);
    }

    public function testTheTitleOfTheGroupIsTheOneOfTheLanguageOfTheExport(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $this->groupTitledInTwoLanguages($factory)]);
        $french = LangQuery::create()->findOneByLocale('fr_FR');
        self::assertNotNull($french, 'the seeded languages are missing from the test database');

        $row = $this->rowOf($customer->getRef(), $french);

        self::assertSame('Professionnels', $row['customer_group_title']);
    }

    public function testACustomerIsExportedOnceWhateverTheLanguagesOfItsGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $this->groupTitledInTwoLanguages($factory)]);

        $export = new CustomerExport();
        $export->setLang(Lang::getDefaultLanguage());

        $rows = 0;

        foreach ($export as $data) {
            if (($data['customer_ref'] ?? null) === $customer->getRef()) {
                ++$rows;
            }
        }

        self::assertSame(1, $rows);
    }

    /**
     * The export joins the title of the language it runs for: another language must not add a row.
     */
    private function groupTitledInTwoLanguages(FixtureFactory $factory): CustomerGroup
    {
        $group = $factory->customerGroup('pro', title: 'Professionals');
        $group->setLocale('fr_FR')->setTitle('Professionnels')->save($this->getPropelConnection());

        return $group;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowOf(string $customerRef, Lang $lang): array
    {
        $export = new CustomerExport();
        $export->setLang($lang);

        foreach ($export as $data) {
            if (($data['customer_ref'] ?? null) === $customerRef) {
                return $export->applyOrderAndAliases($data);
            }
        }

        self::fail('The customer is missing from the export.');
    }
}
