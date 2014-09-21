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

use Thelia\Action\Profile;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Profile as ProfileModel;
use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Model\ProfileQuery;

/**
 * Class ProfileTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ProfileTest extends \PHPUnit_Framework_TestCase
{
    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    public static function setUpBeforeClass()
    {
        ProfileQuery::create()
            ->filterByCode('Test')
            ->delete();
    }

    public function testCreate()
    {
        $event = new ProfileEvent();

        $event
            ->setCode("Test")
            ->setLocale('en_US')
            ->setTitle('test profile')
            ->setChapo('test chapo')
            ->setDescription('test description')
            ->setPostscriptum('test postscriptum')
            ->setDispatcher($this->dispatcher)
        ;

        $action = new Profile();
        $action->create($event);

        $createdProfile = $event->getProfile();

        $this->assertInstanceOf('Thelia\Model\Profile', $createdProfile);
        $this->assertFalse($createdProfile->isNew());

        $this->assertEquals('Test', $createdProfile->getCode());
        $this->assertEquals('en_US', $createdProfile->getLocale());
        $this->assertEquals('test profile', $createdProfile->getTitle());
        $this->assertEquals('test chapo', $createdProfile->getChapo());
        $this->assertEquals('test description', $createdProfile->getDescription());
        $this->assertEquals('test postscriptum', $createdProfile->getPostscriptum());

        return $createdProfile;
    }

    /**
     * @depends testCreate
     */
    public function testUpdate(ProfileModel $profile)
    {
        $event = new ProfileEvent();

        $event
            ->setId($profile->getId())
            ->setLocale('en_US')
            ->setTitle('test update title')
            ->setChapo('test update chapo')
            ->setDescription('test update description')
            ->setPostscriptum('test update postscriptum')
            ->setDispatcher($this->dispatcher)
        ;

        $action = new Profile();
        $action->update($event);

        $updatedProfile = $event->getProfile();

        $this->assertInstanceOf('Thelia\Model\Profile', $updatedProfile);

        $this->assertEquals($profile->getCode(), $updatedProfile->getCode());
        $this->assertEquals('en_US', $updatedProfile->getLocale());
        $this->assertEquals('test update title', $updatedProfile->getTitle());
        $this->assertEquals('test update chapo', $updatedProfile->getChapo());
        $this->assertEquals('test update description', $updatedProfile->getDescription());
        $this->assertEquals('test update postscriptum', $updatedProfile->getPostscriptum());

        return $updatedProfile;
    }

    /**
     * @depends testUpdate
     */
    public function testUpdateResourceAccess(ProfileModel $profile)
    {
        $event = new ProfileEvent();
        $event
            ->setId($profile->getId())
            ->setResourceAccess(array(
                    'admin.address' => array(AccessManager::CREATE)
                ))
            ->setDispatcher($this->dispatcher);
        ;

        $action = new Profile();
        $action->updateResourceAccess($event);

        $updatedProfile = $event->getProfile();

        $this->assertInstanceOf('Thelia\Model\Profile', $updatedProfile);

        $resources = $updatedProfile->getResources();

        $this->assertEquals(1, count($resources));

        $resource = $resources->getFirst();
        $this->assertEquals('admin.address', $resource->getCode());

        $profileResource = $updatedProfile->getProfileResources()->getFirst();
        $accessManager = new AccessManager($profileResource->getAccess());

        $this->assertTrue($accessManager->can(AccessManager::CREATE));
    }
}
