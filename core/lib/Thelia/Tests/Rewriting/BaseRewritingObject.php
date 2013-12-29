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

namespace Thelia\Tests\Rewriting;

/**
 * Class BaseRewritingObject
 * @package Thelia\Tests\Rewriting
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
abstract class BaseRewritingObject extends \PHPUnit_Framework_TestCase
{

    /**
     * @return mixed an instance of Product, Folder, Content or Category Model
     */
    abstract public function getObject();

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleFrenchRewrittenUrl()
    {
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Mon super titre en français')
            ->save();

        $this->assertRegExp('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $object->getRewrittenUrl('fr_FR'));

        $rewrittenUrl = $object->generateRewrittenUrl('fr_FR');
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertRegExp('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $rewrittenUrl);
        //mon-super-titre-en-français-2.html

        $object->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleEnglishRewrittenUrl()
    {
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setTitle('My english super Title')
            ->save();

        $this->assertRegExp('/^my-english-super-title(-[0-9]+)?\.html$/', $object->getRewrittenUrl('en_US'));

        $rewrittenUrl = $object->generateRewrittenUrl('en_US');
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertRegExp('/^my-english-super-title(-[0-9]+)?\.html$/', $rewrittenUrl);

        $object->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Impossible to create an url if title is null
     */
    public function testRewrittenWithoutTitle()
    {
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setDescription('My english super Description')
            ->save();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     * @expectedException \RuntimeException
     */
    public function testOnNotSavedObject()
    {
        $object = $this->getObject();

        $object->generateRewrittenUrl('fr_FR');
    }
}
