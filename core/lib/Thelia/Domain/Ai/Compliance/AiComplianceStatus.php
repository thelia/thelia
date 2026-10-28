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

namespace Thelia\Domain\Ai\Compliance;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiTransparency;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * Where a usage stands on one duty of the AI Act, in words a merchant understands. The core never blocks a
 * usage that does not comply: it says so, in the register of AI usages and on the screen of the usage.
 *
 * The message is the English source text and the translation identifier; its placeholders are in $parameters.
 */
#[Exclude]
final readonly class AiComplianceStatus
{
    private const string INTERACTION_MET = 'People are told they are talking with an AI.';
    private const string INTERACTION_NOT_MET = 'This usage does not comply with article 50 of the EU AI Act: people are not told they are talking with an AI. The law asks to tell them before their first message. Turn the notice back on in the settings of %usage%.';
    private const string GENERATED_MET = 'Generated content is marked as made by an AI.';
    private const string GENERATED_NOT_MET = 'This usage does not comply with article 50 of the EU AI Act: the content it generates is not marked as made by an AI. The law asks for a mark that a machine can read. Turn the mark back on in the settings of %usage%.';
    private const string UNDECLARED = 'This usage answers visitors or customers but does not say whether people talk with it. If they do, article 50 of the EU AI Act asks to tell them it is an AI: ask the author of the module.';

    /**
     * @param array<string, string> $parameters
     * @param string|null           $reason     short code of why the duty is not met or not clear (`notice_turned_off`,
     *                                          `mark_turned_off`, `no_duty_declared`), null when it is met
     */
    public function __construct(
        public AiActObligation $obligation,
        public ComplianceState $state,
        public string $message,
        public array $parameters = [],
        public ?string $reason = null,
    ) {
        AiAssert::notBlank($message, 'The message of a compliance status');
    }

    /**
     * The statuses of a usage, from what its module declared and what the merchant set.
     *
     * @param bool $noticeShown      the notice "you are talking with an AI" is on (on by default)
     * @param bool $provenanceMarked the provenance mark of generated content is on (on by default)
     *
     * @return list<self> empty when the usage falls under no transparency duty
     */
    public static function evaluate(AsAiUsage $usage, bool $noticeShown, bool $provenanceMarked): array
    {
        $parameters = ['%usage%' => $usage->id];

        return match ($usage->transparency) {
            AiTransparency::Interactive => [$noticeShown
                ? new self(AiActObligation::Interaction, ComplianceState::Met, self::INTERACTION_MET, $parameters)
                : new self(AiActObligation::Interaction, ComplianceState::NotMet, self::INTERACTION_NOT_MET, $parameters, 'notice_turned_off')],
            AiTransparency::Generative => [$provenanceMarked
                ? new self(AiActObligation::GeneratedContent, ComplianceState::Met, self::GENERATED_MET, $parameters)
                : new self(AiActObligation::GeneratedContent, ComplianceState::NotMet, self::GENERATED_NOT_MET, $parameters, 'mark_turned_off')],
            AiTransparency::None => \in_array($usage->audience, [AiAudience::Public, AiAudience::Customer], true)
                ? [new self(AiActObligation::Interaction, ComplianceState::ToReview, self::UNDECLARED, $parameters, 'no_duty_declared')]
                : [],
        };
    }

    public function isMet(): bool
    {
        return ComplianceState::Met === $this->state;
    }

    /**
     * The message in the language of the merchant, the placeholders filled.
     */
    public function translate(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->message, $this->parameters, 'core', $locale);
    }

    /**
     * @return list<string> the English source texts, to check they are all in the catalogs
     */
    public static function sources(): array
    {
        return [self::INTERACTION_MET, self::INTERACTION_NOT_MET, self::GENERATED_MET, self::GENERATED_NOT_MET, self::UNDECLARED];
    }
}
