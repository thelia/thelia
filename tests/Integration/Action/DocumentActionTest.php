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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\Exception\FileException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

final class DocumentActionTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    private ?string $documentCacheDirectory = null;

    private ?string $documentCacheFromWebRoot = null;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();

        if (null !== $this->documentCacheDirectory) {
            (new Filesystem())->remove($this->documentCacheDirectory);
        }

        // The database changes are rolled back, the static config cache is not.
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    /**
     * Apache serves the published documents itself, without the headers of the shop:
     * the document cache carries its own, so that a document is downloaded rather than
     * opened as a page of the shop origin.
     */
    public function testProcessDocumentProtectsTheDocumentCacheForApache(): void
    {
        $htaccess = $this->publishADocumentInAFreshCache().DS.'.htaccess';

        self::assertFileExists($htaccess);
        self::assertStringContainsString('Header set Content-Disposition "attachment"', (string) file_get_contents($htaccess));
        self::assertStringContainsString('Header set X-Content-Type-Options "nosniff"', (string) file_get_contents($htaccess));
    }

    public function testProcessDocumentKeepsAnHtaccessTheShopWrote(): void
    {
        $cacheDirectory = THELIA_WEB_DIR.$this->useAFreshDocumentCache();
        mkdir($cacheDirectory, 0o777, true);
        file_put_contents($cacheDirectory.DS.'.htaccess', '# the shop rules');

        $this->publishADocumentInAFreshCache(reuseTheCurrentCache: true);

        self::assertSame('# the shop rules', file_get_contents($cacheDirectory.DS.'.htaccess'));
    }

    public function testSaveDocumentPersistsModelAndMovesFile(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $tmpFile = $this->createTestTextFile('PDF placeholder content');
        $uploadedFile = $this->createUploadedFile($tmpFile, 'manual.pdf', 'application/pdf');

        $model = new ProductDocument();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $event = new FileCreateOrUpdateEvent($product->getId());
        $event
            ->setModel($model)
            ->setUploadedFile($uploadedFile)
            ->setParentName('Test Product');

        $this->dispatch($event, TheliaEvents::DOCUMENT_SAVE);

        $savedModel = $event->getModel();
        self::assertNotNull($savedModel);
        self::assertGreaterThan(0, $savedModel->getId());
        self::assertNotEmpty($savedModel->getFile());

        $finalPath = $savedModel->getUploadDir().DS.$savedModel->getFile();
        self::assertFileExists($finalPath);
        $this->trackFileForCleanup($finalPath);
    }

    /**
     * The upload policy runs before the save event; storage refuses on its own a name a
     * web server may execute, for a caller that dispatches the event without asking it.
     */
    public function testSaveDocumentRefusesAServerExecutableStoredName(): void
    {
        $product = $this->createProduct();
        $model = new ProductDocument();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $event = new FileCreateOrUpdateEvent($product->getId());
        $event
            ->setModel($model)
            ->setUploadedFile($this->createUploadedFile($this->createTestTextFile('<?php echo 1;'), 'report.php ', 'text/plain'))
            ->setParentName('Test Product');

        try {
            $this->dispatch($event, TheliaEvents::DOCUMENT_SAVE);
            self::fail('A document stored as ".php" has to be refused.');
        } catch (FileException) {
        }

        self::assertFileDoesNotExist($model->getUploadDir().DS.'report-'.$model->getId().'.php');
    }

    public function testUpdateDocumentRefusesAServerExecutableStoredNameAndKeepsTheFormerFile(): void
    {
        $product = $this->createProduct();
        $model = new ProductDocument();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $saveEvent = new FileCreateOrUpdateEvent($product->getId());
        $saveEvent
            ->setModel($model)
            ->setUploadedFile($this->createUploadedFile($this->createTestTextFile('PDF placeholder content'), 'manual.pdf', 'application/pdf'))
            ->setParentName('Test Product');
        $this->dispatch($saveEvent, TheliaEvents::DOCUMENT_SAVE);

        $saved = $saveEvent->getModel();
        $formerFile = (string) $saved->getFile();
        $formerPath = $saved->getUploadDir().DS.$formerFile;
        $this->trackFileForCleanup($formerPath);

        $updateEvent = new FileCreateOrUpdateEvent($product->getId());
        $updateEvent->setModel($saved);
        $updateEvent->setOldModel(clone $saved);
        $updateEvent->setUploadedFile($this->createUploadedFile($this->createTestTextFile('<?php echo 1;'), 'report.php ', 'text/plain'));

        try {
            $this->dispatch($updateEvent, TheliaEvents::DOCUMENT_UPDATE);
            self::fail('A document stored as ".php" has to be refused.');
        } catch (FileException) {
        }

        self::assertFileExists($formerPath);
        self::assertSame($formerFile, ProductDocumentQuery::create()->findPk($saved->getId())?->getFile());
        self::assertFileDoesNotExist($saved->getUploadDir().DS.'report-'.$saved->getId().'.php');
    }

    public function testDeleteDocumentRemovesModelAndFile(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $tmpFile = $this->createTestTextFile('Disposable document');
        $uploadedFile = $this->createUploadedFile($tmpFile, 'to-delete.txt', 'text/plain');

        $model = new ProductDocument();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $saveEvent = new FileCreateOrUpdateEvent($product->getId());
        $saveEvent
            ->setModel($model)
            ->setUploadedFile($uploadedFile)
            ->setParentName('Test Product');
        $this->dispatch($saveEvent, TheliaEvents::DOCUMENT_SAVE);

        $savedModel = $saveEvent->getModel();
        $docId = $savedModel->getId();
        $filePath = $savedModel->getUploadDir().DS.$savedModel->getFile();
        self::assertFileExists($filePath);

        $this->dispatch(new FileDeleteEvent($savedModel), TheliaEvents::DOCUMENT_DELETE);

        self::assertNull(ProductDocumentQuery::create()->findPk($docId));
        self::assertFileDoesNotExist($filePath);
    }

    public function testToggleVisibilityFlipsDocumentVisibleFlag(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $tmpFile = $this->createTestTextFile('Toggle test');
        $uploadedFile = $this->createUploadedFile($tmpFile, 'toggle.txt', 'text/plain');

        $model = new ProductDocument();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $saveEvent = new FileCreateOrUpdateEvent($product->getId());
        $saveEvent
            ->setModel($model)
            ->setUploadedFile($uploadedFile)
            ->setParentName('Test Product');
        $this->dispatch($saveEvent, TheliaEvents::DOCUMENT_SAVE);

        $savedModel = $saveEvent->getModel();
        $this->trackFileForCleanup($savedModel->getUploadDir().DS.$savedModel->getFile());

        $toggleEvent = new FileToggleVisibilityEvent(
            ProductDocumentQuery::create(),
            $savedModel->getId(),
        );
        $this->dispatch($toggleEvent, TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY);

        $reloaded = ProductDocumentQuery::create()->findPk($savedModel->getId());
        self::assertSame(0, (int) $reloaded->getVisible());
    }

    private function createProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );
    }

    /**
     * @return string the absolute path of the document cache the document was published in
     */
    private function publishADocumentInAFreshCache(bool $reuseTheCurrentCache = false): string
    {
        $cacheFromWebRoot = $reuseTheCurrentCache ? (string) $this->documentCacheFromWebRoot : $this->useAFreshDocumentCache();

        $source = $this->createTestTextFile('%PDF-1.4');
        $this->trackFileForCleanup($source);

        $event = new DocumentEvent();
        $event->setSourceFilepath($source);
        $event->setCacheSubdirectory('product');

        $this->dispatch($event, TheliaEvents::DOCUMENT_PROCESS);

        return THELIA_WEB_DIR.$cacheFromWebRoot;
    }

    private function useAFreshDocumentCache(): string
    {
        $this->documentCacheFromWebRoot = 'cache'.DS.uniqid('documents-test-');
        $this->documentCacheDirectory = THELIA_WEB_DIR.$this->documentCacheFromWebRoot;
        ConfigQuery::write('document_cache_dir_from_web_root', $this->documentCacheFromWebRoot);

        return $this->documentCacheFromWebRoot;
    }
}
