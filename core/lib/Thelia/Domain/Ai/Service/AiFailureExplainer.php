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

namespace Thelia\Domain\Ai\Service;

use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\AiKeyDecryptionException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\AiRateLimitExceededException;
use Thelia\Domain\Ai\Exception\AiUsageViolationException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Vault\AiEnvironmentKey;

/**
 * The message of an exception of the AI layer in plain words, in the language asked for (domain `core`).
 *
 * The messages of the exceptions are English and written for the log and the developer. A screen or a mail that
 * shows the failure to a person calls explain() instead of getMessage(): it works without a request (console,
 * queue worker), the locale being the one asked for, else the one of the visitor, else the one of the shop.
 *
 * A refusal whose message carries a variable part (the accepted values, a field, the value given) is recognised by
 * its shape and told with the same parts in a sentence of the catalog.
 *
 * An exception of the layer with no sentence of its own (a bug of the calling code) comes back with its own message, as AiVerdictReasons does with an unknown code.
 * Nothing the provider wrote, nor any key, is part of the sentence.
 */
final readonly class AiFailureExplainer
{
    /** The refusals of a value the settings writer and the validators of the layer raise with a fixed sentence (English source text, also the translation identifier). */
    private const array FIXED_SENTENCES = [
        'The code is lower case letters, digits, "-" and "_", 64 characters at most.',
        'Another connection already has this code.',
        'The title is required.',
        'The jurisdiction ("eu" or "non_eu") is required for this provider.',
        AiEnvironmentKey::NOT_ALLOWED,
        AiEnvironmentKey::OTHER_ADDRESS,
        AiEnvironmentKey::INVALID_NAME,
        'The connection now points to another server: type its API key again, the saved key is not sent to a new address.',
        'Tiers, usages or models rely on the provider of this connection: move them to another connection before changing its provider.',
        'The connection does not exist.',
        'The model does not exist.',
        'The model is not served by this connection.',
        'This tier already has an assignment: update it instead.',
        'This usage already has settings: update them instead.',
        'The pinned connection does not exist.',
        'The pinned model does not exist.',
        'The pinned model is not served by the pinned connection.',
        'The base URL is not allowed: it contains a space or a control character.',
        'The base URL is not allowed: it must be an absolute http or https address.',
        'The base URL is not allowed: it must not carry credentials.',
        'The application secret (APP_SECRET) is empty: API keys cannot be encrypted with it.',
        AiSettingsWriter::CONNECTION_IN_TIER,
        AiSettingsWriter::MODEL_IN_TIER,
        AiSettingsWriter::CONNECTION_STILL_USED,
        AiSettingsWriter::MODEL_STILL_USED,
    ];

    /**
     * The refusals whose message carries a variable part: the pattern that recognises the English message and
     * the sentence (also the translation identifier) that tells it, with the named groups as %parameters%.
     */
    private const array VARIABLE_SENTENCES = [
        '/^The tier must be one of: (?<allowed>.+)\.$/' => 'The tier must be one of: %allowed%.',
        '/^The provider must be one of: (?<allowed>.+)\.$/' => 'The provider must be one of: %allowed%.',
        '/^"(?<name>.+)" is not the name of an optional guardrail\.$/' => '"%name%" is not the name of an optional guardrail.',
        '/^The mode of the guardrail "(?<name>.+)" is "block" or "annotate"\.$/' => 'The mode of the guardrail "%name%" is "block" or "annotate".',
        '/^(?<field>The limit per minute|The limit per day|The output limit|The input limit|The prompt retention) must be a positive number\.$/' => '%field% must be a positive number.',
        '/^(?<field>The monthly budget) must be a non-negative decimal string like "0\.05421000", "(?<value>.*)" given\.$/' => '%field% must be a non-negative decimal like "25.00", "%value%" given.',
        '/^"(?<value>.*)" is not an ISO 4217 currency code\.$/' => '"%value%" is not an ISO 4217 currency code.',
        '/^This connection is pinned for the usage "(?<usage>[^"]+)": pin the usage elsewhere first\.$/' => 'This connection is pinned for the usage "%usage%": pin the usage elsewhere first.',
        '/^This model is pinned for the usage "(?<usage>[^"]+)": pin the usage elsewhere first\.$/' => 'This model is pinned for the usage "%usage%": pin the usage elsewhere first.',
        '/^(?<field>A guardrail name) "(?<value>.*)" must hold at most 64 letters, digits, ".", "_", ":" or "-"\.$/' => '%field% "%value%" must hold at most 64 letters, digits, ".", "_", ":" or "-".',
    ];

    /** The names of a field a variable sentence carries (English source text, also the translation identifier). */
    private const array FIELDS = [
        'The limit per minute', 'The limit per day', 'The output limit', 'The input limit', 'The prompt retention', 'The monthly budget', 'A guardrail name',
    ];

    /** What AiConnectionTester stores in the column `check_error` when the test fails before or after the provider answered. */
    public const string CHECK_PROVIDER_UNSUPPORTED = 'The provider of this connection is not supported.';
    public const string CHECK_NO_MODEL = 'No model is known for this connection: add one first.';
    private const array CHECK_ERRORS = [self::CHECK_PROVIDER_UNSUPPORTED, self::CHECK_NO_MODEL];

    private const string CHECK_ERROR_PROVIDER = 'The AI connection "%connection%" failed: %details%';

    private const string BUDGET = 'The monthly budget of this usage is used up.';
    private const string BUDGET_GLOBAL = 'The monthly cap of the whole AI layer is reached.';
    private const string INPUT_BLOCKED = 'A safety check blocked this request.';
    private const string OUTPUT_BLOCKED = 'A safety check blocked the answer.';
    private const string JURISDICTION = 'The AI connection "%connection%" processes data outside the European Union, and the option "EU only" is on.';
    private const string KEY_UNREADABLE = 'The API key of the AI connection "%connection%" cannot be read: it was saved with another application secret. Enter the key again in the connection.';
    private const string PROVIDER_RETRYABLE = 'The provider of the AI connection "%connection%" did not answer. Try again in a few minutes.';
    private const string PROVIDER_REFUSED = 'The provider of the AI connection "%connection%" refused the call. Check the key, the model and the address of the connection.';
    private const string RATE_LIMIT = 'Too many requests in a short time. Try again in a moment.';
    private const string USAGE_VIOLATION = 'This AI feature is not set up for this kind of request.';
    private const string USAGE_INACTIVE = 'This AI feature is switched off.';
    private const string USAGE_UNKNOWN = 'This AI feature does not exist: its module may be off.';

    public function __construct(private AiTextTranslator $translator)
    {
    }

    public function explain(AiExceptionInterface $failure, ?string $locale = null): string
    {
        $sentence = match (true) {
            $failure instanceof AiBudgetExceededException => [$failure->globalCap ? self::BUDGET_GLOBAL : self::BUDGET, []],
            $failure instanceof AiGuardrailBlockedException => [GuardrailStage::Input === $failure->verdict->stage ? self::INPUT_BLOCKED : self::OUTPUT_BLOCKED, []],
            $failure instanceof AiJurisdictionRefusedException => [self::JURISDICTION, ['%connection%' => $failure->connection]],
            $failure instanceof AiKeyDecryptionException => [self::KEY_UNREADABLE, ['%connection%' => $failure->connection]],
            $failure instanceof AiProviderUnavailableException => [$failure->retryable ? self::PROVIDER_RETRYABLE : self::PROVIDER_REFUSED, ['%connection%' => $failure->connection]],
            $failure instanceof AiRateLimitExceededException => [self::RATE_LIMIT, []],
            $failure instanceof AiUsageViolationException => [self::USAGE_VIOLATION, []],
            $failure instanceof InactiveAiUsageException => [self::USAGE_INACTIVE, []],
            $failure instanceof UnknownAiUsageException => [self::USAGE_UNKNOWN, []],
            \in_array($failure->getMessage(), self::FIXED_SENTENCES, true) => [$failure->getMessage(), []],
            default => null,
        };

        $sentence ??= $this->variableSentence($failure->getMessage(), $locale);

        return null === $sentence ? $failure->getMessage() : $this->translator->trans($sentence[0], $sentence[1], $locale);
    }

    /**
     * The error AiConnectionTester stored for a connection, told in the language asked for. The column keeps the
     * English text, so the API and the existing rows do not change; a text this class does not know comes back as stored.
     * The details a provider wrote after the name of the connection are kept as they are: the test shows them to the
     * administrator who has to fix the connection, and they never hold the key.
     */
    public function explainCheckError(string $stored, ?string $locale = null): string
    {
        // A refusal of the settings stops the test before any call, the variable of the key for one.
        if (\in_array($stored, self::CHECK_ERRORS, true) || \in_array($stored, self::FIXED_SENTENCES, true)) {
            return $this->translator->trans($stored, [], $locale);
        }

        if (1 === preg_match('/^The AI connection "(?<connection>[^"]*)" failed: (?<details>.*)$/s', $stored, $matches)) {
            return $this->translator->trans(self::CHECK_ERROR_PROVIDER, ['%connection%' => $matches['connection'], '%details%' => $matches['details']], $locale);
        }

        return $stored;
    }

    /**
     * @return array{string, array<string, string>}|null
     */
    private function variableSentence(string $message, ?string $locale): ?array
    {
        foreach (self::VARIABLE_SENTENCES as $pattern => $sentence) {
            if (1 !== preg_match($pattern, $message, $matches)) {
                continue;
            }

            $parameters = [];

            foreach ($matches as $name => $value) {
                if (\is_string($name)) {
                    $parameters['%'.$name.'%'] = 'field' === $name ? $this->translator->trans($value, [], $locale) : $value;
                }
            }

            return [$sentence, $parameters];
        }

        return null;
    }

    /**
     * @return list<string> the English source texts, to check they are all in the catalogs
     */
    public static function sources(): array
    {
        return array_values(array_unique([
            ...self::FIXED_SENTENCES,
            ...array_values(self::VARIABLE_SENTENCES),
            ...self::FIELDS,
            ...self::CHECK_ERRORS,
            self::CHECK_ERROR_PROVIDER,
            self::BUDGET, self::BUDGET_GLOBAL, self::INPUT_BLOCKED, self::OUTPUT_BLOCKED, self::JURISDICTION, self::KEY_UNREADABLE,
            self::PROVIDER_RETRYABLE, self::PROVIDER_REFUSED, self::RATE_LIMIT, self::USAGE_VIOLATION, self::USAGE_INACTIVE, self::USAGE_UNKNOWN,
        ]));
    }
}
