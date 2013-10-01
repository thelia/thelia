<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
use Thelia\Action\Content;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;


/**
 * Class ContentTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ContentTest extends BaseAction
{
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

        $contentAction = new Content($this->getContainer());
        $contentAction->create($event);

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

        $contentAction = new Content($this->getContainer());
        $contentAction->update($event);

        $updatedContent = $event->getContent();

        $this->assertInstanceOf('Thelia\Model\Content', $updatedContent);
        $this->assertEquals(1, $updatedContent->getVisible());
        $this->assertEquals('test update content title', $updatedContent->getTitle());
        $this->assertEquals('test update content short description', $updatedContent->getChapo());
        $this->assertEquals('test update content description', $updatedContent->getDescription());
        $this->assertEquals('test update content postscriptum', $updatedContent->getPostscriptum());
        $this->assertEquals($folder->getId(), $updatedContent->getDefaultFolderId());
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
     * @return \Thelia\Model\Folder
     */
    protected function getRandomFolder()
    {
        $folder = FolderQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        if(null === $folder) {
            $this->fail('use fixtures before launching test, there is no folder in database');
        }

        return $folder;
    }
}