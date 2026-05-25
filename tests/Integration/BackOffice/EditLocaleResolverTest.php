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

namespace Thelia\Tests\Integration\BackOffice;

use BackOfficeDefaultTwigBundle\Service\I18n\EditLocaleResolver;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;

/**
 * Covers the resolver behind the back-office "Edit in" language switcher.
 *
 * Asserts the locale fed into the i18n forms tracks the edit_language_id
 * query parameter, and falls back to the configured default language for
 * missing or invalid values.
 */
final class EditLocaleResolverTest extends IntegrationTestCase
{
    private EditLocaleResolver $resolver;
    private Lang $defaultLang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new EditLocaleResolver();
        $this->defaultLang = Lang::getDefaultLanguage();
    }

    public function testResolveLangNullFallsBackToDefault(): void
    {
        self::assertSame((int) $this->defaultLang->getId(), (int) $this->resolver->resolveLang(null)->getId());
    }

    public function testResolveLangZeroFallsBackToDefault(): void
    {
        self::assertSame((int) $this->defaultLang->getId(), (int) $this->resolver->resolveLang(0)->getId());
    }

    public function testResolveLangUnknownIdFallsBackToDefault(): void
    {
        self::assertSame((int) $this->defaultLang->getId(), (int) $this->resolver->resolveLang(999_999)->getId());
    }

    public function testResolveLangKnownIdReturnsThatLang(): void
    {
        // Lang id=2 is en_US (seeded by the demo install).
        $resolved = $this->resolver->resolveLang(2);
        self::assertSame(2, (int) $resolved->getId());
        self::assertSame('en_US', $resolved->getLocale());
    }

    public function testResolveLocaleKnownIdReturnsLocaleString(): void
    {
        self::assertSame('en_US', $this->resolver->resolveLocale(2));
    }

    public function testResolveFromRequestWithoutParamFallsBackToDefault(): void
    {
        $request = new Request();
        self::assertSame((int) $this->defaultLang->getId(), (int) $this->resolver->resolveFromRequest($request)->getId());
    }

    public function testResolveFromRequestReadsEditLanguageIdQueryParam(): void
    {
        $request = new Request(['edit_language_id' => '2']);
        $resolved = $this->resolver->resolveFromRequest($request);
        self::assertSame(2, (int) $resolved->getId());
        self::assertSame('en_US', $resolved->getLocale());
    }
}
