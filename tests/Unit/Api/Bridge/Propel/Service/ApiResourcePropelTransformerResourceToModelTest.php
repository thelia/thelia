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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Service;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Validator\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\Service\TypeCasterService;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\I18nCollection;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\Template;
use Thelia\Tests\Unit\Api\Bridge\Propel\Service\Fixture\RecordingProduct;

/**
 * Writing a resource sent by a client into the Propel model that will be saved.
 *
 * The resource is validated first. Each property reaches the model through its setter, under the
 * column name it maps to, with its value bridged to the strict type of that setter. A related
 * resource is written as the foreign key of its model, a null never overwrites a NOT NULL column,
 * and a PUT, which replaces the row, resets what its payload left out without touching what the
 * operation does not let the client write. A resource carrying an id addresses an existing row.
 */
final class ApiResourcePropelTransformerResourceToModelTest extends TestCase
{
    public function testTheResourceIsValidatedAgainstTheWriteContextOfTheOperation(): void
    {
        $resource = new class(new RecordingProduct()) extends WrittenResource {
        };

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::once())->method('validate')->with($resource, ['groups' => ['product:write']]);

        $this->transformer($validator)->resourceToModel($resource, new Post(denormalizationContext: ['groups' => ['product:write']]));
    }

    public function testAResourceWithoutAnIdBecomesANewRow(): void
    {
        $product = new RecordingProduct();

        $this->transformer()->resourceToModel($this->identifiedResource($product, null), new Post(denormalizationContext: []));

        self::assertTrue($product->isNew());
    }

    public function testAResourceCarryingAnIdAddressesAnExistingRow(): void
    {
        $product = new RecordingProduct();

        $this->transformer()->resourceToModel($this->identifiedResource($product, 7), new Post(denormalizationContext: []));

        self::assertFalse($product->isNew());
    }

    public function testTheIdOfATopLevelResourceIsNotCopiedIntoItsModel(): void
    {
        $product = new RecordingProduct();

        $this->transformer()->resourceToModel($this->identifiedResource($product, 7), new Post(denormalizationContext: []));

        self::assertNull($product->getId());
    }

    public function testAResourceWrittenUnderItsParentKeepsItsIdAndItsOtherFields(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public ?int $id = 7;

            public ?string $note = 'nested';

            public function getId(): ?int
            {
                return $this->id;
            }

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []), [], new Product());

        self::assertSame(7, $product->getId());
        self::assertSame([['nested']], $product->calls['setNote']);
    }

    public function testTheTranslationsOfAResourceAreWrittenUnderTheirLocale(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource implements TranslatableResourceInterface {
            public function setI18ns(array $i18ns): self
            {
                return $this;
            }

            public function addI18n(I18n $i18n, string $locale): self
            {
                return $this;
            }

            public function getI18ns(): I18nCollection
            {
                return (new I18nCollection())->add(new WrittenTitleI18n(['title' => 'Chaise']), 'fr_FR');
            }

            public static function getI18nResourceClass(): string
            {
                return WrittenTitleI18n::class;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame([['fr_FR', 'Chaise']], $product->calls['setTitle'] ?? null);
    }

    public function testARelatedResourceIsWrittenAsTheForeignKeyOfItsModel(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public ?WrittenResource $template = null;

            public function getTemplate(): ?WrittenResource
            {
                return $this->template;
            }
        };
        $resource->template = new class((new Template())->setId(3)) extends WrittenResource {
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame(3, $product->getTemplateId());
    }

    public function testAnObjectThatIsNotAResourceIsWrittenAsItIs(): void
    {
        $product = new RecordingProduct();
        $createdAt = new \DateTimeImmutable('-1 day');
        $resource = new class($product) extends WrittenResource {
            public ?\DateTimeInterface $createdAt = null;

            public function getCreatedAt(): ?\DateTimeInterface
            {
                return $this->createdAt;
            }
        };
        $resource->createdAt = $createdAt;

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame($createdAt->format('Y-m-d H:i:s'), $product->getCreatedAt('Y-m-d H:i:s'));
    }

    public function testAPropertyIsWrittenUnderTheColumnItIsMappedTo(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            #[Column(propelFieldName: 'note')]
            public ?string $label = 'renamed';

            public function getLabel(): ?string
            {
                return $this->label;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame([['renamed']], $product->calls['setNote'] ?? null);
    }

    public function testANullNeverOverwritesANotNullColumn(): void
    {
        $product = (new RecordingProduct())->setRef('PRD-1');
        $resource = new class($product) extends WrittenResource {
            public ?string $ref = null;

            public function getRef(): ?string
            {
                return $this->ref;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame('PRD-1', $product->getRef());
    }

    public function testAnExplicitNullReachesASetterThatAcceptsIt(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public ?string $note = null;

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame([[null]], $product->calls['setNote'] ?? null);
    }

    public function testANumberReachesASetterTypedForAStringAsAString(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public float $amount = 12.5;

            public int $code = 3;

            public function getAmount(): float
            {
                return $this->amount;
            }

            public function getCode(): int
            {
                return $this->code;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame([['12.5']], $product->calls['setAmount'] ?? null);
        self::assertSame([['3']], $product->calls['setCode'] ?? null);
    }

    public function testAnIntegerReachesASetterTypedForAnIntegerUnchanged(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public int $quantity = 4;

            public function getQuantity(): int
            {
                return $this->quantity;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame([[4]], $product->calls['setQuantity'] ?? null);
    }

    public function testASetterThatTakesAForceFlagIsCalledWithIt(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public ?string $stamp = 'forced';

            public function getStamp(): ?string
            {
                return $this->stamp;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertContains(['forced', true], $product->calls['setStamp'] ?? []);
    }

    public function testASetterWithoutAForceFlagIsCalledOnceWithTheValueAlone(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public ?string $note = 'once';

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame([['once']], $product->calls['setNote'] ?? null);
    }

    public function testAPostLeavesOutAPropertyItsPayloadDidNotCarry(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            #[Groups(['product:write'])]
            public ?string $note;

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: ['groups' => ['product:write']]));

        self::assertArrayNotHasKey('setNote', $product->calls);
    }

    public function testAPutWritesThePropertiesItsPayloadCarries(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups(['product:write'])]
            public ?string $note = 'sent';

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->put($resource);

        self::assertSame([['sent']], $product->calls['setNote'] ?? null);
    }

    public function testAPutResetsAWritablePropertyItsPayloadLeftOut(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups(['product:write'])]
            public ?string $note;

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->put($resource);

        self::assertSame([[null]], $product->calls['setNote'] ?? null);
    }

    public function testAPutResetsAPropertyItsPayloadLeftOutWhoseGroupIsGivenAsASingleName(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups('product:write')]
            public ?string $note;

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->put($resource);

        self::assertSame([[null]], $product->calls['setNote'] ?? null);
    }

    public function testAPutWhoseOperationNamesNoWriteGroupResetsWhatItsPayloadLeftOut(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups(['product:write'])]
            public ?string $note;

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->put($resource, []);

        self::assertSame([[null]], $product->calls['setNote'] ?? null);
    }

    public function testAPutLeavesAlonePropertiesItsOperationDoesNotLetTheClientWrite(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups(['product:read'])]
            public ?string $note = 'resource default';

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->put($resource);

        self::assertArrayNotHasKey('setNote', $product->calls);
    }

    public function testTheIdOfATranslationNeverReachesTheModel(): void
    {
        $product = (new RecordingProduct())->setId(5);
        $resource = $this->translatedResource($product, new WrittenTitleI18n(['id' => 40, 'title' => 'Chaise']));

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame(5, $product->getId());
    }

    public function testATranslationFieldTheClientDidNotSendIsLeftOutQuietly(): void
    {
        $product = new RecordingProduct();
        $resource = $this->translatedResource($product, new WrittenSummaryI18n(['title' => 'Chaise']));

        set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));
        } finally {
            restore_error_handler();
        }

        self::assertSame([['fr_FR', 'Chaise']], $product->calls['setTitle'] ?? null);
    }

    public function testARelatedResourceWithoutItsModelIsWrittenByItsOwnId(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends WrittenResource {
            public ?WrittenResource $shelf = null;

            public function getShelf(): ?WrittenResource
            {
                return $this->shelf;
            }
        };
        $resource->shelf = new class extends WrittenResource {
            public function getId(): int
            {
                return 9;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame([[9]], $product->calls['setShelfId'] ?? null);
    }

    public function testANullNeverOverwritesTheNotNullColumnAPropertyIsMappedTo(): void
    {
        $product = (new RecordingProduct())->setRef('PRD-1');
        $resource = new class($product) extends WrittenResource {
            #[Column(propelFieldName: 'ref')]
            public ?string $reference = null;

            public function getReference(): ?string
            {
                return $this->reference;
            }
        };

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertSame('PRD-1', $product->getRef());
    }

    public function testAResourceWhoseIdentifyingRelationsMatchAnExistingRowUpdatesIt(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
        };
        $resource->product = $this->identified(1);
        $resource->category = $this->identified(2);

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertFalse($product->isNew());
    }

    public function testAResourceMissingOneOfItsIdentifyingRelationsBecomesANewRow(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
        };
        $resource->product = $this->identified(1);

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []));

        self::assertTrue($product->isNew());
    }

    public function testAnElementReplacedUnderItsParentIsAttachedToIt(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
        };

        $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: ['groups' => ['product:write']]),
            ['request' => new Request()],
            (new Product())->setId(5),
        );

        self::assertSame([[5]], $product->calls['setProductId'] ?? null);
    }

    public function testAPutAddressesTheRowItsUrlNames(): void
    {
        $resource = new class(new RecordingProduct()) extends IdentifiedResource {
        };

        $model = $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: ['groups' => ['product:write']]),
            ['request' => new Request(attributes: ['id' => 12])],
        );

        self::assertSame(12, $model->getId());
    }

    public function testTheIdAResourceCarriesWinsOverTheIdInTheUrl(): void
    {
        $resource = new class(new RecordingProduct()) extends IdentifiedResource {
            public function getId(): int
            {
                return 30;
            }
        };

        $model = $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: ['groups' => ['product:write']]),
            ['request' => new Request(attributes: ['id' => 12])],
        );

        self::assertSame(30, $model->getId());
    }

    public function testAnElementReplacedUnderItsParentIgnoresTheIdInTheUrl(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends IdentifiedResource {
        };

        $model = $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: ['groups' => ['product:write']]),
            ['request' => new Request(attributes: ['id' => 12])],
            (new Product())->setId(5),
        );

        self::assertSame($product, $model);
    }

    public function testAPutOnAResourceIdentifiedByItsRelationsIgnoresTheIdInTheUrl(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends IdentifiedResource {
            public ?WrittenResource $product;

            public ?WrittenResource $category;
        };

        $model = $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: ['groups' => ['product:write']]),
            ['request' => new Request(attributes: ['id' => 12])],
        );

        self::assertSame($product, $model);
    }

    public function testAPutCarryingNoIdWritesTheResourceOwnModel(): void
    {
        $product = new RecordingProduct();
        $resource = new class($product) extends IdentifiedResource {
        };

        $model = $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: ['groups' => ['product:write']]),
            ['request' => new Request()],
        );

        self::assertSame($product, $model);
    }

    public function testAnElementUnderAParentIsIdentifiedByTheParentForTheRelationNamedAfterIt(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
        };
        $resource->category = $this->identified(2);

        $this->transformer()->resourceToModel($resource, new Post(denormalizationContext: []), [], (new Product())->setId(5));

        self::assertFalse($product->isNew());
    }

    public function testTheParentOfAReplacedElementIsMarkedForSaving(): void
    {
        $parent = (new Product())->setId(5);
        $parent->resetModified();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class(new RecordingProduct()) extends ReplacedResource {
        };

        $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: ['groups' => ['product:write']]),
            ['request' => new Request()],
            $parent,
        );

        self::assertTrue($parent->isColumnModified(ProductTableMap::COL_ID));
    }

    public function testAPutLeavesAloneAnAbsentPropertyItsOperationDoesNotWriteAndGoesOn(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups(['product:read'])]
            public ?string $note;

            #[Groups(['product:read'])]
            #[Relation(targetResource: WrittenResource::class)]
            public ?WrittenResource $shelf;

            #[Groups(['product:write'])]
            public int $quantity = 4;

            public function getNote(): ?string
            {
                return $this->note;
            }

            public function getQuantity(): int
            {
                return $this->quantity;
            }

            public function getShelf(): ?WrittenResource
            {
                return $this->shelf;
            }
        };

        $this->put($resource);

        self::assertArrayNotHasKey('setNote', $product->calls);
        self::assertArrayNotHasKey('setShelf', $product->calls);
        self::assertSame([[4]], $product->calls['setQuantity'] ?? null);
    }

    public function testAPutResetsARelationItsPayloadLeftOut(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups(['product:write'])]
            #[Relation(targetResource: WrittenResource::class)]
            public ?WrittenResource $shelf;

            public function getShelf(): ?WrittenResource
            {
                return $this->shelf;
            }
        };

        $this->put($resource);

        self::assertSame([[null]], $product->calls['setShelf'] ?? null);
    }

    public function testAPutWritesAPropertyThatDeclaresNoGroups(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            public ?string $note = 'ungrouped';

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->put($resource);

        self::assertSame([['ungrouped']], $product->calls['setNote'] ?? null);
    }

    public function testAPutWritesAPropertyWhoseGroupIsGivenAsASingleName(): void
    {
        $product = new RecordingProduct();
        $resource = new #[CompositeIdentifiers(['product', 'category'])] class($product) extends ReplacedResource {
            #[Groups('product:write')]
            public ?string $note = 'sent';

            public function getNote(): ?string
            {
                return $this->note;
            }
        };

        $this->put($resource);

        self::assertSame([['sent']], $product->calls['setNote'] ?? null);
    }

    private function translatedResource(ActiveRecordInterface $model, I18n $i18n): PropelResourceInterface
    {
        $resource = new class($model) extends WrittenResource implements TranslatableResourceInterface {
            public ?I18n $translation = null;

            public function setI18ns(array $i18ns): self
            {
                return $this;
            }

            public function addI18n(I18n $i18n, string $locale): self
            {
                return $this;
            }

            public function getI18ns(): I18nCollection
            {
                return (new I18nCollection())->add($this->translation, 'fr_FR');
            }

            public static function getI18nResourceClass(): string
            {
                return I18n::class;
            }
        };
        $resource->translation = $i18n;

        return $resource;
    }

    private function identified(int $id): WrittenResource
    {
        return new class($id) extends WrittenResource {
            public function __construct(private readonly int $identifier)
            {
                parent::__construct();
            }

            public function getId(): int
            {
                return $this->identifier;
            }
        };
    }

    private function put(PropelResourceInterface $resource, array $denormalizationContext = ['groups' => ['product:write']]): void
    {
        $this->transformer()->resourceToModel(
            $resource,
            new Put(denormalizationContext: $denormalizationContext),
            ['request' => new Request()],
        );
    }

    private function identifiedResource(ActiveRecordInterface $model, ?int $id): PropelResourceInterface
    {
        $resource = new class($model) extends WrittenResource {
            public ?int $id = null;

            public function getId(): ?int
            {
                return $this->id;
            }
        };
        $resource->id = $id;

        return $resource;
    }

    private function transformer(?ValidatorInterface $validator = null): ApiResourcePropelTransformerService
    {
        return new ApiResourcePropelTransformerService(
            [],
            $validator ?? $this->createStub(ValidatorInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(TypeCasterService::class),
        );
    }
}

abstract class WrittenResource implements PropelResourceInterface
{
    public function __construct(private ?ActiveRecordInterface $writtenModel = null)
    {
    }

    public function __get(string $property)
    {
        return null;
    }

    public function setPropelModel(ActiveRecordInterface $propelModel): self
    {
        $this->writtenModel = $propelModel;

        return $this;
    }

    public function getPropelModel(): ?ActiveRecordInterface
    {
        return $this->writtenModel;
    }

    public function getResourceAddons(): array
    {
        return [];
    }

    public function getResourceAddon(string $addonName): ?ResourceAddonInterface
    {
        return null;
    }

    public function setResourceAddon(string $addonName, ?ResourceAddonInterface $addon): self
    {
        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return null;
    }
}

/**
 * A resource identified by two relations, like a product in a category: a PUT on it finds its
 * row through them, so writing it never queries the row by id. Attributes are not inherited:
 * each resource declares the identifiers itself.
 */
abstract class ReplacedResource extends WrittenResource
{
    public ?WrittenResource $product;

    public ?WrittenResource $category;

    public function getProduct(): ?WrittenResource
    {
        return $this->product;
    }

    public function getCategory(): ?WrittenResource
    {
        return $this->category;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        $tableMap = new TableMap('replaced_row');
        $tableMap->setClassName(ReplacedRowQuery::MODEL_CLASS);

        return $tableMap;
    }
}

/**
 * The query of the replaced resource's rows, in memory: product 1 and product 5 are in
 * category 2, and a query finds the first row matching the filters it was given.
 */
final class ReplacedRowQuery
{
    public const MODEL_CLASS = 'Thelia\\Tests\\Unit\\Api\\Bridge\\Propel\\Service\\ReplacedRow';

    private array $map = [];

    public static function create(): self
    {
        return new self();
    }

    public function filterByProductId(int $productId): self
    {
        $this->map['product_id'] = $productId;

        return $this;
    }

    public function filterByCategoryId(int $categoryId): self
    {
        $this->map['category_id'] = $categoryId;

        return $this;
    }

    public function getMap(): array
    {
        return $this->map;
    }

    public function findOne(): ?object
    {
        foreach ([[1, 2], [5, 2]] as [$productId, $categoryId]) {
            if (($this->map['product_id'] ?? $productId) === $productId && ($this->map['category_id'] ?? $categoryId) === $categoryId) {
                return new \stdClass();
            }
        }

        return null;
    }
}

/**
 * A resource identified by its id alone, whose rows are looked up in memory.
 */
abstract class IdentifiedResource extends WrittenResource
{
    public static function getPropelRelatedTableMap(): ?TableMap
    {
        $tableMap = new TableMap('identified_row');
        $tableMap->setClassName(IdentifiedRowQuery::MODEL_CLASS);

        return $tableMap;
    }
}

/**
 * The query of the identified resource's rows, in memory: the row of any id exists, and a
 * query that filters on nothing finds the first row of the table, as SQL would.
 */
final class IdentifiedRowQuery
{
    public const MODEL_CLASS = 'Thelia\\Tests\\Unit\\Api\\Bridge\\Propel\\Service\\IdentifiedRow';

    private array $map = [];

    public static function create(): self
    {
        return new self();
    }

    public function filterById(int $id): self
    {
        $this->map['id'] = $id;

        return $this;
    }

    public function getMap(): array
    {
        return $this->map;
    }

    public function findOne(): RecordingProduct
    {
        return (new RecordingProduct())->setId($this->map['id'] ?? 1);
    }
}

final class WrittenTitleI18n extends I18n
{
    public ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }
}

final class WrittenSummaryI18n extends I18n
{
    public ?string $title = null;

    public ?string $chapo;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }
}
