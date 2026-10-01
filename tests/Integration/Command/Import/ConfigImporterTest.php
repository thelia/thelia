<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Command\Import;

use Symfony\Component\Console\Output\NullOutput;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Command\Import\Importer\ConfigImporter;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The settings the demo shop gets: the header names the blog folder and the about page by
 * the ids the import gave them, and the terms are written on the consent as well as on the
 * deprecated setting the 1.1 themes still read.
 */
final class ConfigImporterTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        ConfigQuery::resetCache();
    }

    public function testTheDemoShopNamesItsHeaderAndItsTermsByTheImportedIds(): void
    {
        $fixtures = new FixtureFactory($this->getPropelConnection());
        $context = new DemoImportContext($this->getPropelConnection(), new NullOutput(), false, '', '');

        $information = $fixtures->folder();
        $blog = $fixtures->folder();
        $context->foldersByTitle = ['Information' => $information, 'Blog' => $blog];

        $aboutUs = $fixtures->content($information);
        $terms = $fixtures->content($information);
        $context->contentsByTitle = ['About us' => $aboutUs, 'Terms and Conditions' => $terms];

        (new ConfigImporter())->import($context);

        self::assertSame(\sprintf('folder:%d,content:%d', $blog->getId(), $aboutUs->getId()), ConfigQuery::read('header_menu_items', null, true));
        self::assertSame((string) $information->getId(), ConfigQuery::read('information_folder_id', null, true));
        self::assertSame((string) $terms->getId(), ConfigQuery::read('terms_conditions_content_id', null, true));

        ConsentTableMap::clearInstancePool();
        self::assertSame($terms->getId(), ConsentQuery::create()->findOneByCode(Consent::CODE_TERMS_AND_CONDITIONS)?->getContentId());
    }
}
