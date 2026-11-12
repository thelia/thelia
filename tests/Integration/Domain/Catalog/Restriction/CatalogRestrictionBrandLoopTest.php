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

namespace Thelia\Tests\Integration\Domain\Catalog\Restriction;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionMode;
use Thelia\Model\Brand;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\Category;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The brand loop applies the rule the API applies to the brands (BrandRestrictionExtension):
 * a denied brand is out, an allow list on brands keeps only the listed ones, the back
 * office loop reads them all, and a customer with no rule reads every brand.
 */
final class CatalogRestrictionBrandLoopTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var array<string, Brand> */
    private array $brands;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->brands = ['a' => $this->factory->brand(), 'b' => $this->factory->brand(), 'c' => $this->factory->brand()];
        $this->forget();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->forget();

        parent::tearDown();
    }

    public function testACustomerWithNoRuleReadsEveryBrand(): void
    {
        $customer = $this->customerOf($this->factory->customerGroup());

        self::assertSame($this->idsOf('a', 'b', 'c'), $this->listedFor($customer));
    }

    public function testADeniedBrandIsNotListed(): void
    {
        $group = $this->factory->customerGroup();
        $this->write(CatalogRestrictionMode::Deny, $group, $this->brands['b']);

        self::assertSame($this->idsOf('a', 'c'), $this->listedFor($this->customerOf($group)));
    }

    public function testAnAllowListOnBrandsKeepsOnlyTheListedOnes(): void
    {
        $group = $this->factory->customerGroup();
        $this->write(CatalogRestrictionMode::Allow, $group, $this->brands['a']);
        $this->write(CatalogRestrictionMode::Allow, $group, $this->brands['c']);

        self::assertSame($this->idsOf('a', 'c'), $this->listedFor($this->customerOf($group)));
    }

    public function testADenyBeatsAnAllowOnTheSameBrand(): void
    {
        $group = $this->factory->customerGroup();
        $this->write(CatalogRestrictionMode::Allow, $group, $this->brands['a']);
        $this->write(CatalogRestrictionMode::Allow, $group, $this->brands['b']);
        $this->write(CatalogRestrictionMode::Deny, $group, $this->brands['b']);

        self::assertSame($this->idsOf('a'), $this->listedFor($this->customerOf($group)));
    }

    public function testTheBrandOfAnOpenProductIsListedWhateverTheAllowList(): void
    {
        $group = $this->factory->customerGroup();
        $tools = $this->factory->category();
        $other = $this->factory->category();
        $this->brandProduct($tools, $this->brands['a']);
        $this->brandProduct($other, $this->brands['b']);
        $this->writeCategory($group, $tools);

        self::assertSame($this->idsOf('a'), $this->listedFor($this->customerOf($group)));
    }

    /**
     * The only product of a brand in an allowed category is offline: it opens nothing, so the brand
     * stays closed, as a brand with no product at all.
     */
    public function testABrandWhoseOnlyProductIsOfflineStaysClosed(): void
    {
        $group = $this->factory->customerGroup();
        $tools = $this->factory->category();
        $this->brandProduct($tools, $this->brands['a'], online: false);
        $this->brandProduct($tools, $this->brands['b']);
        $this->writeCategory($group, $tools);

        self::assertSame($this->idsOf('b'), $this->listedFor($this->customerOf($group)));
    }

    public function testADeniedBrandStaysClosedEvenWithAnOpenProduct(): void
    {
        $group = $this->factory->customerGroup();
        $tools = $this->factory->category();
        $this->brandProduct($tools, $this->brands['a']);
        $this->brandProduct($tools, $this->brands['b']);
        $this->writeCategory($group, $tools);
        $this->write(CatalogRestrictionMode::Deny, $group, $this->brands['a']);

        self::assertSame($this->idsOf('b'), $this->listedFor($this->customerOf($group)));
    }

    public function testTheBackOfficeLoopListsEveryBrand(): void
    {
        $group = $this->factory->customerGroup();
        $this->write(CatalogRestrictionMode::Deny, $group, $this->brands['b']);

        self::assertSame($this->idsOf('a', 'b', 'c'), $this->listedFor($this->customerOf($group), ['backend_context' => 1]));
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<int>
     */
    private function listedFor(Customer $customer, array $arguments = []): array
    {
        $this->session()->setCustomerUser($customer);
        $this->forget();

        $ids = [];

        foreach ($this->getService(LoopExecutor::class)->execute('brand', $arguments + ['visible' => '*', 'id' => implode(',', $this->idsOf('a', 'b', 'c'))]) as $row) {
            $ids[] = (int) $row->getVarVal()['ID'];
        }

        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function idsOf(string ...$names): array
    {
        $ids = array_map(fn (string $name): int => (int) $this->brands[$name]->getId(), $names);
        sort($ids);

        return $ids;
    }

    private function write(CatalogRestrictionMode $mode, CustomerGroup $group, Brand $brand): void
    {
        (new CatalogRestriction())
            ->setMode($mode->value)
            ->setCustomerGroupId($group->getId())
            ->setBrandId($brand->getId())
            ->save($this->getPropelConnection());

        $this->forget();
    }

    private function brandProduct(Category $category, Brand $brand, bool $online = true): void
    {
        $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10])
            ->setBrandId($brand->getId())
            ->setVisible($online ? 1 : 0)
            ->save($this->getPropelConnection());
    }

    private function writeCategory(CustomerGroup $group, Category $category): void
    {
        (new CatalogRestriction())
            ->setMode(CatalogRestrictionMode::Allow->value)
            ->setCustomerGroupId($group->getId())
            ->setCategoryId($category->getId())
            ->save($this->getPropelConnection());

        $this->forget();
    }

    private function customerOf(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
    }

    private function forget(): void
    {
        $this->getService('services_resetter')->reset();
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
