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
use Thelia\Model\CustomerGroup;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The images and the documents of a product the customer's group may not see, the only
 * category it sits in being off the list of that group, are hidden with it by the front loops,
 * whichever way the loop names the product. A group with no list, and the default group, are
 * asked the same loops and see everything. The back office is not narrowed.
 */
final class ProductAttachmentLoopsCustomerGroupTest extends IntegrationTestCase
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
        $this->session()->setCustomerUser(null);
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function files(): iterable
    {
        yield 'image, product argument' => ['image', 'product'];
        yield 'image, source argument' => ['image', 'source'];
        yield 'document, product argument' => ['document', 'product'];
        yield 'document, source argument' => ['document', 'source'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('files')]
    public function testTheFilesOfAProductOffTheListAreNotRenderedToTheGroup(string $loop, string $style): void
    {
        $this->attach($loop, $this->hidden);
        $ofShown = $this->attach($loop, $this->shown);
        $this->signInAs($this->groupA);

        self::assertSame([], $this->ids($this->rows($loop, $this->arguments($style, $this->hidden))));
        self::assertSame([$ofShown], $this->ids($this->rows($loop, $this->arguments($style, $this->shown))));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('files')]
    public function testTheFilesOfTheProductAreRenderedToAnotherGroupAndToTheDefaultOne(string $loop, string $style): void
    {
        $ofHidden = $this->attach($loop, $this->hidden);

        foreach ([$this->groupB, null] as $group) {
            $this->signInAs($group);

            self::assertSame([$ofHidden], $this->ids($this->rows($loop, $this->arguments($style, $this->hidden))), $group?->getCode() ?? 'default group');
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('files')]
    public function testTheBackOfficeContextRendersThemAll(string $loop, string $style): void
    {
        $ofHidden = $this->attach($loop, $this->hidden);
        $this->signInAs($this->groupA);

        self::assertSame([$ofHidden], $this->ids($this->rows($loop, $this->arguments($style, $this->hidden) + ['backend_context' => true])));
    }

    private function productIn(Category $category): Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
    }

    /**
     * @return array<string, mixed>
     */
    private function arguments(string $style, Product $product): array
    {
        return 'source' === $style
            ? ['source' => 'product', 'source_id' => $product->getId()]
            : ['product' => $product->getId()];
    }

    private function attach(string $loop, Product $product): int
    {
        if ('image' === $loop) {
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

        // The loop publishes a document from the file it is stored as: it has to exist.
        $directory = $document->getUploadDir();
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }
        $path = $directory.\DIRECTORY_SEPARATOR.$document->getFile();
        file_put_contents($path, 'document');
        $this->storedFiles[] = $path;

        return (int) $document->getId();
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
