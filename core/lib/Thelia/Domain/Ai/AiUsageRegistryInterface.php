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

namespace Thelia\Domain\Ai;

/**
 * The usages the active modules declared with #[AsAiUsage], and whether each can run now.
 */
interface AiUsageRegistryInterface
{
    /**
     * @throws Exception\UnknownAiUsageException
     */
    public function get(string $usageId): AsAiUsage;

    /**
     * @return list<AsAiUsage>
     */
    public function all(): array;

    /**
     * Whether a request of this usage would pass the checks of its settings now: turned on, a model for its
     * tier with its capabilities, a budget above zero for a public usage, budget left this month. Use it to
     * hide a feature; the gateway checks again at each call.
     *
     * @throws Exception\UnknownAiUsageException
     */
    public function isActive(string $usageId): bool;

    /**
     * Where the usage stands on the AI Act, from its declaration and the settings of the merchant
     * (AiComplianceStatus::evaluate()). Informative: a usage that does not comply still runs.
     *
     * @return list<Compliance\AiComplianceStatus>
     *
     * @throws Exception\UnknownAiUsageException
     */
    public function compliance(string $usageId): array;

    /**
     * The notice "you are talking with an AI" (AI Act, article 50(1)) to show before a visitor writes to an
     * interactive usage, translated in the domain `core` into $locale (the current one when null). Null when
     * the usage is not interactive or the merchant turned the notice off for it: show nothing then.
     *
     * @throws Exception\UnknownAiUsageException
     */
    public function transparencyNotice(string $usageId, ?string $locale = null): ?string;
}
