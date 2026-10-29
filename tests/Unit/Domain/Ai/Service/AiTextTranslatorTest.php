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

namespace Thelia\Tests\Unit\Domain\Ai\Service;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Domain\Localization\Service\LangService;

final class AiTextTranslatorTest extends TestCase
{
    public function testTheLocaleAskedForWinsAndTheLangServiceIsNotAsked(): void
    {
        $langService = $this->createMock(LangService::class);
        $langService->expects(self::never())->method('getLocale');

        self::assertSame('de_DE', (new AiTextTranslator($langService))->locale('de_DE'));
    }

    public function testWithoutALocaleTheAnswerOfTheLangServiceIsTheOneOfTheCaller(): void
    {
        $langService = $this->createMock(LangService::class);
        $langService->method('getLocale')->willReturn('it_IT');

        $translator = new AiTextTranslator($langService);

        self::assertSame('it_IT', $translator->locale());
        self::assertSame('it_IT', $translator->locale(''));
    }
}
