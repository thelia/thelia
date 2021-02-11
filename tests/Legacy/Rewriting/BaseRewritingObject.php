<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Rewriting;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Propel;
use Thelia\Model\Tools\UrlRewritingTrait;

/**
 * Class BaseRewritingObject
 * @package Thelia\Tests\Rewriting
 * @author Manuel Raynaud <manu@raynaud.io>
 */
abstract class BaseRewritingObject extends TestCase
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
        /** @var UrlRewritingTrait $object */
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Mon super titre en français')
            ->save();

        $this->assertMatchesRegularExpression('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $object->getRewrittenUrl('fr_FR'));

        $con = Propel::getConnection();
        $rewrittenUrl = $object->generateRewrittenUrl('fr_FR', $con);
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertMatchesRegularExpression('/^mon-super-titre-en-français(-[0-9]+)?\.html$/', $rewrittenUrl);
        //mon-super-titre-en-français-2.html

        $object->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleEnglishRewrittenUrl()
    {
        /** @var UrlRewritingTrait $object */
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setTitle('My english super Title')
            ->save();

        $this->assertMatchesRegularExpression('/^my-english-super-title(-[0-9]+)?\.html$/', $object->getRewrittenUrl('en_US'));

        $con = Propel::getConnection();
        $rewrittenUrl = $object->generateRewrittenUrl('en_US', $con);
        $this->assertNotNull($rewrittenUrl, "rewritten url can not be null");
        $this->assertMatchesRegularExpression('/^my-english-super-title(-[0-9]+)?\.html$/', $rewrittenUrl);

        $object->delete();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testRewrittenWithoutTitle()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Impossible to create an url if title is null");
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setDescription('My english super Description')
            ->save();
    }

    /**
     * @covers Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testOnNotSavedObject()
    {
        /** @var UrlRewritingTrait $object */
        $object = $this->getObject();

        $this->expectException(\RuntimeException::class);
        $con = Propel::getConnection();
        $object->generateRewrittenUrl('fr_FR', $con);
    }
}
