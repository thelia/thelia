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

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Category;
use Thelia\Model\CustomerGroup;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The category loops, and the page of a category, follow the list of the group of the
 * visitor. On the tree Living Room > [Armchairs, Sofas], Dining Room > [Chairs, Stools], the
 * professionals list Sofas: Sofas is open, Living Room is passing (listed, walked, its page
 * answers, its own products are hidden unless Sofas holds them too), and every other
 * category is closed (left out, 404). The default group has no list and sees everything. The
 * back office is not narrowed.
 */
final class CategoryLoopsCustomerGroupTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private Category $livingRoom;

    private Category $diningRoom;

    private Category $chairs;

    private Category $stools;

    private Category $armchairs;

    private Category $sofas;

    private CustomerGroup $pro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        $this->livingRoom = $this->category('Living Room');
        $this->diningRoom = $this->category('Dining Room');
        $this->chairs = $this->category('Chairs', $this->diningRoom);
        $this->stools = $this->category('Stools', $this->diningRoom);
        $this->armchairs = $this->category('Armchairs', $this->livingRoom);
        $this->sofas = $this->category('Sofas', $this->livingRoom);
        $this->pro = $this->factory->customerGroup('pro', categoryIds: [(int) $this->sofas->getId()]);
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testTheCategoryLoopListsTheOpenAndThePassingCategoriesOfTheGroup(): void
    {
        $this->signInAs($this->pro);

        $ids = $this->ids($this->rows('category', ['id' => $this->allIds()]));

        self::assertEqualsCanonicalizing($this->idsOf($this->livingRoom, $this->sofas), $ids);
    }

    public function testTheCategoryLoopListsEverythingForTheDefaultGroupAndForAVisitor(): void
    {
        $this->signInAs(null);
        self::assertCount(6, $this->rows('category', ['id' => $this->allIds()]));

        $this->session()->setCustomerUser(null);
        $this->getService('services_resetter')->reset();
        self::assertCount(6, $this->rows('category', ['id' => $this->allIds()]), 'A visitor is the default group.');
    }

    public function testTheBackOfficeContextListsThemAll(): void
    {
        $this->signInAs($this->pro);

        self::assertCount(6, $this->rows('category', ['id' => $this->allIds(), 'backend_context' => true]));
    }

    public function testTheChildCountLeavesOutTheClosedSubCategories(): void
    {
        $this->signInAs($this->pro);

        $rows = $this->rows('category', ['id' => (string) $this->livingRoom->getId(), 'need_count_child' => true]);

        self::assertSame(1, $rows[0]['CHILD_COUNT'], 'Armchairs is closed, Sofas is open.');
    }

    public function testTheProductCountOfAPassingCategoryCountsWhatTheGroupSeesThroughItsOpenSubCategories(): void
    {
        $this->product($this->sofas);
        $this->product($this->armchairs);
        $this->product($this->livingRoom);
        $alsoASofa = $this->product($this->livingRoom);
        $this->factory->productCategory($alsoASofa, $this->sofas);

        // Visible to the traders through Stools, but filed in Armchairs, which they may not browse.
        $trade = $this->factory->customerGroup('trade', categoryIds: [(int) $this->sofas->getId(), (int) $this->stools->getId()]);
        $armchairAndStool = $this->product($this->armchairs);
        $this->factory->productCategory($armchairAndStool, $this->stools);
        $this->getService('services_resetter')->reset();

        $this->signInAs($this->pro);
        self::assertSame(2, $this->productCount($this->livingRoom), 'The sofa and the product Sofas holds too.');
        self::assertSame(2, $this->productCount($this->sofas));

        $this->signInAs(null);
        self::assertSame(5, $this->productCount($this->livingRoom), 'The default group counts everything.');

        $this->signInAs($trade);
        self::assertSame(2, $this->productCount($this->livingRoom), 'A product reached through a closed sub-category only is not counted.');
        self::assertSame(1, $this->productCount($this->stools));
    }

    public function testTheTreeLoopWalksThePassingCategoryDownToTheOpenOneAndNoFurther(): void
    {
        $this->signInAs($this->pro);

        $ids = $this->ids($this->rows('category-tree', ['category' => 0, 'depth' => 3]));

        self::assertEqualsCanonicalizing($this->idsOf($this->livingRoom, $this->sofas), $ids);
    }

    public function testTheTreeLoopWalksEverythingForTheDefaultGroup(): void
    {
        $this->signInAs(null);

        self::assertCount(6, $this->rows('category-tree', ['category' => 0, 'depth' => 3]));
    }

    public function testThePathLoopOfAnOpenCategoryGoesThroughThePassingOne(): void
    {
        $this->signInAs($this->pro);

        self::assertSame($this->idsOf($this->livingRoom, $this->sofas), $this->ids($this->rows('category-path', ['category' => $this->sofas->getId()])));
    }

    public function testThePathLoopOfAClosedCategoryIsEmpty(): void
    {
        $this->signInAs($this->pro);

        self::assertSame([], $this->rows('category-path', ['category' => $this->armchairs->getId()]));
        self::assertSame([], $this->rows('category-path', ['category' => $this->chairs->getId()]));
    }

    public function testThePagesOfTheOpenAndThePassingCategoriesAnswer(): void
    {
        $this->signInAs($this->pro);

        $this->checkView($this->livingRoom);
        $this->checkView($this->sofas);

        $this->addToAssertionCount(1);
    }

    public function testThePageOfAClosedCategoryIsNotFound(): void
    {
        $this->signInAs($this->pro);

        foreach ([$this->armchairs, $this->diningRoom, $this->chairs, $this->stools] as $category) {
            try {
                $this->checkView($category);
                self::fail('The page of category '.$category->getId().' must be refused.');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testEveryPageAnswersForTheDefaultGroup(): void
    {
        $this->signInAs(null);

        foreach ([$this->livingRoom, $this->diningRoom, $this->chairs, $this->stools, $this->armchairs, $this->sofas] as $category) {
            $this->checkView($category);
        }

        $this->addToAssertionCount(1);
    }

    public function testTheProductPageAnswersThroughAnOpenCategoryOnly(): void
    {
        $sofa = $this->product($this->sofas);
        $alsoASofa = $this->product($this->livingRoom);
        $this->factory->productCategory($alsoASofa, $this->sofas);
        $hidden = [$this->product($this->armchairs), $this->product($this->livingRoom), $this->product($this->chairs)];
        $this->signInAs($this->pro);

        $this->checkProductView((int) $sofa->getId());
        $this->checkProductView((int) $alsoASofa->getId());

        foreach ($hidden as $product) {
            try {
                $this->checkProductView((int) $product->getId());
                self::fail('The page of product '.$product->getId().' must be refused.');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function product(Category $category): Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
    }

    private function productCount(Category $category): int
    {
        return (int) $this->rows('category', ['id' => (string) $category->getId(), 'need_product_count' => true])[0]['PRODUCT_COUNT'];
    }

    private function checkProductView(int $productId): void
    {
        $this->getService('event_dispatcher')->dispatch(new ViewCheckEvent('product', $productId), TheliaEvents::VIEW_CHECK);
    }

    /**
     * @return list<int>
     */
    private function idsOf(Category ...$categories): array
    {
        return array_map(static fn (Category $category): int => (int) $category->getId(), $categories);
    }

    private function allIds(): string
    {
        return implode(',', $this->idsOf($this->livingRoom, $this->diningRoom, $this->chairs, $this->stools, $this->armchairs, $this->sofas));
    }

    private function category(string $title, ?Category $parent = null): Category
    {
        $category = $this->factory->category(null === $parent ? [] : ['parent' => $parent->getId()]);
        $category->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        return $category;
    }

    private function checkView(Category $category): void
    {
        $this->getService('event_dispatcher')->dispatch(new ViewCheckEvent('category', $category->getId()), TheliaEvents::VIEW_CHECK);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $row): int => (int) $row['ID'], $rows);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $loop, array $arguments): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $result = $this->getService(LoopExecutor::class)->execute($loop, ['lang' => $lang->getId(), 'visible' => '*'] + $arguments);

        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
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
