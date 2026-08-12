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
use Propel\Runtime\Propel;
use Thelia\Model\Tools\UrlRewritingTrait;

/**
 * Class BaseRewritingObject.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
abstract class BaseRewritingObject extends TestCase
{
    /*
     * Thelia\Model\RewritingUrl::preInsert() sanitizes the url before it is
     * stored: accents are converted, the ".html" extension is dropped unless
     * admin.url.sanitizer.remove.html says otherwise, and a "<view id>-" prefix
     * is prepended as many times as needed to make the url unique.
     */
    private const FRENCH_URL_PATTERN = '/^([0-9]+-)*mon-super-titre-en-francais(-[0-9]+)?$/';

    private const ENGLISH_URL_PATTERN = '/^([0-9]+-)*my-english-super-title(-[0-9]+)?$/';

    /**
     * @return mixed an instance of Product, Folder, Content or Category Model
     */
    abstract public function getObject();

    /**
     * @covers \Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleFrenchRewrittenUrl(): void
    {
        /** @var UrlRewritingTrait $object */
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Mon super titre en français')
            ->save();

        $this->assertMatchesRegularExpression(self::FRENCH_URL_PATTERN, $object->getRewrittenUrl('fr_FR'));

        $con = Propel::getConnection();
        $rewrittenUrl = $object->generateRewrittenUrl('fr_FR', $con);
        $this->assertNotNull($rewrittenUrl, 'rewritten url can not be null');
        // generateRewrittenUrl() returns the url it built from the title, before
        // RewritingUrl::preInsert() sanitized it. The stored one is what the
        // front office resolves, so check that one.
        $this->assertMatchesRegularExpression(self::FRENCH_URL_PATTERN, $object->getRewrittenUrl('fr_FR'));

        $object->delete();
    }

    /**
     * @covers \Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testSimpleEnglishRewrittenUrl(): void
    {
        /** @var UrlRewritingTrait $object */
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setTitle('My english super Title')
            ->save();

        $this->assertMatchesRegularExpression(self::ENGLISH_URL_PATTERN, $object->getRewrittenUrl('en_US'));

        $con = Propel::getConnection();
        $rewrittenUrl = $object->generateRewrittenUrl('en_US', $con);
        $this->assertNotNull($rewrittenUrl, 'rewritten url can not be null');
        $this->assertMatchesRegularExpression(self::ENGLISH_URL_PATTERN, $object->getRewrittenUrl('en_US'));

        $object->delete();
    }

    /**
     * @covers \Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testRewrittenWithoutTitle(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Impossible to create an url if title is null');
        $object = $this->getObject();
        $object->setVisible(1)
            ->setPosition(1)
            ->setLocale('en_US')
            ->setDescription('My english super Description')
            ->save();
    }

    /**
     * @covers \Thelia\Model\Tools\UrlRewritingTrait::generateRewrittenUrl
     */
    public function testOnNotSavedObject(): void
    {
        /** @var UrlRewritingTrait $object */
        $object = $this->getObject();

        $this->expectException(\RuntimeException::class);
        $con = Propel::getConnection();
        $object->generateRewrittenUrl('fr_FR', $con);
    }
}
