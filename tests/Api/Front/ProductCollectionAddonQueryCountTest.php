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

use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryItemImage;

/**
 * A module extends a native resource with an addon, and an addon resolves its
 * own rows. The `libraryImages` addon of TheliaLibrary belongs to the single
 * read only, yet it was built for every row of every collection: 495 reads of
 * library_item_image on the home page of the demo shop, a third of the page,
 * for a payload that never mentions them.
 */
final class ProductCollectionAddonQueryCountTest extends ApiTestCase
{
    use RecordsSqlQueries;

    public function testTheCollectionDoesNotBuildAnAddonItDoesNotReturn(): void
    {
        $product = $this->productWithALibraryImage();

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/products');
        });

        self::assertArrayNotHasKey('ProductLibraryImagesAddon', $this->member($payload, $product->getId()));
        self::assertSame(
            0,
            self::countSqlQueriesSelectingFrom($statements, 'library_item_image'),
            'The collection returns no library image, so it must not read any.',
        );
    }

    public function testTheSingleReadStillReturnsTheAddon(): void
    {
        $product = $this->productWithALibraryImage();

        $payload = $this->readJson('/api/front/products/'.$product->getId());

        self::assertCount(1, $payload['ProductLibraryImagesAddon']['libraryImages'] ?? []);
    }

    private function productWithALibraryImage(): Product
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $image = (new LibraryImage())
            ->setLocale('en_US')
            ->setTitle('Sample image')
            ->setFileName('sample.png');
        $image->save();

        $itemImage = (new LibraryItemImage())
            ->setImageId($image->getId())
            ->setItemType('product')
            ->setItemId($product->getId())
            ->setVisible(1)
            ->setPosition(0);
        $itemImage->save();

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function member(array $payload, ?int $productId): array
    {
        foreach ($payload['hydra:member'] ?? [] as $member) {
            if (($member['id'] ?? null) === $productId) {
                return $member;
            }
        }

        self::fail('The collection must return the product under test, otherwise nothing is measured.');
    }
}
