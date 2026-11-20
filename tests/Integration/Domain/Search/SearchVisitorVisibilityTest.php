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

namespace Thelia\Tests\Integration\Domain\Search;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionMode;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargets;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargetType;
use Thelia\Domain\Search\Provider\BrandDocumentProvider;
use Thelia\Domain\Search\Provider\CategoryDocumentProvider;
use Thelia\Domain\Search\Provider\ContentDocumentProvider;
use Thelia\Domain\Search\Provider\ProductDocumentProvider;
use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Model\Brand;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\Category;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A search of the shop vouches only for what the visitor of the request may see, by the rules
 * the front API applies, and a redirection never leads that visitor to a target they may not see.
 */
final class SearchVisitorVisibilityTest extends IntegrationTestCase
{
    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        static::getContainer()->get('thelia.url.manager');
        $this->fixtures = $this->createFixtureFactory();
        $this->forget();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->forget();

        parent::tearDown();
    }

    public function testAProductClosedToTheVisitorByACatalogRestrictionIsNotVouchedFor(): void
    {
        $category = $this->fixtures->category();
        $open = $this->product($category);
        $denied = $this->product($category);
        $group = $this->fixtures->customerGroup();
        $this->deny($group, product: $denied);

        $this->signIn($this->customerOf($group));
        self::assertSame([$open->getId()], $this->provider(ProductDocumentProvider::FAMILY)->visibleIds([$open->getId(), $denied->getId()]));

        $this->signIn($this->customerOf($this->fixtures->customerGroup()));
        self::assertSame([$open->getId(), $denied->getId()], $this->provider(ProductDocumentProvider::FAMILY)->visibleIds([$open->getId(), $denied->getId()]));
    }

    public function testACategoryClosedToTheVisitorIsNotVouchedFor(): void
    {
        $open = $this->fixtures->category();
        $denied = $this->fixtures->category();
        $group = $this->fixtures->customerGroup();
        $this->deny($group, category: $denied);

        $this->signIn($this->customerOf($group));

        self::assertSame([$open->getId()], $this->provider(CategoryDocumentProvider::FAMILY)->visibleIds([$open->getId(), $denied->getId()]));
    }

    public function testABrandClosedToTheVisitorIsNotVouchedFor(): void
    {
        $open = $this->fixtures->brand();
        $denied = $this->fixtures->brand();
        $group = $this->fixtures->customerGroup();
        $this->deny($group, brand: $denied);

        $this->signIn($this->customerOf($group));

        self::assertSame([$open->getId()], $this->provider(BrandDocumentProvider::FAMILY)->visibleIds([$open->getId(), $denied->getId()]));
    }

    public function testAContentOfAFolderNotOnlineYetIsNotVouchedFor(): void
    {
        $published = $this->fixtures->content($this->fixtures->folder());
        $inADraftFolder = $this->fixtures->content($this->fixtures->folder(overrides: ['visible' => 0]));

        self::assertSame([$published->getId()], $this->provider(ContentDocumentProvider::FAMILY)->visibleIds([$published->getId(), $inADraftFolder->getId()]));
    }

    public function testARedirectionToAProductClosedToTheVisitorIsNotFollowed(): void
    {
        $product = $this->product($this->fixtures->category());
        $group = $this->fixtures->customerGroup();
        $this->deny($group, product: $product);
        $targets = $this->getService(SearchRedirectTargets::class);

        $this->signIn($this->customerOf($group));
        self::assertNull($targets->pathOf(SearchRedirectTargetType::PRODUCT, $product->getId(), null, 'en_US'));

        $this->signIn($this->customerOf($this->fixtures->customerGroup()));
        self::assertNotNull($targets->pathOf(SearchRedirectTargetType::PRODUCT, $product->getId(), null, 'en_US'));
    }

    public function testARedirectionToACategoryClosedToTheVisitorIsNotFollowed(): void
    {
        $category = $this->fixtures->category();
        $group = $this->fixtures->customerGroup();
        $this->deny($group, category: $category);
        $targets = $this->getService(SearchRedirectTargets::class);

        $this->signIn($this->customerOf($group));
        self::assertNull($targets->pathOf(SearchRedirectTargetType::CATEGORY, $category->getId(), null, 'en_US'));

        $this->signIn($this->customerOf($this->fixtures->customerGroup()));
        self::assertNotNull($targets->pathOf(SearchRedirectTargetType::CATEGORY, $category->getId(), null, 'en_US'));
    }

    private function provider(string $family): object
    {
        $provider = $this->getService(SearchDocumentProviders::class)->get($family);
        self::assertNotNull($provider);

        return $provider;
    }

    private function product(Category $category): Product
    {
        return $this->fixtures->product($category, $this->fixtures->taxRule(), $this->fixtures->currency());
    }

    private function deny(CustomerGroup $group, ?Category $category = null, ?Brand $brand = null, ?Product $product = null): void
    {
        (new CatalogRestriction())
            ->setMode(CatalogRestrictionMode::Deny->value)
            ->setCustomerGroupId($group->getId())
            ->setCategoryId($category?->getId())
            ->setBrandId($brand?->getId())
            ->setProductId($product?->getId())
            ->save($this->getPropelConnection());

        $this->forget();
    }

    private function customerOf(CustomerGroup $group): Customer
    {
        return $this->fixtures->customer($this->fixtures->customerTitle(), ['customerGroup' => $group]);
    }

    private function signIn(Customer $customer): void
    {
        $this->session()->setCustomerUser($customer);
        $this->forget();
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
