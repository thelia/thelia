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
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryImage;
use Thelia\Model\CustomerGroup;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * What hangs on a category closed to the customer group of the reader (off its list), its
 * images and its documents, is hidden with it: out of the collection, 404 on the item. The
 * default group has no list and reads them. Two customers of different groups read the same
 * URLs and are answered differently. The admin API is not narrowed.
 */
final class CategoryAttachmentsVisibilityApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    private Category $closed;

    private Category $open;

    private CustomerGroup $pro;

    /** @var list<string> the document files this test stored, removed afterwards */
    private array $storedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->closed = $this->factory->category();
        $this->open = $this->factory->category();
        $this->pro = $this->factory->customerGroup('pro', categoryIds: [(int) $this->open->getId()]);
        $this->getService('services_resetter')->reset();
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
     * @return iterable<string, array{0: string, 1: class-string}>
     */
    public static function attachments(): iterable
    {
        yield 'images' => ['category_images', CategoryImage::class];
        yield 'documents' => ['category_documents', CategoryDocument::class];
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('attachments')]
    public function testTheAttachmentsOfAClosedCategoryLeaveTheCollectionAndTheirItemIsNotFound(string $resource, string $modelClass): void
    {
        $ofClosed = $this->attach($modelClass, $this->closed);
        $ofOpen = $this->attach($modelClass, $this->open);
        $token = $this->tokenOf($this->pro);

        $ids = $this->memberIds('/api/front/'.$resource, $token);

        self::assertNotContains($ofClosed, $ids);
        self::assertContains($ofOpen, $ids);
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofClosed, token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofClosed.'/file', token: $token)->getStatusCode(), 'The file is not served either.');
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofOpen, token: $token)->getStatusCode());
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('attachments')]
    public function testTheDefaultGroupWithNoListReadsTheAttachmentsOfEveryCategory(string $resource, string $modelClass): void
    {
        $ofClosed = $this->attach($modelClass, $this->closed);
        $proToken = $this->tokenOf($this->pro);
        $consumerToken = $this->tokenOf(null);

        self::assertContains($ofClosed, $this->memberIds('/api/front/'.$resource, $consumerToken));
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/'.$resource.'/'.$ofClosed, token: $consumerToken)->getStatusCode());
        self::assertNotContains($ofClosed, $this->memberIds('/api/front/'.$resource, $proToken), 'The answer given to one group is not kept for the next.');
        self::assertContains($ofClosed, $this->memberIds('/api/front/'.$resource, $consumerToken));
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('attachments')]
    public function testTheAdminApiIsNotNarrowed(string $resource, string $modelClass): void
    {
        $ofClosed = $this->attach($modelClass, $this->closed);

        $response = $this->jsonRequest('GET', '/api/admin/'.$resource.'/'.$ofClosed, token: $this->authenticateAsAdmin());

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    private function attach(string $modelClass, Category $category): int
    {
        /** @var CategoryImage|CategoryDocument $attachment */
        $attachment = new $modelClass();
        $attachment->setCategoryId($category->getId());
        $attachment->setVisible(1);
        $attachment->setPosition(1);
        $attachment->setLocale('en_US');
        $attachment->setTitle('Attachment');
        $attachment->setFile('attachment-'.$category->getId().'.png');
        $attachment->save($this->getPropelConnection());

        // The front publishes a document from the file it is stored as: it has to exist.
        if ($attachment instanceof CategoryDocument) {
            $directory = $attachment->getUploadDir();
            if (!is_dir($directory)) {
                mkdir($directory, 0o775, true);
            }
            $path = $directory.\DIRECTORY_SEPARATOR.$attachment->getFile();
            file_put_contents($path, 'document');
            $this->storedFiles[] = $path;
        }

        return (int) $attachment->getId();
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
