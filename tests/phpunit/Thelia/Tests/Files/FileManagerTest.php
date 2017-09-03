<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Files;

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
 * Class FileManagerTest
 *
 * @package Thelia\Tests\Files
 */
class FileManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var  FileManager */
    protected $fileManager;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();

        $this->fileManager = new FileManager([
            "document.product" => "Thelia\\Model\\ProductDocument",
            "image.product" => "Thelia\\Model\\ProductImage",

            "document.category" => "Thelia\\Model\\CategoryDocument",
            "image.category" => "Thelia\\Model\\CategoryImage",

            "document.content" => "Thelia\\Model\\ContentDocument",
            "image.content" => "Thelia\\Model\\ContentImage",

            "document.folder" => "Thelia\\Model\\FolderDocument",
            "image.folder" => "Thelia\\Model\\FolderImage",

            "document.brand" => "Thelia\\Model\\BrandDocument",
            "image.brand" => "Thelia\\Model\\BrandImage",
        ]);

        $this->container->set("thelia.file_manager", $this->fileManager);
    }

    public function testGetFileTypeIdentifier()
    {
        $obj = $this->fileManager->getModelInstance('document', 'product');
        $this->assertInstanceOf("Thelia\\Model\\ProductDocument", $obj);

        $obj = $this->fileManager->getModelInstance('image', 'product');
        $this->assertInstanceOf("Thelia\\Model\\ProductImage", $obj);

        $obj = $this->fileManager->getModelInstance('document', 'category');
        $this->assertInstanceOf("Thelia\\Model\\CategoryDocument", $obj);

        $obj = $this->fileManager->getModelInstance('image', 'category');
        $this->assertInstanceOf("Thelia\\Model\\CategoryImage", $obj);

        $obj = $this->fileManager->getModelInstance('document', 'content');
        $this->assertInstanceOf("Thelia\\Model\\ContentDocument", $obj);

        $obj = $this->fileManager->getModelInstance('image', 'content');
        $this->assertInstanceOf("Thelia\\Model\\ContentImage", $obj);

        $obj = $this->fileManager->getModelInstance('document', 'folder');
        $this->assertInstanceOf("Thelia\\Model\\FolderDocument", $obj);

        $obj = $this->fileManager->getModelInstance('image', 'folder');
        $this->assertInstanceOf("Thelia\\Model\\FolderImage", $obj);

        $obj = $this->fileManager->getModelInstance('document', 'brand');
        $this->assertInstanceOf("Thelia\\Model\\BrandDocument", $obj);

        $obj = $this->fileManager->getModelInstance('image', 'brand');
        $this->assertInstanceOf("Thelia\\Model\\BrandImage", $obj);
    }

    /**
     * @expectedException \Thelia\Exception\FileException
     */
    public function testGetFileTypeIdentifierWrongType()
    {
        $obj = $this->fileManager->getModelInstance('docment', 'product');
    }

    /**
     * @expectedException \Thelia\Exception\FileException
     */
    public function testGetFileTypeIdentifierWrongObject()
    {
        $obj = $this->fileManager->getModelInstance('document', 'poney');
    }

    /**
     * @expectedException \Thelia\Exception\FileException
     */
    public function testGetFileTypeIdentifierWrongTypeAndObject()
    {
        $obj = $this->fileManager->getModelInstance('licorne', 'poney');
    }

    public function testAddFileModel()
    {
        $this->fileManager->addFileModel("licorne", "poney", "Thelia\\Model\\ProductDocument");

        $obj = $this->fileManager->getModelInstance('licorne', 'poney');

        $this->assertInstanceOf("Thelia\\Model\\ProductDocument", $obj);
    }

    /**
     * @expectedException \Thelia\Exception\FileException
     */
    public function addFileModelWrongClassTest()
    {
        $this->fileManager->addFileModel("licorne", "poney", "Thelia\\Model\\Product");

        $obj = $this->fileManager->getModelInstance('licorne', 'poney');
    }

    public function dotTestImageUpload($model, $type)
    {
        $old_file = $model->getFile();

        $model->setFile(null)->save();

        $testFile = __DIR__ .DS. 'fixtures' .DS. 'move-test.gif';
        $targetFile = THELIA_LOCAL_DIR . 'media'.DS.'images'.DS.$type.DS."original-".$model->getId().".gif";

        @unlink($testFile);
        @unlink($targetFile);

        copy(__DIR__ .DS. 'fixtures' .DS. 'test.gif', $testFile);

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

        $this->assertEquals("".$file, $targetFile);

        $this->assertEquals(basename($targetFile), $model->getFile());

        $this->assertFileExists($targetFile);

        @unlink($targetFile);
        @unlink($testFile);

        $model->setFile($old_file)->save();
    }

    public function dotTestDocumentUpload($model, $type)
    {
        $old_file = $model->getFile();

        $model->setFile(null)->save();

        $testFile = __DIR__ .DS. 'fixtures' .DS. 'move-test.txt';
        $targetFile = THELIA_LOCAL_DIR . 'media'.DS.'documents'.DS.$type.DS."original-".$model->getId().".txt";

        @unlink($testFile);
        @unlink($targetFile);

        copy(__DIR__ .DS. 'fixtures' .DS. 'test.txt', $testFile);

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

        $this->assertEquals("".$file, $targetFile);

        $this->assertEquals(basename($targetFile), $model->getFile());

        $this->assertFileExists($targetFile);

        @unlink($targetFile);
        @unlink($testFile);

        $model->setFile($old_file)->save();
    }

    public function testCopyUploadedFileProductImage()
    {
        $this->dotTestImageUpload(
            ProductImageQuery::create()->findOne(),
            'product'
        );
    }

    public function testCopyUploadedFileCategoryImage()
    {
        $this->dotTestImageUpload(
            CategoryImageQuery::create()->findOne(),
            'category'
        );
    }

    public function testCopyUploadedFileContentImage()
    {
        $this->dotTestImageUpload(
            ContentImageQuery::create()->findOne(),
            'content'
        );
    }

    public function testCopyUploadedFileFolderImage()
    {
        $this->dotTestImageUpload(
            FolderImageQuery::create()->findOne(),
            'folder'
        );
    }

    public function testCopyUploadedFileBrandImage()
    {
        $this->dotTestImageUpload(
            BrandImageQuery::create()->findOne(),
            'brand'
        );
    }

    public function testCopyUploadedFileProductDocument()
    {
        $this->dotTestDocumentUpload(
            ProductDocumentQuery::create()->findOne(),
            'product'
        );
    }

    public function testCopyUploadedFileCategoryDocument()
    {
        $this->dotTestDocumentUpload(
            CategoryDocumentQuery::create()->findOne(),
            'category'
        );
    }

    public function testCopyUploadedFileContentDocument()
    {
        $this->dotTestDocumentUpload(
            ContentDocumentQuery::create()->findOne(),
            'content'
        );
    }

    public function testCopyUploadedFileFolderDocument()
    {
        $this->dotTestDocumentUpload(
            FolderDocumentQuery::create()->findOne(),
            'folder'
        );
    }

    public function testCopyUploadedFileBrandDocument()
    {
        $this->dotTestDocumentUpload(
            BrandDocumentQuery::create()->findOne(),
            'brand'
        );
    }

    public function testSanitizeFileName()
    {
        $file = $this->fileManager->sanitizeFileName("C:\\../test/\\..file/%ù^name \t\n\r²&²:.txt");

        $this->assertEquals("c..test..filename.txt", $file);

        $file = $this->fileManager->sanitizeFileName("/etc/passwd");

        $this->assertEquals("etcpasswd", $file);
    }

    public function doTestDeleteFile($model, $modelParent, $type, $obj)
    {
        $targetFile = THELIA_LOCAL_DIR . 'media'.DS.$type.DS.$obj.DS."original-".$model->getId().".txt";

        $model->setParentId($modelParent->getId())->setFile(basename($targetFile))->save();

        @unlink($targetFile);

        copy(__DIR__ .DS. 'fixtures' .DS. 'test.txt', $targetFile);

        $this->assertFileExists($targetFile);

        $this->fileManager->deleteFile($model);

        $this->assertFileNotExists($targetFile);
    }

    public function testDeleteFileProductDocument()
    {
        $this->doTestDeleteFile(
            new ProductDocument(),
            ProductQuery::create()->findOne(),
            'documents',
            'product'
        );
    }

    public function testDeleteFileProductImage()
    {
        $this->doTestDeleteFile(
            new ProductImage(),
            ProductQuery::create()->findOne(),
            'images',
            'product'
        );
    }

    public function testDeleteFileCategoryDocument()
    {
        $this->doTestDeleteFile(
            new CategoryDocument(),
            CategoryQuery::create()->findOne(),
            'documents',
            'category'
        );
    }

    public function testDeleteFileCategoryImage()
    {
        $this->doTestDeleteFile(
            new CategoryImage(),
            CategoryQuery::create()->findOne(),
            'images',
            'category'
        );
    }
    public function testDeleteFileFolderDocument()
    {
        $this->doTestDeleteFile(
            new FolderDocument(),
            FolderQuery::create()->findOne(),
            'documents',
            'folder'
        );
    }

    public function testDeleteFileFolderImage()
    {
        $this->doTestDeleteFile(
            new FolderImage(),
            FolderQuery::create()->findOne(),
            'images',
            'folder'
        );
    }

    public function testDeleteFileContentDocument()
    {
        $this->doTestDeleteFile(
            new ContentDocument(),
            ContentQuery::create()->findOne(),
            'documents',
            'content'
        );
    }

    public function testDeleteFileContentImage()
    {
        $this->doTestDeleteFile(
            new ContentImage(),
            ContentQuery::create()->findOne(),
            'images',
            'content'
        );
    }
    public function testDeleteFileBrandDocument()
    {
        $this->doTestDeleteFile(
            new BrandDocument(),
            BrandQuery::create()->findOne(),
            'documents',
            'brand'
        );
    }

    public function testDeleteFileBrandImage()
    {
        $this->doTestDeleteFile(
            new BrandImage(),
            BrandQuery::create()->findOne(),
            'images',
            'brand'
        );
    }
}
