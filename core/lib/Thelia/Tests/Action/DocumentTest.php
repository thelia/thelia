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

namespace Thelia\Tests\Action;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;

use Thelia\Action\Document;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Model\ConfigQuery;

/**
 * Class DocumentTest
 *
 * @package Thelia\Tests\Action\DocumentTest
 */
class DocumentTest extends \Thelia\Tests\TestCaseWithURLToolSetup
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
        $config = ConfigQuery::create()->filterByName('document_cache_dir_from_web_root')->findOne();

        if ($config != null) {
            $this->cache_dir_from_web_root = $config->getValue();

            $config->setValue(__DIR__."/assets/documents/cache");

            $config->setValue($this->cache_dir_from_web_root)->save();
        }
    }

    public static function setUpBeforeClass()
    {
        $dir = THELIA_WEB_DIR."/cache/tests";
        if ($dh = @opendir($dir)) {
            while ($file = readdir($dh)) {
                if ($file == '.' || $file == '..') continue;

                unlink(sprintf("%s/%s", $dir, $file));
            }

            closedir($dh);
        }
    }

    public function tearDown()
    {
        // restore cache configuration.
        $config = ConfigQuery::create()->filterByName('document_cache_dir_from_web_root')->findOne();

        if ($config != null) {
            $config->setValue($this->cache_dir_from_web_root)->save();
        }
    }

    /**
     *
     * Documentevent is empty, mandatory parameters not specified.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testProcessEmptyDocumentEvent()
    {
        $event = new DocumentEvent($this->request);

        $document = new Document($this->getContainer());

        $document->processDocument($event);
     }

     /**
      *
      * Try to process a non-existent file
      *
      * @expectedException \InvalidArgumentException
      */
     public function testProcessNonExistentDocument()
     {
         $event = new DocumentEvent($this->request);

         $document = new Document($this->getContainer());

         $event->setCacheFilepath("blablabla.txt");
         $event->setCacheSubdirectory("tests");

         $document->processDocument($event);
     }

     /**
      *
      * Try to process a file outside of the cache
      *
      * @expectedException \InvalidArgumentException
      */
     public function testProcessDocumentOutsideValidPath()
     {
         $event = new DocumentEvent($this->request);

         $document = new Document($this->getContainer());

         $event->setCacheFilepath("blablabla.pdf");
         $event->setCacheSubdirectory("../../../");

         $document->processDocument($event);
     }

     /**
      * No operation done on source file -> copie !
      */
     public function testProcessDocumentCopy()
     {
         $event = new DocumentEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/documents/sources/test-document-1.txt");
         $event->setCacheSubdirectory("tests");

         $document = new Document($this->getContainer());

         // mock cache configuration.
         $config = ConfigQuery::create()->filterByName('original_document_delivery_mode')->findOne();

         if ($config != null) {
             $oldval = $config->getValue();
             $config->setValue('copy')->save();
         }

         $document->processDocument($event);

         if ($config != null) $config->setValue($oldval)->save();

         $imgdir = ConfigQuery::read('document_cache_dir_from_web_root');

         $this->assertFileExists(THELIA_WEB_DIR."/$imgdir/tests/test-document-1.txt");
     }

     /**
      * No operation done on source file -> link !
      */
     public function testProcessDocumentSymlink()
     {
         $event = new DocumentEvent($this->request);

         $event->setSourceFilepath(__DIR__."/assets/documents/sources/test-document-2.txt");
         $event->setCacheSubdirectory("tests");

         $document = new Document($this->getContainer());

         // mock cache configuration.
         $config = ConfigQuery::create()->filterByName('original_document_delivery_mode')->findOne();

         if ($config != null) {
             $oldval = $config->getValue();
             $config->setValue('symlink')->save();
         }

         $document->processDocument($event);

         if ($config != null) $config->setValue($oldval)->save();

         $imgdir = ConfigQuery::read('document_cache_dir_from_web_root');

         $this->assertFileExists(THELIA_WEB_DIR."/$imgdir/tests/test-document-2.txt");
     }

     public function testClearTestsCache()
     {
         $event = new DocumentEvent($this->request);

         $event->setCacheSubdirectory('tests');

         $document = new Document($this->getContainer());

         $document->clearCache($event);
     }

     public function testClearWholeCache()
     {
         $event = new DocumentEvent($this->request);

         $document = new Document($this->getContainer());

         $document->clearCache($event);
     }

     /**
      * Try to clear directory ouside of the cache
      *
      * @expectedException \InvalidArgumentException
      */
     public function testClearUnallowedPathCache()
     {
         $event = new DocumentEvent($this->request);

         $event->setCacheSubdirectory('../../../..');

         $document = new Document($this->getContainer());

         $document->clearCache($event);
     }
}
