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

use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\ProductAssociationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\Admin;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductAssociationTypeI18nTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\ProductAssociationTypeI18nQuery;
use Thelia\Model\ProductAssociationTypeQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;

/**
 * The admin endpoints write through the product facade and the type events, not
 * through Propel.
 *
 * That is the whole point of the two processors: the events fire, a reciprocal
 * type gets its mirror row, and the guards the back office meets — the accessory
 * type cannot be deleted, nor can a type still carrying relations — hold for the
 * API just the same.
 */
final class ProductAssociationApiTest extends ApiTestCase
{
    private const ALL_ACCESSES = [
        AccessManager::VIEW,
        AccessManager::CREATE,
        AccessManager::UPDATE,
        AccessManager::DELETE,
    ];

    private Currency $currency;
    private Category $category;
    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    public function testWritingARelationAnnouncesItAndSavesIt(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $associated = $this->product();

        $announced = [];
        $listener = static function (ProductAssociationEvent $event) use (&$announced): void {
            $announced[] = $event->getProduct()->getId().'-'.$event->getAssociatedProductId();
        };

        $this->listenTo(TheliaEvents::PRODUCT_ADD_ASSOCIATION, $listener, function () use ($token, $product, $associated): void {
            $response = $this->post($token, $product, $associated, ProductAssociationType::CODE_ACCESSORY);

            self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        });

        self::assertSame(
            [$product->getId().'-'.$associated->getId()],
            $announced,
            'The write goes through the facade, so the event fires exactly once.',
        );

        self::assertCount(1, $this->facade()->getAssociations((int) $product->getId(), ProductAssociationType::CODE_ACCESSORY));
    }

    public function testWritingARelationOfAReciprocalTypeWritesTheMirrorRow(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $associated = $this->product();

        $response = $this->post($token, $product, $associated, ProductAssociationType::CODE_CROSS_SELLING);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        self::assertCount(
            1,
            $this->facade()->getAssociations((int) $associated->getId(), ProductAssociationType::CODE_CROSS_SELLING),
            'A reciprocal type is worth stating both ways, and the listener writes the other one.',
        );
    }

    public function testRelatingAProductToItselfIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();

        $response = $this->post($token, $product, $product, ProductAssociationType::CODE_ACCESSORY);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testWritingARelationWithoutATokenIsRefused(): void
    {
        $product = $this->product();
        $associated = $this->product();

        $response = $this->post(null, $product, $associated, ProductAssociationType::CODE_ACCESSORY);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testAnAdminWithoutTheProductRightCannotReadTheRelations(): void
    {
        $token = $this->authenticateAsAdmin($this->configurationOnlyAdmin());

        $response = $this->jsonRequest('GET', '/api/admin/product_associations', token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDeletingARelationOfAReciprocalTypeRemovesBothDirections(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $associated = $this->product();

        $this->facade()->addAssociation((int) $product->getId(), (int) $associated->getId(), ProductAssociationType::CODE_CROSS_SELLING);

        $relations = $this->facade()->getAssociations((int) $product->getId(), ProductAssociationType::CODE_CROSS_SELLING);
        $response = $this->jsonRequest('DELETE', '/api/admin/product_associations/'.$relations[0]->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->facade()->getAssociations((int) $product->getId(), ProductAssociationType::CODE_CROSS_SELLING));
        self::assertSame([], $this->facade()->getAssociations((int) $associated->getId(), ProductAssociationType::CODE_CROSS_SELLING));
    }

    public function testCreatingATypeGoesThroughTheEventAndKeepsItsWording(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'spare_part',
            'visible' => true,
            'reciprocal' => false,
            'i18ns' => ['en_US' => ['title' => 'Spare parts', 'description' => 'What wears out first']],
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $created = ProductAssociationTypeQuery::create()->findOneByCode('spare_part');

        self::assertInstanceOf(ProductAssociationType::class, $created);
        self::assertSame('Spare parts', $created->setLocale('en_US')->getTitle());
    }

    public function testATypePostedWithoutItsFlagsIsCreatedVisibleAndNotReciprocal(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'spare_part_unflagged',
            'i18ns' => ['en_US' => ['title' => 'Spare parts']],
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $created = ProductAssociationTypeQuery::create()->findOneByCode('spare_part_unflagged');

        self::assertInstanceOf(ProductAssociationType::class, $created);
        self::assertSame(1, $created->getVisible(), 'A flag the payload leaves out falls back on the default the schema carries.');
        self::assertSame(0, $created->getReciprocal(), 'A flag the payload leaves out falls back on the default the schema carries.');
    }

    public function testTheCodeOfATypeIsIgnoredOnUpdate(): void
    {
        $token = $this->authenticateAsAdmin();
        $type = $this->type(ProductAssociationType::CODE_UP_SELLING);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_association_types/'.$type->getId(), [
            'code' => 'renamed',
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame(
            ProductAssociationType::CODE_UP_SELLING,
            ProductAssociationTypeQuery::create()->findPk($type->getId())?->getCode(),
            'The core names a type by its code, so an update leaves it alone.',
        );
    }

    public function testTheAccessoryTypeCannotBeDeletedThroughTheApi(): void
    {
        $token = $this->authenticateAsAdmin();
        $type = $this->type(ProductAssociationType::CODE_ACCESSORY);

        $response = $this->jsonRequest('DELETE', '/api/admin/product_association_types/'.$type->getId(), token: $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertNotNull(ProductAssociationTypeQuery::create()->findPk($type->getId()));
    }

    public function testATypeStillCarryingRelationsCannotBeDeletedThroughTheApi(): void
    {
        $token = $this->authenticateAsAdmin();
        $type = $this->type(ProductAssociationType::CODE_UP_SELLING);

        $product = $this->product();
        $associated = $this->product();
        $this->facade()->addAssociation((int) $product->getId(), (int) $associated->getId(), ProductAssociationType::CODE_UP_SELLING);

        $response = $this->jsonRequest('DELETE', '/api/admin/product_association_types/'.$type->getId(), token: $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertNotNull(ProductAssociationTypeQuery::create()->findPk($type->getId()));
    }

    public function testAnAdminWithoutTheConfigurationRightCannotReadTheTypes(): void
    {
        $token = $this->authenticateAsAdmin($this->catalogueOnlyAdmin());

        $response = $this->jsonRequest('GET', '/api/admin/product_association_types', token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testWritingARelationWithoutATypeIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $associated = $this->product();

        try {
            $response = $this->jsonRequest('POST', '/api/admin/product_associations', [
                'product' => '/api/admin/products/'.$product->getId(),
                'associatedProduct' => '/api/admin/products/'.$associated->getId(),
            ], $token);
        } catch (\Throwable $crash) {
            self::fail(
                'A relation payload with no type must be refused, not crash the write: '
                .$crash::class.' — '.$crash->getMessage(),
            );
        }

        self::assertSame(
            422,
            $response->getStatusCode(),
            'A relation payload with no type is refused as unprocessable, not answered by a 500: '
            .substr((string) $response->getContent(), 0, 300),
        );
    }

    public function testCreatingATypeWithACodeAlreadyTakenIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $this->client->catchExceptions(true);

        $response = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => ProductAssociationType::CODE_ACCESSORY,
            'visible' => true,
            'reciprocal' => false,
            'i18ns' => ['en_US' => ['title' => 'Accessories, again']],
        ], $token);

        self::assertSame(
            422,
            $response->getStatusCode(),
            'A code already taken is refused as unprocessable, not answered by a 500: '
            .substr((string) $response->getContent(), 0, 300),
        );
    }

    public function testCreatingATypeWithoutAnyWordingIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $this->client->catchExceptions(true);

        $response = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'untitled_block',
            'visible' => true,
            'reciprocal' => false,
        ], $token);

        self::assertSame(
            422,
            $response->getStatusCode(),
            'A type with no title heads a front-office block with nothing: it is refused. Answer was: '
            .substr((string) $response->getContent(), 0, 300),
        );
    }

    public function testHidingATypeLeavesItsWordingAlone(): void
    {
        $token = $this->authenticateAsAdmin();

        $created = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'goes_well_with',
            'visible' => true,
            'reciprocal' => false,
            'i18ns' => [
                'en_US' => ['title' => 'Goes well with'],
                'fr_FR' => ['title' => 'Va bien avec'],
            ],
        ], $token);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());

        $id = (int) (json_decode((string) $created->getContent(), true)['id'] ?? 0);

        self::assertSame('Goes well with', $this->wording($id, 'en_US'));

        $patched = $this->jsonRequest(
            'PATCH',
            '/api/admin/product_association_types/'.$id,
            ['visible' => false],
            $token,
            'merge-patch+json',
        );

        self::assertSame(200, $patched->getStatusCode(), (string) $patched->getContent());

        self::assertSame(
            'Goes well with',
            $this->wording($id, 'en_US'),
            'A patch that carries no wording must leave the wording of every language where it was.',
        );
        self::assertSame('Va bien avec', $this->wording($id, 'fr_FR'));
    }

    public function testRewordingATypeLeavesItsDescriptionAlone(): void
    {
        $token = $this->authenticateAsAdmin();

        $created = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'goes_well_with',
            'visible' => true,
            'reciprocal' => false,
            'i18ns' => [
                'en_US' => [
                    'title' => 'Goes well with',
                    'description' => 'Products our customers buy along with this one.',
                ],
            ],
        ], $token);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());

        $id = (int) (json_decode((string) $created->getContent(), true)['id'] ?? 0);

        self::assertSame('Products our customers buy along with this one.', $this->description($id, 'en_US'));

        $patched = $this->jsonRequest(
            'PATCH',
            '/api/admin/product_association_types/'.$id,
            ['i18ns' => ['en_US' => ['title' => 'Goes nicely with']]],
            $token,
            'merge-patch+json',
        );

        self::assertSame(200, $patched->getStatusCode(), (string) $patched->getContent());
        self::assertSame('Goes nicely with', $this->wording($id, 'en_US'));
        self::assertSame(
            'Products our customers buy along with this one.',
            $this->description($id, 'en_US'),
            'A patch carrying only a title must leave the description of that language where it was.',
        );
    }

    public function testATypeTitledInAnotherLanguageThanTheFirstOneIsWritten(): void
    {
        // The order of the keys of a JSON object is not something the client chooses
        // on purpose: the guard on the title reads the whole payload.
        $token = $this->authenticateAsAdmin();

        $this->client->catchExceptions(true);

        $created = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'goes_well_with',
            'visible' => true,
            'reciprocal' => false,
            'i18ns' => [
                'en_US' => ['description' => 'Products our customers buy along with this one.'],
                'fr_FR' => ['title' => 'Va bien avec'],
            ],
        ], $token);

        self::assertSame(
            201,
            $created->getStatusCode(),
            'The payload carries a title, in French, so the type is written. Answer was: '
            .substr((string) $created->getContent(), 0, 300),
        );

        $id = (int) (json_decode((string) $created->getContent(), true)['id'] ?? 0);

        self::assertSame('Va bien avec', $this->wording($id, 'fr_FR'));
        self::assertSame('Va bien avec', $this->wording($id, 'en_US'), 'The shop language borrows the title it was not given.');
        self::assertSame('Products our customers buy along with this one.', $this->description($id, 'en_US'));
    }

    private function post(?string $token, Product $product, Product $associated, string $typeCode)
    {
        return $this->jsonRequest('POST', '/api/admin/product_associations', [
            'product' => '/api/admin/products/'.$product->getId(),
            'associatedProduct' => '/api/admin/products/'.$associated->getId(),
            'type' => '/api/admin/product_association_types/'.$this->type($typeCode)->getId(),
        ], $token);
    }

    private function listenTo(string $eventName, callable $listener, callable $body): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        self::assertInstanceOf(SymfonyEventDispatcherInterface::class, $dispatcher);

        $dispatcher->addListener($eventName, $listener, -1024);

        try {
            $body();
        } finally {
            $dispatcher->removeListener($eventName, $listener);
        }
    }

    private function catalogueOnlyAdmin(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::PRODUCT => self::ALL_ACCESSES,
        ]);
    }

    private function configurationOnlyAdmin(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::CONFIG => self::ALL_ACCESSES,
        ]);
    }

    private function type(string $code): ProductAssociationType
    {
        $type = ProductAssociationTypeQuery::create()->findOneByCode($code);

        self::assertInstanceOf(ProductAssociationType::class, $type, \sprintf('The install seeds the "%s" type.', $code));

        return $type;
    }

    private function product(): Product
    {
        return $this->createFixtureFactory()->product($this->category, $this->taxRule, $this->currency);
    }

    private function wording(int $id, string $locale): ?string
    {
        ProductAssociationTypeI18nTableMap::clearInstancePool();

        return ProductAssociationTypeI18nQuery::create()
            ->filterById($id)
            ->filterByLocale($locale)
            ->findOne()
            ?->getTitle();
    }

    private function description(int $id, string $locale): ?string
    {
        ProductAssociationTypeI18nTableMap::clearInstancePool();

        return ProductAssociationTypeI18nQuery::create()
            ->filterById($id)
            ->filterByLocale($locale)
            ->findOne()
            ?->getDescription();
    }

    private function facade(): ProductFacade
    {
        return $this->getService(ProductFacade::class);
    }
}
