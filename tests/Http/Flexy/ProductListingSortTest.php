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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Model\Category;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The sort of a product listing travels in the query string, so it survives a shared url and
 * the pagination links. What the shop gets out of it is pinned here: the ordering of a
 * category page, and the fact that a forged value is not an error page.
 *
 * The listing belongs to the front-office theme, which ships as its own package on its own
 * release cycle: a theme that only knows the two price sorts is reported as skipped.
 */
final class ProductListingSortTest extends WebIntegrationTestCase
{
    private const CATEGORY_URL = 'flexy-sort-test-category.html';

    public function testTheNewestSortListsTheMostRecentProductFirst(): void
    {
        $this->categoryWithDatedProducts();

        $content = $this->render('?sort=newest');

        self::assertSame(
            ['Alpha newest', 'Zulu middle', 'Mike oldest'],
            $this->orderOfTitles($content),
        );
    }

    public function testTheOldestSortListsTheOldestProductFirst(): void
    {
        $this->categoryWithDatedProducts();

        $content = $this->render('?sort=oldest');

        self::assertSame(
            ['Mike oldest', 'Zulu middle', 'Alpha newest'],
            $this->orderOfTitles($content),
        );
    }

    public function testTheAlphabeticalSortsFollowTheProductTitles(): void
    {
        $this->categoryWithDatedProducts();

        self::assertSame(
            ['Alpha newest', 'Mike oldest', 'Zulu middle'],
            $this->orderOfTitles($this->render('?sort=alpha')),
        );

        self::assertSame(
            ['Zulu middle', 'Mike oldest', 'Alpha newest'],
            $this->orderOfTitles($this->render('?sort=alpha_reverse')),
        );
    }

    /**
     * A url can be forged, and a stale link can name a sort the shop no longer offers: the
     * listing answers with its default order rather than an error.
     */
    public function testAnUnknownSortFallsBackOnTheDefaultOrder(): void
    {
        $this->categoryWithDatedProducts();

        // The merchant's own order: the position of each product in its category.
        $positionOrder = ['Zulu middle', 'Alpha newest', 'Mike oldest'];

        self::assertSame($positionOrder, $this->orderOfTitles($this->render('')));
        self::assertSame($positionOrder, $this->orderOfTitles($this->render('?sort=foo')));
    }

    /**
     * The next page is a plain link, so the sort has to be written into it: without that, page
     * two would silently reorder the listing.
     */
    public function testThePaginationLinksCarryTheSort(): void
    {
        $this->categoryWithManyProducts();

        $content = $this->render('?sort=alpha');

        self::assertSame(
            1,
            preg_match('#href="(\?[^"]*page=2)"#', $content, $link),
            'The listing must link to its second page.',
        );

        $secondPageQuery = html_entity_decode($link[1]);

        self::assertStringContainsString('sort=alpha', $secondPageQuery);

        // The link is followed as a visitor would: page two must keep the alphabetical order.
        $secondPage = $this->render($secondPageQuery);

        self::assertStringContainsString('Sort product 31', $secondPage);
    }

    private function render(string $query): string
    {
        $this->assertPageRenders('/'.self::CATEGORY_URL.$query);

        $content = (string) $this->client->getResponse()->getContent();

        if (!str_contains($content, 'value="newest"')) {
            self::markTestSkipped('The installed front-office theme only offers the price sorts.');
        }

        return $content;
    }

    /**
     * @return array<int, string>
     */
    private function orderOfTitles(string $content): array
    {
        $titles = ['Alpha newest', 'Zulu middle', 'Mike oldest'];

        foreach ($titles as $title) {
            self::assertStringContainsString($title, $content);
        }

        usort($titles, static fn (string $a, string $b): int => strpos($content, $a) <=> strpos($content, $b));

        return $titles;
    }

    private function categoryWithDatedProducts(): void
    {
        $factory = $this->factory();
        $category = $this->category($factory);
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        // Titles deliberately break the creation order, so no sort can pass for another.
        // Position, reference, title and creation date deliberately disagree: each of the six
        // sorts then yields an order no other one produces, so none can pass for another.
        $factory->product($category, $taxRule, $currency, [
            'ref' => 'SORT-C',
            'title' => 'Zulu middle',
            'createdAt' => new \DateTime('2022-06-15 12:00:00'),
        ]);
        $factory->product($category, $taxRule, $currency, [
            'ref' => 'SORT-B',
            'title' => 'Alpha newest',
            'createdAt' => new \DateTime('2024-01-01 08:00:00'),
        ]);
        $factory->product($category, $taxRule, $currency, [
            'ref' => 'SORT-A',
            'title' => 'Mike oldest',
            'createdAt' => new \DateTime('2020-03-04 09:30:00'),
        ]);
    }

    /**
     * More products than the listing shows on one page, so the pagination is rendered.
     */
    private function categoryWithManyProducts(): void
    {
        $factory = $this->factory();
        $category = $this->category($factory);
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        for ($i = 1; $i <= 31; ++$i) {
            $factory->product($category, $taxRule, $currency, [
                // References run backwards, so the tiebreaker alone cannot produce the
                // alphabetical order the test asks for.
                'ref' => \sprintf('SORT-%03d', 32 - $i),
                'title' => \sprintf('Sort product %02d', $i),
            ]);
        }
    }

    private function category(FixtureFactory $factory): Category
    {
        $category = $factory->category();
        $category->setLocale('en_US')->setTitle('Sort test category');
        $category->save($this->getPropelConnection());
        $category->setRewrittenUrl('en_US', self::CATEGORY_URL);

        return $category;
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the "main" request of the page render below.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
