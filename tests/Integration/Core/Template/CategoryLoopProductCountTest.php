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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Model\Category;
use Thelia\Model\CustomerGroup;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * PRODUCT_COUNT of the category loop counts what the visitor can see: a group that lists a
 * sub-category passes through its parent, whose own products it does not see, so they are
 * left out on the front; the back office counts them all.
 */
final class CategoryLoopProductCountTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private Category $open;

    private CustomerGroup $pro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        $this->open = $this->category('Open');
        $listedChild = $this->category('Listed child', $this->open);
        $currency = $this->factory->currency();

        $this->factory->product($this->open, $this->factory->taxRule(), $currency);
        $this->factory->product($this->open, $this->factory->taxRule(), $currency);
        $this->factory->product($listedChild, $this->factory->taxRule(), $currency);

        $this->pro = $this->factory->customerGroup('pro', categoryIds: [(int) $listedChild->getId()]);
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testTheCountOfAPassingCategoryLeavesOutItsOwnProducts(): void
    {
        $this->signInAs($this->pro);

        self::assertSame(1, $this->productCount(), 'The product of the listed child only.');
        self::assertSame(1, $this->productCount(['product_count_visible_only' => true]));
    }

    public function testTheDefaultGroupWithNoListCountsThemAll(): void
    {
        $this->signInAs(null);

        self::assertSame(3, $this->productCount());
    }

    public function testTheBackOfficeCountsThemAll(): void
    {
        $this->signInAs($this->pro);

        self::assertSame(3, $this->productCount(['backend_context' => true]));
    }

    public function testTheModelCountsThemAllUnlessAVisibilityIsGiven(): void
    {
        $this->signInAs($this->pro);

        self::assertSame(3, $this->open->countAllProducts(), 'Without a visibility the count is the one it always was.');
        self::assertSame(3, $this->open->countAllProductsVisibleOnly());
        self::assertSame(1, $this->open->countAllProducts(true, $this->getService(ProductVisibility::class)));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function productCount(array $arguments = []): int
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $result = $this->getService(LoopExecutor::class)->execute('category', [
            'lang' => $lang->getId(),
            'visible' => '*',
            'id' => (string) $this->open->getId(),
            'need_product_count' => true,
        ] + $arguments);

        foreach ($result as $row) {
            return (int) $row->getVarVal()['PRODUCT_COUNT'];
        }

        self::fail('The loop returned no row.');
    }

    private function category(string $title, ?Category $parent = null): Category
    {
        $category = $this->factory->category(null === $parent ? [] : ['parent' => $parent->getId()]);
        $category->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        return $category;
    }

    private function signInAs(?CustomerGroup $group): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), null === $group ? [] : ['customerGroup' => $group]);
        $this->session()->setCustomerUser($customer);
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
