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