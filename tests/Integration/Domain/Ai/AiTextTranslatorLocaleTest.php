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

namespace Thelia\Tests\Integration\Domain\Ai;

use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The texts of the AI layer follow the language the caller reads: the back-office language in the back-office,
 * the visitor's on the storefront, the default language of the shop with no request at all.
 */
final class AiTextTranslatorLocaleTest extends IntegrationTestCase
{
    public function testWithoutARequestTheDefaultLanguageIsUsed(): void
    {
        self::assertSame(Lang::getDefaultLanguage()->getLocale(), $this->translator(new RequestStack())->locale());
    }

    public function testInTheBackOfficeTheLanguageOfTheBackOfficeIsUsedNotTheOneOfTheShop(): void
    {
        Request::$isAdminEnv = true;

        self::assertSame('fr_FR', $this->translator($this->stackWith(shop: 'en_US', backOffice: 'fr_FR'))->locale());
    }

    public function testOnTheStorefrontTheLanguageOfTheVisitorIsUsed(): void
    {
        Request::$isAdminEnv = false;

        self::assertSame('en_US', $this->translator($this->stackWith(shop: 'en_US', backOffice: 'fr_FR'))->locale());
    }

    private function translator(RequestStack $stack): AiTextTranslator
    {
        return new AiTextTranslator(new LangService($stack, new NullLogger()));
    }

    private function stackWith(string $shop, string $backOffice): RequestStack
    {
        $session = new Session(new MockArraySessionStorage());
        $session->setLang(LangQuery::create()->findOneByLocale($shop) ?? throw new \LogicException($shop));
        $session->setAdminLang(LangQuery::create()->findOneByLocale($backOffice) ?? throw new \LogicException($backOffice));

        $request = new Request();
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
