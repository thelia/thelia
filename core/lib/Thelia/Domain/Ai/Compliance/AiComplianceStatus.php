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
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public AiActObligation $obligation,
        public ComplianceState $state,
        public string $message,
        public array $parameters = [],
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
                ? new self(AiActObligation::Interaction, ComplianceState::Met, 'People are told they are talking with an AI.', $parameters)
                : new self(AiActObligation::Interaction, ComplianceState::NotMet, 'This usage does not comply with article 50(1) of the AI Act: people are no longer told they are talking with an AI. Turn the notice back on in the settings of %usage%.', $parameters)],
            AiTransparency::Generative => [$provenanceMarked
                ? new self(AiActObligation::GeneratedContent, ComplianceState::Met, 'Generated content is marked as made by an AI.', $parameters)
                : new self(AiActObligation::GeneratedContent, ComplianceState::NotMet, 'This usage does not comply with article 50(2) of the AI Act: generated content is no longer marked as made by an AI. Turn the mark back on in the settings of %usage%.', $parameters)],
            AiTransparency::None => \in_array($usage->audience, [AiAudience::Public, AiAudience::Customer], true)
                ? [new self(AiActObligation::Interaction, ComplianceState::ToReview, 'This usage answers visitors or customers but declares no transparency duty. If people talk with it, article 50(1) of the AI Act asks to tell them it is an AI: check with the author of the module.', $parameters)]
                : [],
        };
    }

    public function isMet(): bool
    {
        return ComplianceState::Met === $this->state;
    }
}
