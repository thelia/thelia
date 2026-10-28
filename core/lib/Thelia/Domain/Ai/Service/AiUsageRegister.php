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

use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Compliance\ComplianceState;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;

/**
 * The register of the AI usages of the shop, as rows: what the merchant can keep for the AI Act, and what the
 * admin API and the console export. One row per declared usage. Stable keys; the compliance messages come in
 * the language asked for.
 *
 * @internal
 */
final readonly class AiUsageRegister
{
    /** No transparency duty applies to the usage. */
    public const string NOT_APPLICABLE = 'not_applicable';

    public function __construct(
        private AiUsageRegistryInterface $usages,
        private AiUsageSettingRepository $settings,
        private AiTargetResolver $targets,
        private AiTextTranslator $translator,
    ) {
    }

    /**
     * @return list<array{
     *     id: string, module: string, purpose: string, audience: string, tier: string, transparency: string,
     *     active: bool, notice_enabled: bool, provenance_mark_enabled: bool, compliance: string,
     *     compliance_details: list<array{obligation: string, state: string, reason: string|null, message: string}>,
     *     jurisdiction: string|null
     * }>
     */
    public function rows(?string $locale = null): array
    {
        $locale = $this->translator->locale($locale);

        return array_map(fn (AsAiUsage $usage): array => $this->row($usage, $locale), $this->usages->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AsAiUsage $usage, string $locale): array
    {
        $setting = $this->settings->findByUsage($usage->id);
        $details = [];

        foreach ($this->usages->compliance($usage->id) as $status) {
            $details[] = [
                'obligation' => $status->obligation->value,
                'state' => $status->state->value,
                'reason' => $status->reason,
                'message' => $this->translator->trans($status->message, $status->parameters, $locale),
            ];
        }

        return [
            'id' => $usage->id,
            'module' => $usage->module(),
            'purpose' => $usage->purpose,
            'audience' => $usage->audience->value,
            'tier' => $usage->tier->value,
            'transparency' => $usage->transparency->value,
            'active' => $this->usages->isActive($usage->id),
            'notice_enabled' => $setting?->getTransparencyNotice() ?? true,
            'provenance_mark_enabled' => $setting?->getProvenanceMark() ?? true,
            'compliance' => self::overall($details),
            'compliance_details' => $details,
            'jurisdiction' => $this->jurisdiction($usage->id)?->value,
        ];
    }

    /**
     * Where the connection of the range of the usage processes the data; null when no active connection with a
     * model and a readable key serves the usage now.
     */
    private function jurisdiction(string $usageId): ?AiJurisdiction
    {
        try {
            return $this->targets->resolve($usageId)->jurisdiction;
        } catch (AiExceptionInterface) {
            return null;
        }
    }

    /**
     * The worst state of the duties of a usage.
     *
     * @param list<array{state: string}> $details
     */
    private static function overall(array $details): string
    {
        $states = array_column($details, 'state');

        foreach ([ComplianceState::NotMet, ComplianceState::ToReview, ComplianceState::Met] as $state) {
            if (\in_array($state->value, $states, true)) {
                return $state->value;
            }
        }

        return self::NOT_APPLICABLE;
    }
}
