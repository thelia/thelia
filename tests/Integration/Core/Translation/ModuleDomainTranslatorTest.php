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

namespace Thelia\Tests\Integration\Core\Translation;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Test\IntegrationTestCase;
use Twig\Environment;

/**
 * A module template rendered by a Twig theme translates with `|trans({}, '<module>')`
 * or `|trans({}, '<module>.fo.<template>')`. Those catalogues live in the Thelia
 * translator; the `|trans` filter resolves against the framework translator, which
 * must reach them on every request, front office included, and on the console.
 */
final class ModuleDomainTranslatorTest extends IntegrationTestCase
{
    public function testModuleDomainIsTranslated(): void
    {
        self::assertSame('Ordre du chèque', $this->translator()->trans('Pay cheque to', [], 'cheque', 'fr_FR'));
    }

    public function testModuleFrontOfficeTemplateDomainIsTranslated(): void
    {
        self::assertSame(
            'N\'oubliez pas de signer votre chèque !',
            $this->translator()->trans('Be sure to sign your cheque !', [], 'cheque.fo.default', 'fr_FR'),
        );
    }

    public function testTwigTransFilterTranslatesModuleDomainInCurrentLocale(): void
    {
        $translator = $this->translator();
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $previousLocale = $translator->getLocale();
        $translator->setLocale('fr_FR');

        try {
            /** @var Environment $twig */
            $twig = static::getContainer()->get('twig');
            $rendered = $twig->createTemplate("{{ 'Pay cheque to'|trans({}, 'cheque') }}")->render();
        } finally {
            $translator->setLocale($previousLocale);
        }

        self::assertSame('Ordre du chèque', $rendered);
    }

    public function testUnknownKeyInModuleDomainFallsBackToTheKey(): void
    {
        $key = 'missing.module.key.'.uniqid();

        self::assertSame($key, $this->translator()->trans($key, [], 'cheque', 'fr_FR'));
    }

    public function testFrameworkCatalogueStillWins(): void
    {
        self::assertSame('Tous droits réservés', $this->translator()->trans('All rights reserved.', [], 'email', 'fr_FR'));
    }

    private function translator(): TranslatorInterface
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get('translator');

        return $translator;
    }
}
