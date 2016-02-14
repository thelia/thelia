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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Category;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryToggleVisibilityEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\Template\TemplateDeleteEvent;
use Thelia\Model\Category as CategoryModel;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Template;
use Thelia\Tests\TestCaseWithURLToolSetup;

/**
 * Class CategoryTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CategoryTest extends TestCaseWithURLToolSetup
{
    /**
     * @return EventDispatcherInterface
     */
    protected function getMockEventDispatcher()
    {
        return $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    /**
     * @return \Thelia\Model\Category
     */
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

    public function testCreate()
    {
        $event = new CategoryCreateEvent();

        $event
            ->setLocale('en_US')
            ->setParent(0)
            ->setTitle('foo')
            ->setVisible(1);

        $action = new Category();
        $action->create($event, null, $this->getMockEventDispatcher());

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
     * @return CategoryModel
     */
    public function testUpdate(CategoryModel $category)
    {
        $template = new Template();
        $template->setName('A sample template')->save();

        $event = new CategoryUpdateEvent($category->getId());

        $event
            ->setLocale('en_US')
            ->setTitle('bar')
            ->setDescription('bar description')
            ->setChapo('bar chapo')
            ->setPostscriptum('bar postscriptum')
            ->setVisible(0)
            ->setParent(0)
            ->setDefaultTemplateId($template->getId())
        ;

        $action = new Category();
        $action->update($event, null, $this->getMockEventDispatcher());

        $updatedCategory = $event->getCategory();

        $this->assertInstanceOf('Thelia\Model\Category', $updatedCategory);

        $this->assertEquals('en_US', $updatedCategory->getLocale());
        $this->assertEquals('bar', $updatedCategory->getTitle());
        $this->assertEquals('bar description', $updatedCategory->getDescription());
        $this->assertEquals('bar chapo', $updatedCategory->getChapo());
        $this->assertEquals('bar postscriptum', $updatedCategory->getPostscriptum());
        $this->assertEquals(0, $updatedCategory->getVisible());
        $this->assertEquals($template->getId(), $updatedCategory->getDefaultTemplateId());
        $this->assertEquals(0, $updatedCategory->getParent());

        return [ $updatedCategory, $template ];
    }

    /**
     * @param array $argArray
     * @depends testUpdate
     * @return CategoryModel
     */
    public function testRemoveTemplate($argArray)
    {
        /** @var CategoryModel $category */
        $category = $argArray[0];

        /** @var Template $template */
        $template = $argArray[1];

        $event = new TemplateDeleteEvent($template->getId());

        $action = new \Thelia\Action\Template();
        $action->delete($event, null, $this->getMockEventDispatcher());

        $this->assertInstanceOf('Thelia\Model\Template', $event->getTemplate());

        $theCat = CategoryQuery::create()->findPk($category->getId());

        $this->assertNull($theCat->getDefaultTemplateId());

        return $category;
    }

    /**
     * @param CategoryModel $category
     * @depends testRemoveTemplate
     */
    public function testDelete(CategoryModel $category)
    {
        $event = new CategoryDeleteEvent($category->getId());

        $action = new Category();
        $action->delete($event, null, $this->getMockEventDispatcher());

        $deletedCategory = $event->getCategory();

        $this->assertInstanceOf('Thelia\Model\Category', $deletedCategory);
        $this->assertTrue($deletedCategory->isDeleted());
    }

    public function testToggleVisibility()
    {
        $category = $this->getRandomCategory();
        $expectedVisibility = !$category->getVisible();

        $event = new CategoryToggleVisibilityEvent($category);

        $action = new Category();
        $action->toggleVisibility($event, null, $this->getMockEventDispatcher());

        $updatedCategory = $event->getCategory();

        $this->assertInstanceOf('Thelia\Model\Category', $updatedCategory);
        $this->assertEquals($expectedVisibility, $updatedCategory->getVisible());
    }
}
