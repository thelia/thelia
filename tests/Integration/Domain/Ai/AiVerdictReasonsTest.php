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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Ai\Guardrail\AiVerdictReasons;
use Thelia\Domain\Ai\Guardrail\GuardrailAvailability;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Test\IntegrationTestCase;

final class AiVerdictReasonsTest extends IntegrationTestCase
{
    public function testEveryCodeOfTheLogIsExplainedInFrenchAndEnglish(): void
    {
        $reasons = new AiVerdictReasons(new AiTextTranslator(new RequestStack()));

        foreach (['unavailable', 'jurisdiction', 'no_connection', 'no_model', 'inactive', 'guardrail_error', 'invalid_json', 'truncated', 'sanitized', 'off_topic', 'input_too_long', 'rate_limit', 'budget', 'budget_global'] as $code) {
            $french = $reasons->explain($code, 'fr_FR');
            $english = $reasons->explain($code, 'en_US');

            self::assertNotSame($code, $french, $code);
            self::assertNotSame($french, $english, $code);
            self::assertStringEndsWith('.', $english, $code);
        }

        self::assertSame('Aucun modèle n\'est affecté à la gamme dont cette vérification a besoin.', $reasons->explain(GuardrailAvailability::NO_MODEL, 'fr_FR'));
    }

    public function testTheDetailsOfAReasonAreKept(): void
    {
        $reasons = new AiVerdictReasons(new AiTextTranslator(new RequestStack()));

        self::assertSame('La modération a signalé des catégories sans bloquer : pii,law.', $reasons->explain('noted: pii,law', 'fr_FR'));
        self::assertSame('The moderation blocked the content: hate.', $reasons->explain('flagged: hate', 'en_US'));
        self::assertSame('La réponse n\'a pas la forme attendue (/title: type).', $reasons->explain('schema_mismatch: /title: type', 'fr_FR'));
    }

    public function testAnUnknownCodeIsReturnedAsItIs(): void
    {
        self::assertSame('something_new', (new AiVerdictReasons(new AiTextTranslator(new RequestStack())))->explain('something_new', 'fr_FR'));
    }
}
