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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Router;
use Thelia\Action\Category;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Model\Category as CategoryModel;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Model\CategoryQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;
use Thelia\Tools\URL;


/**
 * Class CategoryTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CategoryTest extends TestCaseWithURLToolSetup
{

    protected function getRandomCategory()
    {
        $category = CategoryQuery::create()
        ->addAscendingOrderByColumn('RAND()')
        ->findOne();

        if (null === $category) {
            $this->fail('use fixtures before launching test, there is no category in database');
        }

        return $category;
    }

    public function getUpdateEvent(&$category)
    {
        if (!$category instanceof \Thelia\Model\Category) {
            $category = $this->getRandomCategory();
        }

        $event = new CategoryUpdateEvent($category->getId());

        $event
            ->setLocale('en_US')
            ->setTitle('bar')
            ->setDescription('bar description')
            ->setChapo('bar chapo')
            ->setPostscriptum('bar postscriptum')
            ->setVisible(0)
            ->setParent(0)
            ->setDispatcher($this->getDispatcher())
        ;
    }

    public function getUpdateSeoEvent(&$category)
    {
        if (!$category instanceof \Thelia\Model\Category) {
            $category = $this->getRandomCategory();
        }

        $event = new UpdateSeoEvent($category->getId());
        $event->setDispatcher($this->getDispatcher());
        $event
            ->setLocale($category->getLocale())
            ->setMetaTitle($category->getMetaTitle())
            ->setMetaDescription($category->getMetaDescription())
            ->setMetaKeywords($category->getMetaKeywords());

        return $event;
    }

    public function processUpdateAction($event)
    {
        $action = new Category();
        $action->update($event);

        return $event->getCategory();
    }

    public function processUpdateSeoAction($event)
    {
        $action = new Category();

        return $action->updateSeo($event);

    }

    public function testCreate()
    {
        $event = new CategoryCreateEvent();

        $event
            ->setLocale('en_US')
            ->setParent(0)
            ->setTitle('foo')
            ->setVisible(1)
            ->setDispatcher($this->getDispatcher());

        $action = new Category();
        $action->create($event);

        $createdCategory = $event->getCategory();

        $this->assertInstanceOf('Thelia\Model\Category', $createdCategory);

        $this->assertFalse($createdCategory->isNew());

        $this->assertEquals('en_US', $createdCategory->getLocale());
        $this->assertEquals('foo', $createdCategory->getTitle());
        $this->assertEquals(1, $createdCategory->getVisible());
        $this->assertEquals(0, $createdCategory->getParent());
        $this->assertNull($createdCategory->getDescription());
        $this->assertNull($createdCategory->getChapo());
        $this->assertNull($createdCategory->getPostscriptum());

        return $createdCategory;
    }

    /**
     * @param CategoryModel $category
     * @depends testCreate
     */
    public function testUpdate(CategoryModel $category)
    {
        $event = new CategoryUpdateEvent($category->getId());

        $event
            ->setLocale('en_US')
            ->setTitle('bar')
            ->setDescription('bar description')
            ->setChapo('bar chapo')
            ->setPostscriptum('bar postscriptum')
            ->setVisible(0)
            ->setParent(0)
            ->setDispatcher($this->getDispatcher())
        ;

        $action = new Category();
        $action->update($event);

        $updatedCategory = $event->getCategory();

        $this->assertInstanceOf('Thelia\Model\Category', $updatedCategory);

        $this->assertEquals('en_US', $updatedCategory->getLocale());
        $this->assertEquals('bar', $updatedCategory->getTitle());
        $this->assertEquals('bar description', $updatedCategory->getDescription());
        $this->assertEquals('bar chapo', $updatedCategory->getChapo());
        $this->assertEquals('bar postscriptum', $updatedCategory->getPostscriptum());
        $this->assertEquals(0, $updatedCategory->getVisible());
        $this->assertEquals(0, $updatedCategory->getParent());

        return $updatedCategory;
    }

    /**
     * @depends testUpdate
     */
    public function testDelete(CategoryModel $category)
    {
        $event = new CategoryDeleteEvent($category->getId());
        $event->setDispatcher($this->getDispatcher());

        $action = new Category();
        $action->delete($event);

        $deletedCategory = $event->getCategory();

        $this->assertInstanceOf('Thelia\Model\Category', $deletedCategory);
        $this->assertTrue($deletedCategory->isDeleted());
    }
}