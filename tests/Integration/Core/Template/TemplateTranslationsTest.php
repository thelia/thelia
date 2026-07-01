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

namespace Thelia\Tests\Integration\Core\Template;

use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * The email and PDF template packages ship their own Symfony translation catalogs
 * (templates/{email,pdf}/<name>/translations/<domain>.<locale>.php); the framework
 * translator must load them so the templates can use the native |trans filter with their
 * own domain, including Symfony-style placeholders (%ref%).
 */
final class TemplateTranslationsTest extends IntegrationTestCase
{
    public function testEmailCatalogIsLoaded(): void
    {
        self::assertSame('Tous droits réservés', $this->translator()->trans('All rights reserved.', [], 'email', 'fr_FR'));
    }

    public function testEmailCatalogSubstitutesPlaceholders(): void
    {
        $result = $this->translator()->trans(
            'Here is the details of your order %ref% placed on %date%',
            ['%ref%' => 'ORD-1', '%date%' => '2026-01-15'],
            'email',
            'fr_FR',
        );

        self::assertSame('Voici les détails de votre commande ORD-1 passée le 2026-01-15', $result);
    }

    public function testPdfCatalogIsLoaded(): void
    {
        self::assertSame('Adresse de livraison', $this->translator()->trans('Delivery address', [], 'pdf', 'fr_FR'));
    }

    private function translator(): TranslatorInterface
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get('translator');

        return $translator;
    }
}
