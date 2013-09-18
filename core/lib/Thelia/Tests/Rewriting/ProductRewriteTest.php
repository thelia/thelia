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
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;


/**
 * Class ProductRewriteTest
 * @package Thelia\Tests\Rewriting
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ProductRewriteTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleFrenchRewrittenUrl()
    {
        $product = new Product();
        $product->setRef(sprintf("TestRewrittenProduct%s",uniqid()))
            ->setVisible(1)
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Mon super titre en français')
            ->save();

        $this->assertRegExp('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $product->getRewrittenUrl('fr_FR'));

        $rewrittenUrl = $product->generateRewrittenUrl('fr_FR');
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertRegExp('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $rewrittenUrl);
        //mon-super-titre-en-français-2.html

        $product->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleEnglishRewrittenUrl()
    {
        $product = new Product();
        $product->setRef(sprintf("TestRewrittenProduct%s",uniqid()))
            ->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setTitle('My english super Title')
            ->save();

        $this->assertRegExp('/^my-english-super-title(-[0-9]+)?\.html$/', $product->getRewrittenUrl('en_US'));

        $rewrittenUrl = $product->generateRewrittenUrl('en_US');
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertRegExp('/^my-english-super-title(-[0-9]+)?\.html$/', $rewrittenUrl);

        $product->delete();
    }

    public function testRewrittenWithoutTitle()
    {
        $product = new Product();
        $product->setRef(sprintf("TestRewrittenProduct%s",uniqid()))
            ->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setDescription('My english super Description')
            ->save();

        $this->assertEquals(strtolower(sprintf('%s.html', $product->getRef())), $product->getRewrittenUrl('en_US'));

        $rewrittenUrl = $product->generateRewrittenUrl('en_US');
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertEquals(strtolower(sprintf('%s-1.html', $product->getRef())), $rewrittenUrl);

        $product->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Object product must be saved before generating url
     */
    public function testOnNotSavedProduct()
    {
        $product = new Product();

        $product->generateRewrittenUrl('fr_FR');
    }
}