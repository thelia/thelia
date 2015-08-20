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

namespace Thelia\Tests\Rewriting;

/**
 * Class BaseRewritingObject
 * @package Thelia\Tests\Rewriting
 * @author Manuel Raynaud <manu@raynaud.io>
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
