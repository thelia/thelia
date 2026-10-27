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

namespace Thelia\Tests\Integration\Api;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\PriceFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\Category;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\Template;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The price slider of a listing is drawn on what the visitor pays, the way the card is: the
 * price of the group, then the discounts of the group and of the customer, taxes included
 * unless the group is shown untaxed prices.
 *
 *   CHEAP  60.00 untaxed, 20 % VAT
 *   DEAR  100.00 untaxed, 20 % VAT
 */
final class PriceFacetCustomerPriceTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use ResetsPriceMemos;

    private FilterService $filterService;

    private FixtureFactory $factory;

    private Category $category;

    private Currency $currency;

    /** @var array<string, Product> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->currency = Currency::getDefaultCurrency();
        $this->resetPriceMemos();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Price template');
        $template->save($this->getPropelConnection());

        $this->category = $this->factory->category();
        $this->category->setDefaultTemplateId($template->getId())->save($this->getPropelConnection());
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($this->factory->tax(['requirements' => ['percent' => '20']])->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        foreach (['CHEAP' => 60.0, 'DEAR' => 100.0] as $ref => $price) {
            $this->products[$ref] = $this->factory->product($this->category, $taxRule, $this->currency, ['ref' => $ref, 'basePrice' => $price]);
        }
    }

    protected function tearDown(): void
    {
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testAVisitorIsDrawnThePublicSlider(): void
    {
        self::assertSame(['72', '120'], $this->bounds());
    }

    public function testAMemberIsDrawnTheSliderOfWhatTheyPay(): void
    {
        $this->loginAsCustomerInSession($this->member($this->factory->customerGroup(discount: 50.0)));

        // 60 and 100, halved, taxed.
        self::assertSame(['36', '60'], $this->bounds());
        self::assertSame(['CHEAP'], $this->matching(['price' => ['price' => ['min' => '30', 'max' => '40']]]));

        $this->logoutCustomer();
        $this->resetPriceMemos();

        self::assertSame([], $this->matching(['price' => ['price' => ['min' => '30', 'max' => '40']]]), 'A visitor, a minute later, pays more than that.');
    }

    public function testTheDiscountOfTheCustomerAndThePriceOfTheirGroupCountToo(): void
    {
        $group = $this->factory->customerGroup(discount: 50.0);
        $this->factory->customerGroupProductPrice($group, $this->products['DEAR']->getDefaultSaleElements(), $this->currency, 40.0);
        $this->loginAsCustomerInSession($this->member($group, 10.0));

        // CHEAP: 60 x 50 % x 90 % x 1.2 = 32.4 ; DEAR: 40 x 90 % x 1.2 = 43.2.
        self::assertSame(['32', '44'], $this->bounds());
    }

    public function testAGroupShownUntaxedPricesIsDrawnAnUntaxedSlider(): void
    {
        $this->loginAsCustomerInSession($this->member($this->factory->customerGroup(discount: 50.0, displayTaxedPrice: false)));

        self::assertSame(['30', '50'], $this->bounds());
        self::assertSame(['CHEAP'], $this->matching(['price' => ['price' => ['min' => '25', 'max' => '35']]]));
    }

    private function member(CustomerGroup $group, float $customerDiscount = 0.0): Customer
    {
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $member->setDiscount((string) $customerDiscount)->save($this->getPropelConnection());

        return $member;
    }

    /**
     * @return list<string>
     */
    private function bounds(): array
    {
        $facets = $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $this->category->getId()]]],
                    'locale' => 'en_US',
                ],
            ],
            'products',
        );

        foreach ($facets as $facet) {
            if ($facet instanceof Filter && 'price' === $facet->getType()) {
                return array_map(static fn (FilterValue $value): string => $value->getTitle(), $facet->getValues());
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $tfilters
     *
     * @return list<string>
     */
    private function matching(array $tfilters): array
    {
        $this->getService(PriceFilter::class)->reset();
        $ids = array_map(static fn (Product $product): int => (int) $product->getId(), $this->products);
        $query = ProductQuery::create()->filterById(array_values($ids), Criteria::IN);
        $filtered = $this->filterService->filterWithTFilter(tfilters: $tfilters, resource: 'products', query: $query);

        return array_values(array_map(
            static fn (Product $product): string => $product->getRef(),
            iterator_to_array($filtered->find($this->getPropelConnection())),
        ));
    }
}
