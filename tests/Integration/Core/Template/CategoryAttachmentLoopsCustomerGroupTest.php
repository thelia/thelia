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

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Category;
use Thelia\Model\CategoryAssociatedContent;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryImage;
use Thelia\Model\Content;
use Thelia\Model\CustomerGroup;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What hangs on a category closed to the customer's group (off its list, and no ancestor of a
 * listed one), its images, its documents and the contents associated with it, is hidden with
 * it by the front loops, whichever way the loop names the category; what hangs on a listed or
 * a passing category is rendered. The default group, which has no list, sees everything. The
 * back office is not narrowed.
 */
final class CategoryAttachmentLoopsCustomerGroupTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private Category $closed;

    private Category $passing;

    private Category $listed;

    private CustomerGroup $pro;

    /** @var list<string> the document files this test stored, removed afterwards */
    private array $storedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        $this->closed = $this->factory->category();
        $this->passing = $this->factory->category();
        $this->listed = $this->factory->category(['parent' => $this->passing->getId()]);
        $this->pro = $this->factory->customerGroup('pro', categoryIds: [(int) $this->listed->getId()]);
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->storedFiles as $storedFile) {
            @unlink($storedFile);
        }
        $this->session()->setCustomerUser(null);
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string<CategoryImage|CategoryDocument>, 2: string}>
     */
    public static function files(): iterable
    {
        yield 'image, category argument' => ['image', CategoryImage::class, 'category'];
        yield 'image, source argument' => ['image', CategoryImage::class, 'source'];
        yield 'document, category argument' => ['document', CategoryDocument::class, 'category'];
        yield 'document, source argument' => ['document', CategoryDocument::class, 'source'];
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('files')]
    public function testTheFilesOfAClosedCategoryAreNotRenderedToTheGroupAndThoseOfTheListedAndPassingOnesAre(string $loop, string $modelClass, string $style): void
    {
        $this->attach($modelClass, $this->closed);
        $ofPassing = $this->attach($modelClass, $this->passing);
        $ofListed = $this->attach($modelClass, $this->listed);
        $this->signInAs($this->pro);

        self::assertSame([], $this->ids($this->rows($loop, $this->arguments($style, $this->closed))));
        self::assertSame([$ofPassing], $this->ids($this->rows($loop, $this->arguments($style, $this->passing))));
        self::assertSame([$ofListed], $this->ids($this->rows($loop, $this->arguments($style, $this->listed))));
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('files')]
    public function testTheFilesOfEveryCategoryAreRenderedToTheDefaultGroup(string $loop, string $modelClass, string $style): void
    {
        $ofClosed = $this->attach($modelClass, $this->closed);
        $this->signInAs(null);

        self::assertSame([$ofClosed], $this->ids($this->rows($loop, $this->arguments($style, $this->closed))));
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('files')]
    public function testTheBackOfficeContextRendersThemAll(string $loop, string $modelClass, string $style): void
    {
        $ofClosed = $this->attach($modelClass, $this->closed);
        $this->signInAs($this->pro);

        self::assertSame([$ofClosed], $this->ids($this->rows($loop, $this->arguments($style, $this->closed) + ['backend_context' => true])));
    }

    public function testTheContentsAssociatedWithAClosedCategoryAreNotRenderedToTheGroup(): void
    {
        $folder = $this->factory->folder();
        $this->associate($this->factory->content($folder), $this->closed);
        $contentOfPassing = $this->associate($this->factory->content($folder), $this->passing);
        $contentOfListed = $this->associate($this->factory->content($folder), $this->listed);
        $this->signInAs($this->pro);

        self::assertSame([], $this->contentIds(['category' => $this->closed->getId()]));
        self::assertSame([$contentOfPassing], $this->contentIds(['category' => $this->passing->getId()]));
        self::assertSame([$contentOfListed], $this->contentIds(['category' => $this->listed->getId()]));
    }

    public function testTheContentsAssociatedWithEveryCategoryAreRenderedToTheDefaultGroup(): void
    {
        $contentOfClosed = $this->associate($this->factory->content($this->factory->folder()), $this->closed);
        $this->signInAs(null);

        self::assertSame([$contentOfClosed], $this->contentIds(['category' => $this->closed->getId()]));
    }

    public function testTheBackOfficeContextRendersTheAssociatedContentsAll(): void
    {
        $contentOfClosed = $this->associate($this->factory->content($this->factory->folder()), $this->closed);
        $this->signInAs($this->pro);

        self::assertSame([$contentOfClosed], $this->contentIds(['category' => $this->closed->getId(), 'backend_context' => true]));
    }

    /**
     * @return array<string, mixed>
     */
    private function arguments(string $style, Category $category): array
    {
        return 'source' === $style
            ? ['source' => 'category', 'source_id' => $category->getId()]
            : ['category' => $category->getId()];
    }

    /**
     * @param class-string<CategoryImage|CategoryDocument> $modelClass
     */
    private function attach(string $modelClass, Category $category): int
    {
        /** @var CategoryImage|CategoryDocument $file */
        $file = new $modelClass();
        $file->setCategoryId($category->getId());
        $file->setVisible(1);
        $file->setPosition(1);
        $file->setLocale('en_US');
        $file->setTitle('File');
        $file->setFile('file-'.$category->getId().'.png');
        $file->save($this->getPropelConnection());

        // The loop publishes a document from the file it is stored as: it has to exist.
        if ($file instanceof CategoryDocument) {
            $directory = $file->getUploadDir();
            if (!is_dir($directory)) {
                mkdir($directory, 0o775, true);
            }
            $path = $directory.\DIRECTORY_SEPARATOR.$file->getFile();
            file_put_contents($path, 'document');
            $this->storedFiles[] = $path;
        }

        return (int) $file->getId();
    }

    /**
     * @return int the id of the content, which the loop reports as CONTENT_ID
     */
    private function associate(Content $content, Category $category): int
    {
        (new CategoryAssociatedContent())
            ->setCategoryId($category->getId())
            ->setContentId($content->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return (int) $content->getId();
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<int>
     */
    private function contentIds(array $arguments): array
    {
        return array_map(static fn (array $row): int => (int) $row['CONTENT_ID'], $this->rows('associated_content', $arguments));
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
