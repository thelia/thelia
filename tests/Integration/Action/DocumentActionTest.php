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

use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

final class DocumentActionTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
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
}
