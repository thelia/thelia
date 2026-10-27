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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Category;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * What hangs on a product hidden from the customer group of the reader - its images, its
 * documents and the links of its sale elements to its images - is hidden with it: out of the
 * collection, 404 on the item and on its file. A product is hidden here from group A by the
 * only category it sits in, off the list of group A. Group B and the default group have no
 * list and read everything. The admin API is not narrowed.
 */
final class ProductAttachmentsVisibilityApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    private CustomerGroup $groupA;

    private CustomerGroup $groupB;

    private Product $hidden;

    private Product $shown;

    /** @var list<string> the files this test stored, removed afterwards */
    private array $storedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        $listed = $this->factory->category();
        $this->groupA = $this->factory->customerGroup('group-a', categoryIds: [(int) $listed->getId()]);
        $this->groupB = $this->factory->customerGroup('group-b');
        $this->getService('services_resetter')->reset();

        $this->hidden = $this->productIn($this->factory->category());
        $this->shown = $this->productIn($listed);
    }

    protected function tearDown(): void
    {
        foreach ($this->storedFiles as $storedFile) {
            @unlink($storedFile);
        }
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function attachments(): iterable
    {
        yield 'images' => ['product_images'];
        yield 'documents' => ['product_documents'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('attachments')]
    public function testTheAttachmentsOfAHiddenProductLeaveTheCollectionAndTheirItemIsNotFound(string $resource): void
    {
        $ofHidden = $this->attach($resource, $this->hidden);
        $ofShown = $this->attach($resource, $this->shown);

        foreach ([$this->groupA] as $group) {
            $token = $this->tokenOf($group);
            $label = $group->getCode();

            $ids = $this->memberIds('/api/front/'.$resource, $token);

            self::assertNotContains($ofHidden, $ids, $label);
            self::assertContains($ofShown, $ids, $label);
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofHidden, token: $token)->getStatusCode(), $label);
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofHidden.'/file', token: $token)->getStatusCode(), $label.': the file is not served either');
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofShown, token: $token)->getStatusCode(), $label);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('attachments')]
    public function testTheGroupsWithNoListReadTheAttachments(string $resource): void
    {
        $ofHidden = $this->attach($resource, $this->hidden);
        $tokenA = $this->tokenOf($this->groupA);

        foreach ([$this->groupB, null] as $group) {
            $token = $this->tokenOf($group);
            $label = $group?->getCode() ?? 'default group';

            self::assertContains($ofHidden, $this->memberIds('/api/front/'.$resource, $token), $label);
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofHidden, token: $token)->getStatusCode(), $label);
            self::assertNotContains($ofHidden, $this->memberIds('/api/front/'.$resource, $tokenA), 'The answer given to one group is not kept for the next.');
        }
    }

    public function testTheLinksOfASaleElementToTheImageOfAHiddenProductAreHiddenToo(): void
    {
        $linkOfHidden = $this->link($this->hidden);
        $linkOfShown = $this->link($this->shown);
        $tokenA = $this->tokenOf($this->groupA);
        $tokenB = $this->tokenOf($this->groupB);

        $idsForA = $this->memberIds('/api/front/product_sale_elements_product_image', $tokenA);
        self::assertNotContains($linkOfHidden, $idsForA);
        self::assertContains($linkOfShown, $idsForA);
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/product_sale_elements_product_image/'.$linkOfHidden, token: $tokenA)->getStatusCode());

        self::assertContains($linkOfHidden, $this->memberIds('/api/front/product_sale_elements_product_image', $tokenB));
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/product_sale_elements_product_image/'.$linkOfHidden, token: $tokenB)->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('attachments')]
    public function testTheAdminApiIsNotNarrowed(string $resource): void
    {
        $ofHidden = $this->attach($resource, $this->hidden);

        $response = $this->jsonRequest('GET', '/api/admin/'.$resource.'/'.$ofHidden, token: $this->authenticateAsAdmin());

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    private function productIn(Category $category): Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
    }

    private function attach(string $resource, Product $product): int
    {
        if ('product_images' === $resource) {
            $image = $this->factory->productImage($product);
            $this->storedFiles[] = $image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile();

            return (int) $image->getId();
        }

        $document = new ProductDocument();
        $document->setProductId($product->getId());
        $document->setVisible(1);
        $document->setPosition(1);
        $document->setLocale('en_US');
        $document->setTitle('Document');
        $document->setFile('document-'.$product->getId().'.txt');
        $document->save($this->getPropelConnection());

        // The front publishes a document from the file it is stored as: it has to exist.
        $directory = $document->getUploadDir();
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }
        $path = $directory.\DIRECTORY_SEPARATOR.$document->getFile();
        file_put_contents($path, 'document');
        $this->storedFiles[] = $path;

        return (int) $document->getId();
    }

    private function link(Product $product): int
    {
        $image = $this->factory->productImage($product);
        $this->storedFiles[] = $image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile();

        $link = new ProductSaleElementsProductImage();
        $link->setProductSaleElementsId($product->getDefaultSaleElements()->getId());
        $link->setProductImageId($image->getId());
        $link->save($this->getPropelConnection());

        return (int) $link->getId();
    }

    private function tokenOf(?CustomerGroup $group): string
    {
        $customer = $this->factory->customer(
            $this->factory->customerTitle(),
            ['password' => 'password'] + (null === $group ? [] : ['customerGroup' => $group]),
        );

        return $this->authenticateAsCustomer($customer);
    }

    /**
     * @return list<int>
     */
    private function memberIds(string $uri, string $token): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return array_map(static fn (array $member): int => (int) $member['id'], $payload['hydra:member']);
    }
}
