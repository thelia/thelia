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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Test\IntegrationTestCase;

final class I18nBehaviorTest extends IntegrationTestCase
{
    public function testBrandI18nRoundTrip(): void
    {
        $factory = $this->createFixtureFactory();
        $brand = $factory->brand(['title' => 'English Title']);

        // Set a French translation.
        $brand->setLocale('fr_FR')
            ->setTitle('Titre Français')
            ->setChapo('Court résumé')
            ->save();

        // Reload and verify both locales.
        $reloaded = BrandQuery::create()->findPk($brand->getId());
        self::assertNotNull($reloaded);

        self::assertSame('English Title', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('Titre Français', $reloaded->setLocale('fr_FR')->getTitle());
        self::assertSame('Court résumé', $reloaded->getChapo());
    }

    public function testCategoryI18nMultipleLocales(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();

        $category->setLocale('en_US')->setTitle('Shoes')->setDescription('All shoes');
        $category->setLocale('fr_FR')->setTitle('Chaussures')->setDescription('Toutes les chaussures');
        $category->save();

        $reloaded = CategoryQuery::create()->findPk($category->getId());

        self::assertSame('Shoes', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('Chaussures', $reloaded->setLocale('fr_FR')->getTitle());
        self::assertSame('Toutes les chaussures', $reloaded->getDescription());
    }

    public function testI18nFallsBackToEmptyStringForMissingLocale(): void
    {
        $factory = $this->createFixtureFactory();
        $brand = $factory->brand(['title' => 'Only English']);

        $brand->setLocale('de_DE');
        // No German translation set, title should be null or empty.
        self::assertNull($brand->getTitle());
    }
}
