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

namespace Thelia\Domain\Ai\Guardrail;

use Thelia\Domain\Ai\Service\AiTextTranslator;

/**
 * The reasons the guardrails and the gateway write to the log (`unavailable`, `jurisdiction`, `sanitized`,
 * `noted: …`), told in a sentence a merchant understands, in the domain `core`. The screen of the usages and
 * the API show these sentences; the log keeps the short codes. An unknown code is returned as it is.
 *
 * @internal
 */
final readonly class AiVerdictReasons
{
    /** Code => sentence (English source text, also the translation identifier). */
    private const array SENTENCES = [
        GuardrailAvailability::UNAVAILABLE => 'This check could not run: it is not installed, or its provider did not answer.',
        GuardrailAvailability::NO_CONNECTION => 'No active connection with a key is set up for the provider this check calls.',
        GuardrailAvailability::JURISDICTION => 'The only connection this check could use is outside the EU, and the option "EU only" is on.',
        GuardrailAvailability::NO_MODEL => 'No model is assigned to the range this check needs.',
        GuardrailAvailability::INACTIVE => 'The usage this check relies on, or the whole AI layer, is switched off.',
        'guardrail_error' => 'This check failed unexpectedly.',
        'invalid_json' => 'The answer was not valid JSON.',
        'truncated' => 'The answer was cut short: it reached the output limit.',
        'sanitized' => 'The HTML of the answer was cleaned: unsafe parts were removed or rewritten.',
        'off_topic' => 'The message is off topic for this usage.',
        'input_too_long' => 'The message is longer than the limit set for this usage.',
        'rate_limit' => 'This person sent too many requests in a short time.',
        'budget' => 'The monthly budget of this usage is used up.',
        'budget_global' => 'The monthly cap of the whole AI layer is reached.',
    ];

    /** Code followed by details => sentence with %details%. */
    private const array WITH_DETAILS = [
        'schema_mismatch' => 'The answer does not have the expected shape (%details%).',
        'noted' => 'The moderation flagged categories without blocking: %details%.',
        'flagged' => 'The moderation blocked the content: %details%.',
    ];

    public function __construct(private AiTextTranslator $translator)
    {
    }

    public function explain(string $reason, ?string $locale = null): string
    {
        if (isset(self::SENTENCES[$reason])) {
            return $this->translator->trans(self::SENTENCES[$reason], [], $locale);
        }

        $separator = strpos($reason, ': ');

        if (false !== $separator) {
            $code = substr($reason, 0, $separator);

            if (isset(self::WITH_DETAILS[$code])) {
                return $this->translator->trans(self::WITH_DETAILS[$code], ['%details%' => substr($reason, $separator + 2)], $locale);
            }
        }

        return $reason;
    }

    /**
     * @return list<string> the English sentences, to check they are all in the catalogs
     */
    public static function sources(): array
    {
        return [...array_values(self::SENTENCES), ...array_values(self::WITH_DETAILS)];
    }
}
