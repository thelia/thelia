<?php
/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 8:47 PM
 * 
 * @author Guillaume MOREL <gmorel@openstudio.fr>
 */

namespace Thelia\Tests\Type;


use Thelia\Core\Event\ImagesCreateOrUpdateEvent;
use Thelia\Exception\ImageException;
use Thelia\Model\Admin;
use Thelia\Tools\FileManager;

class FileManagerTest extends \PHPUnit_Framework_TestCase {


    /**
     * @covers Thelia\Tools\FileManager::copyUploadedFile
     */
    public function testCopyUploadedFile()
    {
        $this->markTestIncomplete(
            'Mock issue'
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

        $actual = $fileManager->copyUploadedFile(24, ImagesCreateOrUpdateEvent::TYPE_PRODUCT, $stubProductImage, $stubUploadedFile, $newUploadedFiles);

        $this->assertCount(1, $actual);
    }


    /**
     * @covers Thelia\Tools\FileManager::copyUploadedFile
     * @expectedException \Thelia\Exception\ImageException
     */
    public function testCopyUploadedFileExceptionImageException()
    {
        $this->markTestIncomplete(
            'Mock issue'
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

        $actual = $fileManager->copyUploadedFile(24, ImagesCreateOrUpdateEvent::TYPE_PRODUCT, $stubProductImage, $stubUploadedFile, $newUploadedFiles);

    }

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

        $fileManager = new FileManager($stubContainer);

        $event = new ImagesCreateOrUpdateEvent(ImagesCreateOrUpdateEvent::TYPE_PRODUCT, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubProductImage);

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

        $fileManager = new FileManager($stubContainer);

        $event = new ImagesCreateOrUpdateEvent(ImagesCreateOrUpdateEvent::TYPE_CATEGORY, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubCategoryImage);

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

        $fileManager = new FileManager($stubContainer);

        $event = new ImagesCreateOrUpdateEvent(ImagesCreateOrUpdateEvent::TYPE_FOLDER, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubFolderImage);

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

        $fileManager = new FileManager($stubContainer);

        $event = new ImagesCreateOrUpdateEvent(ImagesCreateOrUpdateEvent::TYPE_CONTENT, 24);

        $expected = 10;
        $actual = $fileManager->saveImage($event, $stubContentImage);

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
        $fileManager = new FileManager($stubContainer);

        $stubProductImage = $this->getMockBuilder('\Thelia\Model\ProductImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(10));
        $stubProductImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $event = new ImagesCreateOrUpdateEvent('bad', 24);

        $fileManager->saveImage($event, $stubProductImage);
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
        $fileManager = new FileManager($stubContainer);

        $stubProductImage = $this->getMockBuilder('\Thelia\Model\ProductImage')
            ->disableOriginalConstructor()
            ->getMock();
        $stubProductImage->expects($this->any())
            ->method('save')
            ->will($this->returnValue(0));
        $stubProductImage->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue('file'));

        $event = new ImagesCreateOrUpdateEvent(ImagesCreateOrUpdateEvent::TYPE_PRODUCT, 24);

        $fileManager->saveImage($event, $stubProductImage);
    }

    /**
     * @covers Thelia\Tools\FileManager::sanitizeFileName
     */
    public function testSanitizeFileName()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $fileManager = new FileManager($stubContainer);
        $badFileName = 'azeéràçè§^"$*+-_°)(&é<>@#ty';

        $expected = 'azeyryZyy-_yty';
        $actual = $fileManager->sanitizeFileName($badFileName);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Tools\FileManager::adminLogAppend
     */
    public function testAdminLogAppend()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
