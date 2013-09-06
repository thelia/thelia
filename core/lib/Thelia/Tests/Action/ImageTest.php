<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Action\ImageTest;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;

use Thelia\Action\Image;
use Thelia\Core\Event\ImageEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * Class ImageTest
 *
 * @package Thelia\Tests\Action\ImageTest
 */
class ImageTest extends \Thelia\Tests\TestCaseWithURLToolSetup
{
    protected $request;

    protected $session;

    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $container->set("event_dispatcher", $dispatcher);

        $request = new Request();
        $request->setSession($this->session);

        $container->set("request", $request);

        return $container;
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

            $config->setValue(__DIR__."/assets/images/cache");

            $config->setValue($this->cache_dir_from_web_root)->save();
        }
    }

    public static function setUpBeforeClass() {
        $dir = THELIA_WEB_DIR."/cache/tests";
        if ($dh = @opendir($dir)) {
            while ($file = readdir($dh)) {
                if ($file == '.' || $file == '..') continue;

                unlink(sprintf("%s/%s", $dir, $file));
            }

            closedir($dh);
        }
    }

    public function tearDown() {
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

        $image = new Image($this->getContainer());

        $image->processImage($event);
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

         $image = new Image($this->getContainer());

         $event->setCacheFilepath("blablabla.png");
         $event->setCacheSubdirectory("tests");

         $image->processImage($event);
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

         $image = new Image($this->getContainer());

         $event->setCacheFilepath("blablabla.png");
         $event->setCacheSubdirectory("../../../");

         $image->processImage($event);
     }

     /**
      * No operation done on source file -> copie !
      */
     public function testProcessImageWithoutAnyTransformationsCopy()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-1.png");
         $event->setCacheSubdirectory("tests");

         $image = new Image($this->getContainer());

         // mock cache configuration.
         $config = ConfigQuery::create()->filterByName('original_image_delivery_mode')->findOne();

         if ($config != null) {
             $oldval = $config->getValue();
             $config->setValue('copy')->save();
         }

         $image->processImage($event);

         if ($config != null) $config->setValue($oldval)->save();

         $imgdir = ConfigQuery::read('image_cache_dir_from_web_root');

         $this->assertFileExists(THELIA_WEB_DIR."/$imgdir/tests/test-image-1.png");
     }

     /**
      * No operation done on source file -> copie !
      */
     public function testProcessImageWithoutAnyTransformationsSymlink()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-9.png");
         $event->setCacheSubdirectory("tests");

         $image = new Image($this->getContainer());

         // mock cache configuration.
         $config = ConfigQuery::create()->filterByName('original_image_delivery_mode')->findOne();

         if ($config != null) {
             $oldval = $config->getValue();
             $config->setValue('symlink')->save();
         }

         $image->processImage($event);

         if ($config != null) $config->setValue($oldval)->save();

         $imgdir = ConfigQuery::read('image_cache_dir_from_web_root');

         $this->assertFileExists(THELIA_WEB_DIR."/$imgdir/tests/test-image-9.png");
     }

     /**
      * Resize image with bands width > height
      */
     public function testProcessImageResizeHorizWithBands()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-2.png");
         $event->setCacheSubdirectory("tests");

         $event->setBackgroundColor('#ff0000');
         $event->setWidth(100);
         $event->setHeight(100);
         $event->setResizeMode(Image::EXACT_RATIO_WITH_BORDERS);

         $image = new Image($this->getContainer());

         $image->processImage($event);
     }

     /**
      * Resize image with bands height > width
      */
     public function testProcessImageResizeVertWithBands()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-3.png");
         $event->setCacheSubdirectory("tests");

         $event->setBackgroundColor('#ff0000');
         $event->setWidth(100);
         $event->setHeight(100);
         $event->setResizeMode(Image::EXACT_RATIO_WITH_BORDERS);

         $image = new Image($this->getContainer());

         $image->processImage($event);
     }


     /**
      * Apply all transformations
      */
     public function testProcessImageWithTransformations()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-4.png");
         $event->setCacheSubdirectory("tests");

         $event->setEffects(array("grayscale", "vertical_flip", "horizontal_flip", 'colorize:#00ff00', 'gamma: 0.2'));

         $image = new Image($this->getContainer());

         $image->processImage($event);
     }

     /**
      * Resize image with crop width > height
      */
     public function testProcessImageResizeHorizWithCrop()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-5.png");
         $event->setCacheSubdirectory("tests");

         $event->setBackgroundColor('#ff0000');
         $event->setWidth(180);
         $event->setHeight(100);
         $event->setResizeMode(Image::EXACT_RATIO_WITH_CROP);

         $image = new Image($this->getContainer());

         $image->processImage($event);
     }

     /**
      * Resize image with crop height > width
      */
     public function testProcessImageResizeVertWithCrop()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-6.png");
         $event->setCacheSubdirectory("tests");

         $event->setBackgroundColor('#ff0000');
         $event->setWidth(100);
         $event->setHeight(150);
         $event->setResizeMode(Image::EXACT_RATIO_WITH_CROP);

         $image = new Image($this->getContainer());

         $image->processImage($event);
     }

     /**
      * Resize image keeping image ration
      */
     public function testProcessImageResizeHorizKeepRatio()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-7.png");
         $event->setCacheSubdirectory("tests");

         $event->setWidth(100);
         $event->setHeight(100);

         $image = new Image($this->getContainer());

         $image->processImage($event);
     }

     /**
      * Resize image with crop height > width
      */
     public function testProcessImageResizeVertKeepRatio()
     {
         $event = new ImageEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/images/sources/test-image-8.png");
         $event->setCacheSubdirectory("tests");

         $event->setWidth(100);
         $event->setHeight(100);

         $image = new Image($this->getContainer());

         $image->processImage($event);
     }

     public function testClearTestsCache() {
         $event = new ImageEvent($this->request);

         $event->setCacheSubdirectory('tests');

         $image = new Image($this->getContainer());

         $image->clearCache($event);
     }

     public function testClearWholeCache() {
         $event = new ImageEvent($this->request);

         $image = new Image($this->getContainer());

         $image->clearCache($event);
     }

     /**
      * Try to clear directory ouside of the cache
      *
      * @expectedException \InvalidArgumentException
      */
     public function testClearUnallowedPathCache() {
         $event = new ImageEvent($this->request);

         $event->setCacheSubdirectory('../../../..');

         $image = new Image($this->getContainer());

         $image->clearCache($event);
     }
}