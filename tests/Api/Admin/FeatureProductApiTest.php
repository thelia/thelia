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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Map\FeatureAvI18nTableMap;
use Thelia\Model\Map\FeatureProductTableMap;
use Thelia\Test\ApiTestCase;

/**
 * Writing the feature values of a product one at a time over the admin API.
 *
 * A free text is stored the way the back office stores it: as a translated
 * value of its own, so the assertions read the translation table rather than
 * the row. Ids are taken as integers before each request because the test and
 * the request share one Propel instance pool.
 */
final class FeatureProductApiTest extends ApiTestCase
{
    public function testAFreeTextIsWrittenInTheDefaultLanguage(): void
    {
        [$productId, $featureId] = $this->productAndFeature();

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$productId,
                'feature' => '/api/admin/features/'.$featureId,
                'is_free_text' => true,
                'free_text_value' => '0.50',
            ],
            $this->authenticateAsAdmin(),
            'json',
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('0.50', $payload['free_text_value']);
        self::assertTrue($payload['is_free_text']);
        self::assertArrayNotHasKey('locale', $payload);

        self::assertSame(['en_US' => '0.50'], $this->freeTextTranslations($productId, $featureId));
        self::assertSame((int) $payload['id'], $this->freeTextRowId($productId, $featureId));
    }

    public function testAFreeTextReadsBackAsItWasWritten(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $token = $this->authenticateAsAdmin();
        $id = $this->postFreeText($productId, $featureId, '0.50', $token);

        $response = $this->jsonRequest('GET', '/api/admin/feature_products/'.$id, token: $token, format: 'json');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['is_free_text'] ?? null, (string) $response->getContent());
        self::assertSame('0.50', $payload['free_text_value'] ?? null, (string) $response->getContent());
    }

    public function testAFreeTextIsReplacedInPlace(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $token = $this->authenticateAsAdmin();
        $id = $this->postFreeText($productId, $featureId, '6', $token);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/feature_products/'.$id,
            ['free_text_value' => '12'],
            $token,
            'merge-patch+json',
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($id, $payload['id']);
        self::assertSame('12', $payload['free_text_value']);

        self::assertSame(['en_US' => '12'], $this->freeTextTranslations($productId, $featureId));
        self::assertSame(1, $this->rowCount($productId, $featureId));
    }

    public function testASecondPostRewritesTheFreeTextInPlace(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $token = $this->authenticateAsAdmin();
        $id = $this->postFreeText($productId, $featureId, '6', $token);

        $secondId = $this->postFreeText($productId, $featureId, '12', $token);

        self::assertSame($id, $secondId);
        self::assertSame(['en_US' => '12'], $this->freeTextTranslations($productId, $featureId));
        self::assertSame(1, $this->rowCount($productId, $featureId));
    }

    public function testAFreeTextWrittenInAnotherLanguageKeepsTheFirstTranslation(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $token = $this->authenticateAsAdmin();
        $id = $this->postFreeText($productId, $featureId, 'Cotton', $token);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/feature_products/'.$id,
            ['free_text_value' => 'Coton', 'locale' => 'fr_FR'],
            $token,
            'merge-patch+json',
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('Coton', json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['free_text_value']);
        self::assertSame(['en_US' => 'Cotton', 'fr_FR' => 'Coton'], $this->freeTextTranslations($productId, $featureId));
        self::assertSame(1, $this->rowCount($productId, $featureId));
    }

    public function testALanguageTheShopDoesNotHaveIsRefused(): void
    {
        [$productId, $featureId] = $this->productAndFeature();

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$productId,
                'feature' => '/api/admin/features/'.$featureId,
                'is_free_text' => true,
                'free_text_value' => '6',
                'locale' => 'xx_XX',
            ],
            $this->authenticateAsAdmin(),
            'json',
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, $this->rowCount($productId, $featureId));
    }

    public function testAFreeTextIsDeletedWithItsValue(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $token = $this->authenticateAsAdmin();
        $id = $this->postFreeText($productId, $featureId, '6', $token);
        $featureAvId = (int) FeatureProductQuery::create()
            ->filterById($id)
            ->select([FeatureProductTableMap::COL_FEATURE_AV_ID])
            ->findOne();

        $response = $this->jsonRequest('DELETE', '/api/admin/feature_products/'.$id, token: $token);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('', (string) $response->getContent());
        self::assertSame(0, $this->rowCount($productId, $featureId));
        self::assertSame(0, FeatureAvQuery::create()->filterById($featureAvId)->count(), 'The free text value must not be left behind as a value of the feature.');
    }

    public function testAListedValueIsDeletedWithoutItsSiblings(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $feature = $factory->feature();
        $productId = (int) $product->getId();
        $featureId = (int) $feature->getId();
        $redId = (int) $factory->featureAv($feature)->getId();
        $blueId = (int) $factory->featureAv($feature)->getId();
        $token = $this->authenticateAsAdmin();

        $ids = [];
        foreach ([$redId, $blueId] as $featureAvId) {
            $response = $this->jsonRequest(
                'POST',
                '/api/admin/feature_products',
                [
                    'product' => '/api/admin/products/'.$productId,
                    'feature' => '/api/admin/features/'.$featureId,
                    'featureAv' => '/api/admin/feature_avs/'.$featureAvId,
                ],
                $token,
                'json',
            );
            self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
            $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertFalse($payload['is_free_text']);
            $ids[] = (int) $payload['id'];
        }

        self::assertSame([$redId, $blueId], $this->listedValueIds($productId, $featureId));

        $response = $this->jsonRequest('DELETE', '/api/admin/feature_products/'.$ids[0], token: $token);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([$blueId], $this->listedValueIds($productId, $featureId));
        self::assertSame(2, FeatureAvQuery::create()->filterByFeatureId($featureId)->count(), 'A listed value belongs to the feature and stays.');
    }

    public function testAValueOfAnotherFeatureIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $productId = (int) $product->getId();
        $featureId = (int) $factory->feature()->getId();
        $otherValueId = (int) $factory->featureAv($factory->feature())->getId();

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$productId,
                'feature' => '/api/admin/features/'.$featureId,
                'featureAv' => '/api/admin/feature_avs/'.$otherValueId,
            ],
            $this->authenticateAsAdmin(),
            'json',
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, $this->rowCount($productId, $featureId));
    }

    /**
     * The value a free text is stored as belongs to the product that wrote it:
     * shared, it would be renamed and deleted along with the owner's text.
     */
    public function testTheValueOfAnotherProductsFreeTextIsRefused(): void
    {
        [$ownerId, $featureId] = $this->productAndFeature();
        $factory = $this->createFixtureFactory();
        $otherId = (int) $factory->product($factory->category(), $factory->taxRule(), $factory->currency())->getId();
        $token = $this->authenticateAsAdmin();
        $this->postFreeText($ownerId, $featureId, 'Private text', $token);
        $freeTextValueId = (int) FeatureProductQuery::create()
            ->filterByProductId($ownerId)
            ->filterByFeatureId($featureId)
            ->select([FeatureProductTableMap::COL_FEATURE_AV_ID])
            ->findOne();

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$otherId,
                'feature' => '/api/admin/features/'.$featureId,
                'featureAv' => '/api/admin/feature_avs/'.$freeTextValueId,
            ],
            $token,
            'json',
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, $this->rowCount($otherId, $featureId));
    }

    /**
     * The update action looks a free text up by product and feature only: let it
     * reach a listed value and it would rename a value other products share.
     */
    public function testAFreeTextOnAFeatureHoldingAListedValueIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $feature = $factory->feature();
        $sharedValue = $factory->featureAv($feature, ['title' => 'Red']);
        $factory->featureProduct($product, $sharedValue);
        $productId = (int) $product->getId();
        $featureId = (int) $feature->getId();
        $sharedValueId = (int) $sharedValue->getId();

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$productId,
                'feature' => '/api/admin/features/'.$featureId,
                'is_free_text' => true,
                'free_text_value' => 'Crimson',
            ],
            $this->authenticateAsAdmin(),
            'json',
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(
            'Red',
            FeatureAvI18nQuery::create()
                ->filterById($sharedValueId)
                ->filterByLocale('en_US')
                ->select([FeatureAvI18nTableMap::COL_TITLE])
                ->findOne(),
        );
    }

    public function testAListedValueIsNotEditedInPlace(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $featureProductId = (int) $factory->featureProduct($product, $factory->featureAv($factory->feature()))->getId();

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/feature_products/'.$featureProductId,
            ['free_text_value' => 'Crimson'],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAProfileWithoutTheProductWriteRightIsRefused(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $id = $this->postFreeText($productId, $featureId, '6', $this->authenticateAsAdmin());

        $readOnly = $this->authenticateAsAdmin(
            $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]),
        );

        $post = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$productId,
                'feature' => '/api/admin/features/'.$featureId,
                'is_free_text' => true,
                'free_text_value' => '7',
            ],
            $readOnly,
            'json',
        );
        $patch = $this->jsonRequest('PATCH', '/api/admin/feature_products/'.$id, ['free_text_value' => '7'], $readOnly, 'merge-patch+json');
        $delete = $this->jsonRequest('DELETE', '/api/admin/feature_products/'.$id, token: $readOnly);

        self::assertSame(Response::HTTP_FORBIDDEN, $post->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $patch->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $delete->getStatusCode());
        self::assertSame(['en_US' => '6'], $this->freeTextTranslations($productId, $featureId));
    }

    /**
     * A POST is checked against the right to create products: rewriting a text
     * the product already holds is an update, so it goes through PATCH.
     */
    public function testAFreeTextIsNotRewrittenThroughPostWithoutTheUpdateRight(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $this->postFreeText($productId, $featureId, 'original', $this->authenticateAsAdmin());

        $createOnly = $this->authenticateAsAdmin(
            $this->createFixtureFactory()->restrictedAdmin([
                AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::CREATE],
            ]),
        );

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$productId,
                'feature' => '/api/admin/features/'.$featureId,
                'is_free_text' => true,
                'free_text_value' => 'overwritten',
            ],
            $createOnly,
            'json',
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['en_US' => 'original'], $this->freeTextTranslations($productId, $featureId));
        self::assertSame(1, $this->rowCount($productId, $featureId));
    }

    public function testTheFrontRoutesStayReadOnly(): void
    {
        [$productId, $featureId] = $this->productAndFeature();
        $id = $this->postFreeText($productId, $featureId, '6', $this->authenticateAsAdmin());

        $post = $this->jsonRequest('POST', '/api/front/feature_products', [
            'product' => '/api/front/products/'.$productId,
            'feature' => '/api/front/features/'.$featureId,
            'is_free_text' => true,
            'free_text_value' => '7',
        ], format: 'json');
        $patch = $this->jsonRequest('PATCH', '/api/front/feature_products/'.$id, ['free_text_value' => '7'], format: 'merge-patch+json');
        $delete = $this->jsonRequest('DELETE', '/api/front/feature_products/'.$id);

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $post->getStatusCode());
        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $patch->getStatusCode());
        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $delete->getStatusCode());
        self::assertSame(['en_US' => '6'], $this->freeTextTranslations($productId, $featureId));
    }

    /**
     * @return array{int, int}
     */
    private function productAndFeature(): array
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        return [(int) $product->getId(), (int) $factory->feature()->getId()];
    }

    private function postFreeText(int $productId, int $featureId, string $text, string $token): int
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/feature_products',
            [
                'product' => '/api/admin/products/'.$productId,
                'feature' => '/api/admin/features/'.$featureId,
                'is_free_text' => true,
                'free_text_value' => $text,
            ],
            $token,
            'json',
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        return (int) json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];
    }

    /**
     * @return array<string, string> the translations of the product's free text, by locale
     */
    private function freeTextTranslations(int $productId, int $featureId): array
    {
        $featureAvId = (int) FeatureProductQuery::create()
            ->filterByProductId($productId)
            ->filterByFeatureId($featureId)
            ->filterByIsFreeText(true)
            ->select([FeatureProductTableMap::COL_FEATURE_AV_ID])
            ->findOne();

        $translations = [];
        foreach (FeatureAvI18nQuery::create()
            ->filterById($featureAvId)
            ->orderByLocale()
            ->select([FeatureAvI18nTableMap::COL_LOCALE, FeatureAvI18nTableMap::COL_TITLE])
            ->find() as $row) {
            $translations[(string) $row[FeatureAvI18nTableMap::COL_LOCALE]] = (string) $row[FeatureAvI18nTableMap::COL_TITLE];
        }

        return $translations;
    }

    private function freeTextRowId(int $productId, int $featureId): int
    {
        return (int) FeatureProductQuery::create()
            ->filterByProductId($productId)
            ->filterByFeatureId($featureId)
            ->filterByIsFreeText(true)
            ->select([FeatureProductTableMap::COL_ID])
            ->findOne();
    }

    private function rowCount(int $productId, int $featureId): int
    {
        return FeatureProductQuery::create()
            ->filterByProductId($productId)
            ->filterByFeatureId($featureId)
            ->count();
    }

    /**
     * @return list<int>
     */
    private function listedValueIds(int $productId, int $featureId): array
    {
        return array_map('intval', FeatureProductQuery::create()
            ->filterByProductId($productId)
            ->filterByFeatureId($featureId)
            ->orderByFeatureAvId()
            ->select([FeatureProductTableMap::COL_FEATURE_AV_ID])
            ->find()
            ->getData());
    }
}
