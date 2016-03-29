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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\Collection;
use Thelia\Action\Content;
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;
use Thelia\Model\Content as ContentModel;

/**
 * Class ContentTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ContentTest extends TestCaseWithURLToolSetup
{
    use I18nTestTrait;

    protected static $folderForPositionTest = null;

    public function getUpdateEvent(&$content)
    {
        if (!$content instanceof ContentModel) {
            $content = $this->getRandomContent();
        }

        $event = new ContentUpdateEvent($content->getId());

        $event
            ->setVisible(1)
            ->setLocale($content->getLocale())
            ->setTitle($content->getTitle())
            ->setChapo($content->getChapo())
            ->setDescription($content->getDescription())
            ->setPostscriptum($content->getPostscriptum())
            ->setDefaultFolder($content->getDefaultFolderId())
        ;

        return $event;
    }

    /**
     * @param ContentUpdateEvent$event
     * @return ContentModel
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function processUpdateAction($event)
    {
        $contentAction = new Content();
        $contentAction->update($event, null, $this->getMockEventDispatcher());

        return $event->getContent();
    }

    public function testCreateContent()
    {
        $folder = $this->getRandomFolder();

        $event = new ContentCreateEvent();
        $event
            ->setVisible(1)
            ->setLocale('en_US')
            ->setTitle('test create content')
            ->setDefaultFolder($folder->getId())
        ;

        $contentAction = new Content();
        $contentAction->create($event, null, $this->getMockEventDispatcher());

        $createdContent = $event->getContent();

        $this->assertInstanceOf('Thelia\Model\Content', $createdContent);
        $this->assertEquals(1, $createdContent->getVisible());
        $this->assertEquals('test create content', $createdContent->getTitle());
        $this->assertEquals($folder->getId(), $createdContent->getDefaultFolderId());
    }

    public function testUpdateContent()
    {
        $content = $this->getRandomContent();
        $folder = $this->getRandomFolder();

        $event = new ContentUpdateEvent($content->getId());
        $event
            ->setVisible(1)
            ->setLocale('en_US')
            ->setTitle('test update content title')
            ->setChapo('test update content short description')
            ->setDescription('test update content description')
            ->setPostscriptum('test update content postscriptum')
            ->setDefaultFolder($folder->getId())
        ;

        $contentAction = new Content();
        $contentAction->update($event, null, $this->getMockEventDispatcher());

        $updatedContent = $event->getContent();

        $this->assertInstanceOf('Thelia\Model\Content', $updatedContent);
        $this->assertEquals(1, $updatedContent->getVisible());
        $this->assertEquals('test update content title', $updatedContent->getTitle());
        $this->assertEquals('test update content short description', $updatedContent->getChapo());
        $this->assertEquals('test update content description', $updatedContent->getDescription());
        $this->assertEquals('test update content postscriptum', $updatedContent->getPostscriptum());
        $this->assertEquals($folder->getId(), $updatedContent->getDefaultFolderId());
    }

    public function testDeleteContent()
    {
        $content = $this->getRandomContent();

        $event = new ContentDeleteEvent($content->getId());

        $contentAction = new Content();
        $contentAction->delete($event, null, $this->getMockEventDispatcher());

        $deletedContent = $event->getContent();

        $this->assertInstanceOf('Thelia\Model\Content', $deletedContent);
        $this->assertTrue($deletedContent->isDeleted());
    }

    public function testContentToggleVisibility()
    {
        $content = $this->getRandomContent();

        $visibility = $content->getVisible();

        $event = new ContentToggleVisibilityEvent($content);

        $contentAction = new Content();
        $contentAction->toggleVisibility($event, null, $this->getMockEventDispatcher());

        $updatedContent = $event->getContent();

        $this->assertInstanceOf('Thelia\Model\Content', $updatedContent);
        $this->assertEquals(!$visibility, $updatedContent->getVisible());
    }

    public function testUpdatePositionUp()
    {
        $contentFolderQuery = ContentFolderQuery::create()
            ->filterByFolder($this->getFolderForPositionTest())
            ->filterByPosition(2, Criteria::GREATER_THAN)
            ->findOne();

        if (null === $contentFolderQuery) {
            $this->fail('use fixtures before launching test, there is no content in database');
        }

        $newPosition = $contentFolderQuery->getPosition()-1;

        $event = new UpdatePositionEvent(
            $contentFolderQuery->getContentId(),
            UpdatePositionEvent::POSITION_UP,
            null,
            $contentFolderQuery->getFolderId()
        );

        $contentAction = new Content();
        $contentAction->updatePosition($event, null, $this->getMockEventDispatcher());

        $updatedContent = ContentFolderQuery::create()
            ->filterByFolderId($contentFolderQuery->getFolderId())
            ->filterByContentId($contentFolderQuery->getContentId())
            ->findOne();

        $this->assertEquals($newPosition, $updatedContent->getPosition(), sprintf("new position is %d, new position expected is %d for content %d", $newPosition, $updatedContent->getPosition(), $updatedContent->getContentId()));
    }

    public function testUpdatePositionDown()
    {
        $contentFolderQuery = ContentFolderQuery::create()
            ->filterByFolder($this->getFolderForPositionTest())
            ->filterByPosition(1)
            ->findOne();

        if (null === $contentFolderQuery) {
            $this->fail('use fixtures before launching test, there is no content in database');
        }

        $newPosition = $contentFolderQuery->getPosition()+1;

        $event = new UpdatePositionEvent(
            $contentFolderQuery->getContentId(),
            UpdatePositionEvent::POSITION_DOWN,
            null,
            $contentFolderQuery->getFolderId()
        );

        $contentAction = new Content();
        $contentAction->updatePosition($event, null, $this->getMockEventDispatcher());

        $updatedContent = ContentFolderQuery::create()
            ->filterByFolderId($contentFolderQuery->getFolderId())
            ->filterByContentId($contentFolderQuery->getContentId())
            ->findOne();
        ;

        $this->assertEquals($newPosition, $updatedContent->getPosition(), sprintf("new position is %d, new position expected is %d for content %d", $newPosition, $updatedContent->getPosition(), $updatedContent->getContentId()));
    }

    public function testUpdatePositionWithSpecificPosition()
    {
        $contentFolderQuery = ContentFolderQuery::create()
            ->filterByFolder($this->getFolderForPositionTest())
            ->filterByPosition(1, Criteria::GREATER_THAN)
            ->findOne();

        if (null === $contentFolderQuery) {
            $this->fail('use fixtures before launching test, there is no content in database');
        }

        $event = new UpdatePositionEvent(
            $contentFolderQuery->getContentId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            1,
            $contentFolderQuery->getFolderId()
        );

        $contentAction = new Content();
        $contentAction->updatePosition($event, null, $this->getMockEventDispatcher());

        $updatedContent = ContentFolderQuery::create()
            ->filterByFolderId($contentFolderQuery->getFolderId())
            ->filterByContentId($contentFolderQuery->getContentId())
            ->findOne();
        ;

        $this->assertEquals(1, $updatedContent->getPosition(), sprintf("new position is 1, new position expected is %d for content %d", $updatedContent->getPosition(), $updatedContent->getContentId()));
    }

    public function testAddFolderToContent()
    {
        $content = $this->getRandomContent();

        do {
            $folder = $this->getRandomFolder();

            $test = ContentFolderQuery::create()
                ->filterByContent($content)
                ->filterByFolder($folder);
        } while ($test->count() > 0);

        $event = new ContentAddFolderEvent($content, $folder->getId());

        $contentAction = new Content();
        $contentAction->addFolder($event, null, $this->getMockEventDispatcher());

        $testAddFolder = ContentFolderQuery::create()
            ->filterByContent($content)
            ->filterByFolder($folder)
            ->findOne()
        ;

        $this->assertNotNull($testAddFolder);
        $this->assertEquals($content->getId(), $testAddFolder->getContentId(), 'check if content id are equals');
        $this->assertEquals($folder->getId(), $testAddFolder->getFolderId(), 'check if folder id are equals');

        return $testAddFolder;
    }

    /**
     * @param ContentFolder $association
     * @depends testAddFolderToContent
     */
    public function testRemoveFolder(ContentFolder $association)
    {
        $event = new ContentRemoveFolderEvent($association->getContent(), $association->getFolder()->getId());

        $contentAction = new Content();
        $contentAction->removeFolder($event, null, $this->getMockEventDispatcher());

        $testAssociation = ContentFolderQuery::create()
            ->filterByContent($association->getContent())
            ->filterByFolder($association->getFolder())
            ->findOne();

        $this->assertNull($testAssociation);
    }

    /**
     * @return \Thelia\Model\Content
     */
    protected function getRandomContent()
    {
        $content = ContentQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        if (null === $content) {
            $this->fail('use fixtures before launching test, there is no content in database');
        }

        return $content;
    }

    /**
     * generates a folder and its contents to be used in Position tests
     *
     * @return Folder the parent folder
     */
    protected function getFolderForPositionTest()
    {
        if (null === self::$folderForPositionTest) {
            $folder = new Folder();

            $folder->setParent(0);
            $folder->setVisible(1);
            $folder->setPosition(1);

            $this->setI18n($folder);

            $folder->save();

            for ($i = 0; $i < 4; $i++) {
                $content = new ContentModel();

                $content->setVisible(1);

                $content->addFolder($folder);

                $this->setI18n($content);

                $content->save();
            }

            self::$folderForPositionTest = $folder;
        }

        return self::$folderForPositionTest;
    }

    /**
     * @return \Thelia\Model\Folder
     */
    protected function getRandomFolder()
    {
        $folder = FolderQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        if (null === $folder) {
            $this->fail('use fixtures before launching test, there is no folder in database');
        }

        return $folder;
    }
}
