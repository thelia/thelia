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
use Thelia\Model\Category;


/**
 * Class CategoryRewritingTest
 * @package Thelia\Tests\Rewriting
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CategoryRewritingTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleFrenchRewrittenUrl()
    {
        $category = new Category();
        $category->setVisible(1)
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Mon super titre en français')
            ->save();

        $this->assertRegExp('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $category->getRewrittenUrl('fr_FR'));

        $rewrittenUrl = $category->generateRewrittenUrl('fr_FR');
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertRegExp('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $rewrittenUrl);
        //mon-super-titre-en-français-2.html

        $category->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleEnglishRewrittenUrl()
    {
        $category = new Category();
        $category->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setTitle('My english super Title')
            ->save();

        $this->assertRegExp('/^my-english-super-title(-[0-9]+)?\.html$/', $category->getRewrittenUrl('en_US'));

        $rewrittenUrl = $category->generateRewrittenUrl('en_US');
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertRegExp('/^my-english-super-title(-[0-9]+)?\.html$/', $rewrittenUrl);

        $category->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Impossible to create an url if title is null
     */
    public function testRewrittenWithoutTitle()
    {
        $category = new Category();
        $category->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setDescription('My english super Description')
            ->save();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Object category must be saved before generating url
     */
    public function testOnNotSavedProduct()
    {
        $product = new Category();

        $product->generateRewrittenUrl('fr_FR');
    }
}