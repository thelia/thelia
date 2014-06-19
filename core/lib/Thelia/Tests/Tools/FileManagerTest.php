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

namespace Thelia\Tests\Tools;

use Thelia\Core\Event\Document\DocumentCreateOrUpdateEvent;
use Thelia\Core\Event\Image\ImageCreateOrUpdateEvent;

use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Tools\FileManager;

/**
 * Class FileManagerTest
 *
 * @package Thelia\Tests\Tools
 */
class FileManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Thelia\Tools\FileManager::copyUploadedFile
     */
/*    public function testCopyUploadedFile()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet : Mock issue'
        );

        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnValue('translated'));

        $stubRequest = $this->getMockBuilder('\Thelia\Core\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $stubSecurity = $this->getMockBuilder('\Thelia\Core\Security\SecurityContext')
            ->disableOriginalConstructor()
            ->getMock();
        $stubSecurity->expects($this->any())
            ->method('getAdminUser')
            ->will($this->returnValue(new Admin()));

        // Create a map of arguments to return values.
        $map = array(
            array('thelia.translator', $stubTranslator),
            array('request', $stubRequest),
            array('thelia.securityContext', $stubSecurity)
        );
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap($map));

        $stubProductImage = $this->getMockBuilder('\Thelia\Model\ProductImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductImage->expects($this->any())
            ->method('getUploadDir')
            ->will($this->returnValue(THELIA_LOCAL_DIR . 'media/images/product'));
        $stubProductImage->expects($this->any())
            ->method('getId')
            ->will($this->returnValue(42));
        $stubProductImage->expects($this->any())
            ->method('setFile')
            ->will($this->returnValue(true));
        $stubProductImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(0));

        $stubUploadedFile = $this->getMockBuilder('\Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalName')
            ->will($this->returnValue('goodName'));
        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalExtension')
            ->will($this->returnValue('png'));
        $stubUploadedFile->expects($this->any())
            ->method('move')
            ->will($this->returnValue($stubUploadedFile));

        $fileManager = new FileManager($stubContainer);

        $newUploadedFiles = array();

        $actual = $fileManager->copyUploadedFile(24, FileManager::TYPE_PRODUCT, $stubProductImage, $stubUploadedFile, $newUploadedFiles, FileManager::FILE_TYPE_IMAGES);

        $this->assertCount(1, $actual);
    }*/

    /**
     * @covers Thelia\Tools\FileManager::copyUploadedFile
     * @expectedException \Thelia\Exception\ImageException
     */
    /*public function testCopyUploadedFileExceptionImageException()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet : Mock issue'
        );

        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnValue('translated'));

        $stubRequest = $this->getMockBuilder('\Thelia\Core\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $stubSecurity = $this->getMockBuilder('\Thelia\Core\Security\SecurityContext')
            ->disableOriginalConstructor()
            ->getMock();
        $stubSecurity->expects($this->any())
            ->method('getAdminUser')
            ->will($this->returnValue(new Admin()));

        // Create a map of arguments to return values.
        $map = array(
            array('thelia.translator', $stubTranslator),
            array('request', $stubRequest),
            array('thelia.securityContext', $stubSecurity)
        );
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap($map));

        $stubProductImage = $this->getMockBuilder('\Thelia\Model\ProductImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductImage->expects($this->any())
            ->method('getUploadDir')
            ->will($this->returnValue(THELIA_LOCAL_DIR . 'media/images/product'));
        $stubProductImage->expects($this->any())
            ->method('getId')
            ->will($this->returnValue(42));
        $stubProductImage->expects($this->any())
            ->method('setFile')
            ->will($this->returnValue(true));
        $stubProductImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(0));

        $stubUploadedFile = $this->getMockBuilder('\Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalName')
            ->will($this->returnValue('goodName'));
        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalExtension')
            ->will($this->returnValue('png'));
        $stubUploadedFile->expects($this->any())
            ->method('move')
            ->will($this->returnValue($stubUploadedFile));

        $fileManager = new FileManager($stubContainer);

        $newUploadedFiles = array();

        $actual = $fileManager->copyUploadedFile(24, FileManager::TYPE_PRODUCT, $stubProductImage, $stubUploadedFile, $newUploadedFiles, FileManager::FILE_TYPE_DOCUMENTS);

    }*/

    /**
     * @covers Thelia\Tools\FileManager::saveImage
     */
    public function testSaveImageProductImage()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubProductImage = $this->getMockBuilder('\Thelia\Model\ProductImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubProductImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new ImageCreateOrUpdateEvent(FileManager::TYPE_PRODUCT, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubProductImage);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveDocument
     */
    public function testSaveDocumentProductDocument()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubProductDocument = $this->getMockBuilder('\Thelia\Model\ProductDocument')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductDocument->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubProductDocument->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new DocumentCreateOrUpdateEvent(FileManager::TYPE_PRODUCT, 24);

        $expected = 10;
        $actual = $fileManager->saveDocument($event, $stubProductDocument);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveImage
     */
    public function testSaveImageCategoryImage()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubCategoryImage = $this->getMockBuilder('\Thelia\Model\CategoryImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubCategoryImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubCategoryImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new ImageCreateOrUpdateEvent(FileManager::TYPE_CATEGORY, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubCategoryImage);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveDocument
     */
    public function testSaveDocumentCategoryDocument()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubCategoryDocument = $this->getMockBuilder('\Thelia\Model\CategoryDocument')
            ->disableOriginalConstructor()
            ->getMock();
        $stubCategoryDocument->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubCategoryDocument->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new DocumentCreateOrUpdateEvent(FileManager::TYPE_CATEGORY, 24);

        $expected = 10;
        $actual = $fileManager->saveDocument($event, $stubCategoryDocument);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveImage
     */
    public function testSaveImageFolderImage()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFolderImage = $this->getMockBuilder('\Thelia\Model\FolderImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubFolderImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubFolderImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new ImageCreateOrUpdateEvent(FileManager::TYPE_FOLDER, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubFolderImage);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveDocument
     */
    public function testSaveDocumentFolderDocument()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFolderDocument = $this->getMockBuilder('\Thelia\Model\FolderDocument')
            ->disableOriginalConstructor()
            ->getMock();
        $stubFolderDocument->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubFolderDocument->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new DocumentCreateOrUpdateEvent(FileManager::TYPE_FOLDER, 24);

        $expected = 10;
        $actual = $fileManager->saveDocument($event, $stubFolderDocument);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveImage
     */
    public function testSaveImageContentImage()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubContentImage = $this->getMockBuilder('\Thelia\Model\ContentImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubContentImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubContentImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new ImageCreateOrUpdateEvent(FileManager::TYPE_CONTENT, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubContentImage);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveDocument
     */
    public function testSaveDocumentContentDocument()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubContentDocument = $this->getMockBuilder('\Thelia\Model\ContentDocument')
            ->disableOriginalConstructor()
            ->getMock();
        $stubContentDocument->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubContentDocument->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $fileManager = new FileManager();

        $event = new DocumentCreateOrUpdateEvent(FileManager::TYPE_CONTENT, 24);

        $expected = 10;
        $actual = $fileManager->saveDocument($event, $stubContentDocument);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveImage
     * @expectedException \Thelia\Exception\ImageException
     */
    public function testSaveImageExceptionImageException()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $fileManager = new FileManager();

        $stubProductImage = $this->getMockBuilder('\Thelia\Model\ProductImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubProductImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $event = new ImageCreateOrUpdateEvent('bad', 24);

        $fileManager->saveImage($event, $stubProductImage);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveDocument
     * @expectedException \Thelia\Model\Exception\InvalidArgumentException
     */
    public function testSaveDocumentExceptionDocumentException()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $fileManager = new FileManager();

        $stubProductDocument = $this->getMockBuilder('\Thelia\Model\ProductDocument')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductDocument->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubProductDocument->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $event = new DocumentCreateOrUpdateEvent('bad', 24);

        $fileManager->saveDocument($event, $stubProductDocument);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveImage
     * @expectedException \Thelia\Exception\ImageException
     */
    public function testSaveImageExceptionImageException2()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $fileManager = new FileManager();

        $stubProductImage = $this->getMockBuilder('\Thelia\Model\ProductImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(0));
        $stubProductImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $event = new ImageCreateOrUpdateEvent(FileManager::TYPE_PRODUCT, 24);

        $fileManager->saveImage($event, $stubProductImage);
    }

    /**
     * @covers Thelia\Tools\FileManager::saveDocument
     * @expectedException \Thelia\Model\Exception\InvalidArgumentException
     */
    public function testSaveDocumentExceptionDocumentException2()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $fileManager = new FileManager();

        $stubProductDocument = $this->getMockBuilder('\Thelia\Model\ProductDocument')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductDocument->expects($this->any())
            ->method('save')
            ->will($this->returnValue(0));
        $stubProductDocument->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $event = new DocumentCreateOrUpdateEvent(FileManager::TYPE_PRODUCT, 24);

        $fileManager->saveDocument($event, $stubProductDocument);
    }

    /**
     * @covers Thelia\Tools\FileManager::sanitizeFileName
     */
    public function testSanitizeFileName()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();
        $badFileName = 'a/ze\érà~çè§^"$*+-_°)(&é<>@#ty2/[\/:*?"<>|]/fi?.fUPPERile.exel../e*';

        $expected = 'azer-_ty2fi.fupperile.exel..e';
        $actual = $fileManager->sanitizeFileName($badFileName);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getImageModel
     */
    public function testGetImageModel()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();
        $actual = $fileManager->getImageModel(FileManager::TYPE_PRODUCT);
        $this->assertInstanceOf('\Thelia\Model\ProductImage', $actual);
        $actual = $fileManager->getImageModel(FileManager::TYPE_CATEGORY);
        $this->assertInstanceOf('\Thelia\Model\CategoryImage', $actual);
        $actual = $fileManager->getImageModel(FileManager::TYPE_CONTENT);
        $this->assertInstanceOf('\Thelia\Model\ContentImage', $actual);
        $actual = $fileManager->getImageModel(FileManager::TYPE_FOLDER);
        $this->assertInstanceOf('\Thelia\Model\FolderImage', $actual);
        $actual = $fileManager->getImageModel('bad');
        $this->assertNull($actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getDocumentModel
     */
    public function testGetDocumentModel()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();
        $actual = $fileManager->getDocumentModel(FileManager::TYPE_PRODUCT);
        $this->assertInstanceOf('\Thelia\Model\ProductDocument', $actual);
        $actual = $fileManager->getDocumentModel(FileManager::TYPE_CATEGORY);
        $this->assertInstanceOf('\Thelia\Model\CategoryDocument', $actual);
        $actual = $fileManager->getDocumentModel(FileManager::TYPE_CONTENT);
        $this->assertInstanceOf('\Thelia\Model\ContentDocument', $actual);
        $actual = $fileManager->getDocumentModel(FileManager::TYPE_FOLDER);
        $this->assertInstanceOf('\Thelia\Model\FolderDocument', $actual);
        $actual = $fileManager->getDocumentModel('bad');
        $this->assertNull($actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getImageModelQuery
     */
    public function testGetImageModelQuery()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();
        $actual = $fileManager->getImageModelQuery(FileManager::TYPE_PRODUCT);
        $this->assertInstanceOf('\Thelia\Model\ProductImageQuery', $actual);
        $actual = $fileManager->getImageModelQuery(FileManager::TYPE_CATEGORY);
        $this->assertInstanceOf('\Thelia\Model\CategoryImageQuery', $actual);
        $actual = $fileManager->getImageModelQuery(FileManager::TYPE_CONTENT);
        $this->assertInstanceOf('\Thelia\Model\ContentImageQuery', $actual);
        $actual = $fileManager->getImageModelQuery(FileManager::TYPE_FOLDER);
        $this->assertInstanceOf('\Thelia\Model\FolderImageQuery', $actual);
        $actual = $fileManager->getImageModelQuery('bad');
        $this->assertNull($actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getDocumentModelQuery
     */
    public function testGetDocumentModelQuery()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();
        $actual = $fileManager->getDocumentModelQuery(FileManager::TYPE_PRODUCT);
        $this->assertInstanceOf('\Thelia\Model\ProductDocumentQuery', $actual);
        $actual = $fileManager->getDocumentModelQuery(FileManager::TYPE_CATEGORY);
        $this->assertInstanceOf('\Thelia\Model\CategoryDocumentQuery', $actual);
        $actual = $fileManager->getDocumentModelQuery(FileManager::TYPE_CONTENT);
        $this->assertInstanceOf('\Thelia\Model\ContentDocumentQuery', $actual);
        $actual = $fileManager->getDocumentModelQuery(FileManager::TYPE_FOLDER);
        $this->assertInstanceOf('\Thelia\Model\FolderDocumentQuery', $actual);
        $actual = $fileManager->getDocumentModelQuery('bad');
        $this->assertNull($actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getParentFileModel
     */
    public function testGetParentFileModel()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();
        $actual = $fileManager->getParentFileModel(FileManager::TYPE_PRODUCT, ProductQuery::create()->findOne()->getId());
        $this->assertInstanceOf('\Thelia\Model\Product', $actual);
        $actual = $fileManager->getParentFileModel(FileManager::TYPE_CATEGORY, CategoryQuery::create()->findOne()->getId());
        $this->assertInstanceOf('\Thelia\Model\Category', $actual);
        $actual = $fileManager->getParentFileModel(FileManager::TYPE_CONTENT, ContentQuery::create()->findOne()->getId());
        $this->assertInstanceOf('\Thelia\Model\Content', $actual);
        $actual = $fileManager->getParentFileModel(FileManager::TYPE_FOLDER, FolderQuery::create()->findOne()->getId());
        $this->assertInstanceOf('\Thelia\Model\Folder', $actual, 1);
        $actual = $fileManager->getParentFileModel('bad', 1);
        $this->assertNull($actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getImageForm
     */
/*    public function testGetImageForm()
    {
        // Mock issue
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }*/
    /**
     * @covers Thelia\Tools\FileManager::getDocumentForm
     */
/*    public function testGetDocumentForm()
    {
        // Mock issue
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }*/

    /**
     * @covers Thelia\Tools\FileManager::getUploadDir
     */
    public function testGetUploadDir()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();

        $actual = $fileManager->getUploadDir(FileManager::TYPE_PRODUCT, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/images/product', $actual);
        $actual = $fileManager->getUploadDir(FileManager::TYPE_CATEGORY, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/images/category', $actual);
        $actual = $fileManager->getUploadDir(FileManager::TYPE_CONTENT, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/images/content', $actual);
        $actual = $fileManager->getUploadDir(FileManager::TYPE_FOLDER, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/images/folder', $actual);
        $actual = $fileManager->getUploadDir('bad', FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals(false, $actual);

        $actual = $fileManager->getUploadDir(FileManager::TYPE_PRODUCT, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/documents/product', $actual);
        $actual = $fileManager->getUploadDir(FileManager::TYPE_CATEGORY, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/documents/category', $actual);
        $actual = $fileManager->getUploadDir(FileManager::TYPE_CONTENT, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/documents/content', $actual);
        $actual = $fileManager->getUploadDir(FileManager::TYPE_FOLDER, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals(THELIA_LOCAL_DIR . 'media/documents/folder', $actual);
        $actual = $fileManager->getUploadDir('bad', FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals(false, $actual);

        $actual = $fileManager->getUploadDir(FileManager::TYPE_FOLDER, 'bad');
        $this->assertEquals(false, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getRedirectionUrl
     */
    public function testGetRedirectionUrl()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();

        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_PRODUCT, 1, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('/admin/products/update?product_id=1&current_tab=images', $actual);
        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_CATEGORY, 1, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('/admin/categories/update?category_id=1&current_tab=images', $actual);
        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_CONTENT, 1, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('/admin/content/update/1?current_tab=images', $actual);
        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_FOLDER, 1, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('/admin/folders/update/1?current_tab=images', $actual);
        $actual = $fileManager->getRedirectionUrl('bad', 1, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals(false, $actual);

        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_PRODUCT, 1, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('/admin/products/update?product_id=1&current_tab=documents', $actual);
        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_CATEGORY, 1, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('/admin/categories/update?category_id=1&current_tab=documents', $actual);
        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_CONTENT, 1, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('/admin/content/update/1?current_tab=documents', $actual);
        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_FOLDER, 1, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('/admin/folders/update/1?current_tab=documents', $actual);
        $actual = $fileManager->getRedirectionUrl('bad', 1, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals(false, $actual);

        $actual = $fileManager->getRedirectionUrl(FileManager::TYPE_FOLDER, 1, 'bad');
        $this->assertEquals(false, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::getFormId
     */
    public function testGetFormId()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();

        $actual = $fileManager->getFormId(FileManager::TYPE_PRODUCT, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('thelia.admin.product.image.modification', $actual);
        $actual = $fileManager->getFormId(FileManager::TYPE_CATEGORY, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('thelia.admin.category.image.modification', $actual);
        $actual = $fileManager->getFormId(FileManager::TYPE_CONTENT, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('thelia.admin.content.image.modification', $actual);
        $actual = $fileManager->getFormId(FileManager::TYPE_FOLDER, FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals('thelia.admin.folder.image.modification', $actual);
        $actual = $fileManager->getFormId('bad', FileManager::FILE_TYPE_IMAGES);
        $this->assertEquals(false, $actual);

        $actual = $fileManager->getFormId(FileManager::TYPE_PRODUCT, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('thelia.admin.product.document.modification', $actual);
        $actual = $fileManager->getFormId(FileManager::TYPE_CATEGORY, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('thelia.admin.category.document.modification', $actual);
        $actual = $fileManager->getFormId(FileManager::TYPE_CONTENT, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('thelia.admin.content.document.modification', $actual);
        $actual = $fileManager->getFormId(FileManager::TYPE_FOLDER, FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals('thelia.admin.folder.document.modification', $actual);
        $actual = $fileManager->getFormId('bad', FileManager::FILE_TYPE_DOCUMENTS);
        $this->assertEquals(false, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::renameFile
     */
    public function testRenameFile()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubUploadedFile = $this->getMockBuilder('\Symfony\Component\HttpFoundation\File\UploadedFile')
            ->setConstructorArgs([__DIR__ . '/fixtures/test.xml', 'test.xml'])
            ->getMock();
        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalExtension')
            ->will($this->returnValue('yml'));
        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalName')
            ->will($this->returnValue('or1-g_n?al*/&é"filen@me#'));

        $fileManager = new FileManager();

        $expected = 'or1-g_nalfilenme-1.yml';
        $actual = $fileManager->renameFile(1, $stubUploadedFile);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::renameFile
     */
    public function testRenameFileWithoutExtension()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $stubUploadedFile = $this->getMockBuilder('\Symfony\Component\HttpFoundation\File\UploadedFile')
            ->setConstructorArgs([__DIR__ . '/fixtures/test.xml', 'test.xml'])
            ->getMock();

        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalExtension')
            ->will($this->returnValue(''));
        $stubUploadedFile->expects($this->any())
            ->method('getClientOriginalName')
            ->will($this->returnValue('or1-g_n?al*/&é"filen@me#'));

        $fileManager = new FileManager();

        $expected = 'or1-g_nalfilenme-1';
        $actual = $fileManager->renameFile(1, $stubUploadedFile);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::isImage
     */
    public function testIsImage()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager();

        $actual = $fileManager->isImage('image/jpeg');
        $this->assertTrue($actual);
        $actual = $fileManager->isImage('image/png');
        $this->assertTrue($actual);
        $actual = $fileManager->isImage('image/gif');
        $this->assertTrue($actual);

        $actual = $fileManager->isImage('bad');
        $this->assertFalse($actual);
        $actual = $fileManager->isImage('image/jpg');
        $this->assertFalse($actual);
        $actual = $fileManager->isImage('application/x-msdownload');
        $this->assertFalse($actual);
        $actual = $fileManager->isImage('application/x-sh');
        $this->assertFalse($actual);

    }

    /**
     * @covers Thelia\Tools\FileManager::getAvailableTypes
     */
    public function testGetAvailableTypes()
    {
        $expected = array(
            FileManager::TYPE_CATEGORY,
            FileManager::TYPE_CONTENT,
            FileManager::TYPE_FOLDER,
            FileManager::TYPE_PRODUCT,
            FileManager::TYPE_MODULE,
        );
        $actual = FileManager::getAvailableTypes();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::adminLogAppend
     */
/*    public function testAdminLogAppend()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }*/

    /**
     * @covers Thelia\Tools\FileManager::deleteFile
     */
 /*   public function testDeleteFile()
    {
        // @todo see http://tech.vg.no/2011/03/09/mocking-the-file-system-using-phpunit-and-vfsstream/
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }*/
}
