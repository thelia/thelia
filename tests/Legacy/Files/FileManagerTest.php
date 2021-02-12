<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Files;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Files\FileManager;
use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\Base\ContentImageQuery;
use Thelia\Model\Base\FolderImageQuery;
use Thelia\Model\BrandDocument;
use Thelia\Model\BrandDocumentQuery;
use Thelia\Model\BrandImage;
use Thelia\Model\BrandImageQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\CategoryImage;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\ContentImage;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderDocument;
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\FolderImage;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductQuery;

/**
 * Class FileManagerTest.
 */
class FileManagerTest extends TestCase
{
    /** @var FileManager */
    protected $fileManager;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        $this->fileManager = new FileManager([
            'document.product' => 'Thelia\\Model\\ProductDocument',
            'image.product' => 'Thelia\\Model\\ProductImage',

            'document.category' => 'Thelia\\Model\\CategoryDocument',
            'image.category' => 'Thelia\\Model\\CategoryImage',

            'document.content' => 'Thelia\\Model\\ContentDocument',
            'image.content' => 'Thelia\\Model\\ContentImage',

            'document.folder' => 'Thelia\\Model\\FolderDocument',
            'image.folder' => 'Thelia\\Model\\FolderImage',

            'document.brand' => 'Thelia\\Model\\BrandDocument',
            'image.brand' => 'Thelia\\Model\\BrandImage',
        ]);

        $this->container->set('thelia.file_manager', $this->fileManager);
    }

    public function testGetFileTypeIdentifier(): void
    {
        $obj = $this->fileManager->getModelInstance('document', 'product');
        $this->assertInstanceOf('Thelia\\Model\\ProductDocument', $obj);

        $obj = $this->fileManager->getModelInstance('image', 'product');
        $this->assertInstanceOf('Thelia\\Model\\ProductImage', $obj);

        $obj = $this->fileManager->getModelInstance('document', 'category');
        $this->assertInstanceOf('Thelia\\Model\\CategoryDocument', $obj);

        $obj = $this->fileManager->getModelInstance('image', 'category');
        $this->assertInstanceOf('Thelia\\Model\\CategoryImage', $obj);

        $obj = $this->fileManager->getModelInstance('document', 'content');
        $this->assertInstanceOf('Thelia\\Model\\ContentDocument', $obj);

        $obj = $this->fileManager->getModelInstance('image', 'content');
        $this->assertInstanceOf('Thelia\\Model\\ContentImage', $obj);

        $obj = $this->fileManager->getModelInstance('document', 'folder');
        $this->assertInstanceOf('Thelia\\Model\\FolderDocument', $obj);

        $obj = $this->fileManager->getModelInstance('image', 'folder');
        $this->assertInstanceOf('Thelia\\Model\\FolderImage', $obj);

        $obj = $this->fileManager->getModelInstance('document', 'brand');
        $this->assertInstanceOf('Thelia\\Model\\BrandDocument', $obj);

        $obj = $this->fileManager->getModelInstance('image', 'brand');
        $this->assertInstanceOf('Thelia\\Model\\BrandImage', $obj);
    }

    public function testGetFileTypeIdentifierWrongType(): void
    {
        $this->expectException(\Thelia\Exception\FileException::class);
        $this->fileManager->getModelInstance('docment', 'product');
    }

    public function testGetFileTypeIdentifierWrongObject(): void
    {
        $this->expectException(\Thelia\Exception\FileException::class);
        $this->fileManager->getModelInstance('document', 'poney');
    }

    public function testGetFileTypeIdentifierWrongTypeAndObject(): void
    {
        $this->expectException(\Thelia\Exception\FileException::class);
        $this->fileManager->getModelInstance('licorne', 'poney');
    }

    public function testAddFileModel(): void
    {
        $this->fileManager->addFileModel('licorne', 'poney', 'Thelia\\Model\\ProductDocument');

        $obj = $this->fileManager->getModelInstance('licorne', 'poney');

        $this->assertInstanceOf('Thelia\\Model\\ProductDocument', $obj);
    }

    public function addFileModelWrongClassTest(): void
    {
        $this->fileManager->addFileModel('licorne', 'poney', 'Thelia\\Model\\Product');

        $this->expectException(\Thelia\Exception\FileException::class);
        $this->fileManager->getModelInstance('licorne', 'poney');
    }

    public function dotTestImageUpload($model, $type): void
    {
        $old_file = $model->getFile();

        $model->setFile(null)->save();

        $testFile = __DIR__.DS.'fixtures'.DS.'move-test.gif';
        $targetFile = THELIA_LOCAL_DIR.'media'.DS.'images'.DS.$type.DS.'original-'.$model->getId().'.gif';

        @unlink($testFile);
        @unlink($targetFile);

        copy(__DIR__.DS.'fixtures'.DS.'test.gif', $testFile);

        $uploadedFile = new UploadedFile(
            $testFile,
            'original.gif',
            'image/gif',
            filesize($testFile),
            UPLOAD_ERR_OK,
            true
        );

        $file = $this->fileManager->copyUploadedFile($model, $uploadedFile);

        // Normalize path
        $file = str_replace('/', DS, $file);

        $this->assertEquals(''.$file, $targetFile);

        $this->assertEquals(basename($targetFile), $model->getFile());

        $this->assertFileExists($targetFile);

        @unlink($targetFile);
        @unlink($testFile);

        $model->setFile($old_file)->save();
    }

    public function dotTestDocumentUpload($model, $type): void
    {
        $old_file = $model->getFile();

        $model->setFile(null)->save();

        $testFile = __DIR__.DS.'fixtures'.DS.'move-test.txt';
        $targetFile = THELIA_LOCAL_DIR.'media'.DS.'documents'.DS.$type.DS.'original-'.$model->getId().'.txt';

        @unlink($testFile);
        @unlink($targetFile);

        copy(__DIR__.DS.'fixtures'.DS.'test.txt', $testFile);

        $uploadedFile = new UploadedFile(
            $testFile,
            'original.txt',
            'plain/text',
            filesize($testFile),
            UPLOAD_ERR_OK,
            true
        );

        $file = $this->fileManager->copyUploadedFile($model, $uploadedFile);

        // Normalize path
        $file = str_replace('/', DS, $file);

        $this->assertEquals(''.$file, $targetFile);

        $this->assertEquals(basename($targetFile), $model->getFile());

        $this->assertFileExists($targetFile);

        @unlink($targetFile);
        @unlink($testFile);

        $model->setFile($old_file)->save();
    }

    public function testCopyUploadedFileProductImage(): void
    {
        $this->dotTestImageUpload(
            ProductImageQuery::create()->findOne(),
            'product'
        );
    }

    public function testCopyUploadedFileCategoryImage(): void
    {
        $this->dotTestImageUpload(
            CategoryImageQuery::create()->findOne(),
            'category'
        );
    }

    public function testCopyUploadedFileContentImage(): void
    {
        $this->dotTestImageUpload(
            ContentImageQuery::create()->findOne(),
            'content'
        );
    }

    public function testCopyUploadedFileFolderImage(): void
    {
        $this->dotTestImageUpload(
            FolderImageQuery::create()->findOne(),
            'folder'
        );
    }

    public function testCopyUploadedFileBrandImage(): void
    {
        $this->dotTestImageUpload(
            BrandImageQuery::create()->findOne(),
            'brand'
        );
    }

    public function testCopyUploadedFileProductDocument(): void
    {
        $this->dotTestDocumentUpload(
            ProductDocumentQuery::create()->findOne(),
            'product'
        );
    }

    public function testCopyUploadedFileCategoryDocument(): void
    {
        $this->dotTestDocumentUpload(
            CategoryDocumentQuery::create()->findOne(),
            'category'
        );
    }

    public function testCopyUploadedFileContentDocument(): void
    {
        $this->dotTestDocumentUpload(
            ContentDocumentQuery::create()->findOne(),
            'content'
        );
    }

    public function testCopyUploadedFileFolderDocument(): void
    {
        $this->dotTestDocumentUpload(
            FolderDocumentQuery::create()->findOne(),
            'folder'
        );
    }

    public function testCopyUploadedFileBrandDocument(): void
    {
        $this->dotTestDocumentUpload(
            BrandDocumentQuery::create()->findOne(),
            'brand'
        );
    }

    public function testSanitizeFileName(): void
    {
        $file = $this->fileManager->sanitizeFileName("C:\\../test/\\..file/%ù^name \t\n\r²&²:.txt");

        $this->assertEquals('c..test..filename.txt', $file);

        $file = $this->fileManager->sanitizeFileName('/etc/passwd');

        $this->assertEquals('etcpasswd', $file);
    }

    public function doTestDeleteFile($model, $modelParent, $type, $obj): void
    {
        $targetFile = THELIA_LOCAL_DIR.'media'.DS.$type.DS.$obj.DS.'original-'.$model->getId().'.txt';

        $model->setParentId($modelParent->getId())->setFile(basename($targetFile))->save();

        @unlink($targetFile);

        copy(__DIR__.DS.'fixtures'.DS.'test.txt', $targetFile);

        $this->assertFileExists($targetFile);

        $this->fileManager->deleteFile($model);

        $this->assertFileDoesNotExist($targetFile);
    }

    public function testDeleteFileProductDocument(): void
    {
        $this->doTestDeleteFile(
            new ProductDocument(),
            ProductQuery::create()->findOne(),
            'documents',
            'product'
        );
    }

    public function testDeleteFileProductImage(): void
    {
        $this->doTestDeleteFile(
            new ProductImage(),
            ProductQuery::create()->findOne(),
            'images',
            'product'
        );
    }

    public function testDeleteFileCategoryDocument(): void
    {
        $this->doTestDeleteFile(
            new CategoryDocument(),
            CategoryQuery::create()->findOne(),
            'documents',
            'category'
        );
    }

    public function testDeleteFileCategoryImage(): void
    {
        $this->doTestDeleteFile(
            new CategoryImage(),
            CategoryQuery::create()->findOne(),
            'images',
            'category'
        );
    }

    public function testDeleteFileFolderDocument(): void
    {
        $this->doTestDeleteFile(
            new FolderDocument(),
            FolderQuery::create()->findOne(),
            'documents',
            'folder'
        );
    }

    public function testDeleteFileFolderImage(): void
    {
        $this->doTestDeleteFile(
            new FolderImage(),
            FolderQuery::create()->findOne(),
            'images',
            'folder'
        );
    }

    public function testDeleteFileContentDocument(): void
    {
        $this->doTestDeleteFile(
            new ContentDocument(),
            ContentQuery::create()->findOne(),
            'documents',
            'content'
        );
    }

    public function testDeleteFileContentImage(): void
    {
        $this->doTestDeleteFile(
            new ContentImage(),
            ContentQuery::create()->findOne(),
            'images',
            'content'
        );
    }

    public function testDeleteFileBrandDocument(): void
    {
        $this->doTestDeleteFile(
            new BrandDocument(),
            BrandQuery::create()->findOne(),
            'documents',
            'brand'
        );
    }

    public function testDeleteFileBrandImage(): void
    {
        $this->doTestDeleteFile(
            new BrandImage(),
            BrandQuery::create()->findOne(),
            'images',
            'brand'
        );
    }
}
