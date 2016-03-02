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

namespace Thelia\Tests\Action;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Action\Image;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Files\FileManager;
use Thelia\Model\ConfigQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;

/**
 * Class ImageTest
 *
 * @package Thelia\Tests\Action\ImageTest
 */
class ImageTest extends TestCaseWithURLToolSetup
{
    protected $cache_dir_from_web_root;

    protected $request;

    protected $session;

    public function getContainer()
    {
        $container = new ContainerBuilder();

        $container->set("event_dispatcher", $this->getDispatcher());

        $request = new Request();
        $request->setSession($this->session);

        $container->set("request", $request);

        return $container;
    }

    public function getFileManager()
    {
        $fileManager = new FileManager([
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

        return $fileManager;
    }

    public function setUp()
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->request = new Request();

        $this->request->setSession($this->session);

        // mock cache configuration.
        $config = ConfigQuery::create()->filterByName('image_cache_dir_from_web_root')->findOne();

        if ($config != null) {
            $this->cache_dir_from_web_root = $config->getValue();

            $config->setValue(__DIR__ . "/assets/images/cache");

            $config->setValue($this->cache_dir_from_web_root)->save();
        }
    }

    public static function setUpBeforeClass()
    {
        $dir = THELIA_WEB_DIR . "/cache/tests";
        if ($dh = @opendir($dir)) {
            while ($file = readdir($dh)) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                unlink(sprintf("%s/%s", $dir, $file));
            }

            closedir($dh);
        }
    }

    public function tearDown()
    {
        // restore cache configuration.
        $config = ConfigQuery::create()->filterByName('image_cache_dir_from_web_root')->findOne();

        if ($config != null) {
            $config->setValue($this->cache_dir_from_web_root)->save();
        }
    }

    /**
     *
     * Imageevent is empty, mandatory parameters not specified.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testProcessEmptyImageEvent()
    {
        $event = new ImageEvent($this->request);

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     *
     * Try to process a non-existent file
     *
     * @expectedException \InvalidArgumentException
     */
    public function testProcessNonExistentImage()
    {
        $event = new ImageEvent($this->request);

        $image = new Image($this->getFileManager());

        $event->setCacheFilepath("blablabla.png");
        $event->setCacheSubdirectory("tests");

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     *
     * Try to process a file outside of the cache
     *
     * @expectedException \InvalidArgumentException
     */
    public function testProcessImageOutsideValidPath()
    {
        $event = new ImageEvent($this->request);

        $image = new Image($this->getFileManager());

        $event->setCacheFilepath("blablabla.png");
        $event->setCacheSubdirectory("../../../");

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     * No operation done on source file -> copie !
     */
    public function testProcessImageWithoutAnyTransformationsCopy()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-1.png");
        $event->setCacheSubdirectory("tests");

        $image = new Image($this->getFileManager());

        // mock cache configuration.
        $config = ConfigQuery::create()->filterByName('original_image_delivery_mode')->findOne();

        if ($config != null) {
            $oldval = $config->getValue();
            $config->setValue('copy')->save();
        }

        $image->processImage($event, null, $this->getMockEventDispatcher());

        if ($config != null) {
            $config->setValue($oldval)->save();
        }

        $imgdir = ConfigQuery::read('image_cache_dir_from_web_root');

        $this->assertFileExists(THELIA_WEB_DIR . "/$imgdir/tests/test-image-1.png");
    }

    /**
     * No operation done on source file -> copie !
     */
    public function testProcessImageWithoutAnyTransformationsSymlink()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-9.png");
        $event->setCacheSubdirectory("tests");

        $image = new Image($this->getFileManager());

        // mock cache configuration.
        $config = ConfigQuery::create()->filterByName('original_image_delivery_mode')->findOne();

        if ($config != null) {
            $oldval = $config->getValue();
            $config->setValue('symlink')->save();
        }

        $image->processImage($event, null, $this->getMockEventDispatcher());

        if ($config != null) {
            $config->setValue($oldval)->save();
        }

        $imgdir = ConfigQuery::read('image_cache_dir_from_web_root');

        $this->assertFileExists(THELIA_WEB_DIR . "/$imgdir/tests/test-image-9.png");
    }

    /**
     * Resize image with bands width > height
     */
    public function testProcessImageResizeHorizWithBands()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-2.png");
        $event->setCacheSubdirectory("tests");

        $event->setBackgroundColor('#ff0000');
        $event->setWidth(100);
        $event->setHeight(100);
        $event->setResizeMode(Image::EXACT_RATIO_WITH_BORDERS);

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     * Resize image with bands height > width
     */
    public function testProcessImageResizeVertWithBands()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-3.png");
        $event->setCacheSubdirectory("tests");

        $event->setBackgroundColor('#ff0000');
        $event->setWidth(100);
        $event->setHeight(100);
        $event->setResizeMode(Image::EXACT_RATIO_WITH_BORDERS);

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     * Apply all transformations
     */
    public function testProcessImageWithTransformations()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-4.png");
        $event->setCacheSubdirectory("tests");

        $event->setEffects(array("grayscale", "vertical_flip", "horizontal_flip", 'colorize:#00ff00', 'gamma: 0.2'));

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     * Resize image with crop width > height
     */
    public function testProcessImageResizeHorizWithCrop()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-5.png");
        $event->setCacheSubdirectory("tests");

        $event->setBackgroundColor('#ff0000');
        $event->setWidth(180);
        $event->setHeight(100);
        $event->setResizeMode(Image::EXACT_RATIO_WITH_CROP);

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     * Resize image with crop height > width
     */
    public function testProcessImageResizeVertWithCrop()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-6.png");
        $event->setCacheSubdirectory("tests");

        $event->setBackgroundColor('#ff0000');
        $event->setWidth(100);
        $event->setHeight(150);
        $event->setResizeMode(Image::EXACT_RATIO_WITH_CROP);

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     * Resize image keeping image ration
     */
    public function testProcessImageResizeHorizKeepRatio()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-7.png");
        $event->setCacheSubdirectory("tests");

        $event->setWidth(100);
        $event->setHeight(100);

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    /**
     * Resize image with crop height > width
     */
    public function testProcessImageResizeVertKeepRatio()
    {
        $event = new ImageEvent($this->request);

        $event->setSourceFilepath(__DIR__ . "/assets/images/sources/test-image-8.png");
        $event->setCacheSubdirectory("tests");

        $event->setWidth(100);
        $event->setHeight(100);

        $image = new Image($this->getFileManager());

        $image->processImage($event, null, $this->getMockEventDispatcher());
    }

    public function testClearTestsCache()
    {
        $event = new ImageEvent($this->request);

        $event->setCacheSubdirectory('tests');

        $image = new Image($this->getFileManager());

        $image->clearCache($event);
    }

    public function testClearWholeCache()
    {
        $event = new ImageEvent($this->request);

        $image = new Image($this->getFileManager());

        $image->clearCache($event);
    }

    /**
     * Try to clear directory ouside of the cache
     *
     * @expectedException \InvalidArgumentException
     */
    public function testClearUnallowedPathCache()
    {
        $event = new ImageEvent($this->request);

        $event->setCacheSubdirectory('../../../..');

        $image = new Image($this->getFileManager());

        $image->clearCache($event);
    }
}
