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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\DependencyInjection\Compiler\RegisterAiUsagePass;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Compliance\AiComplianceStatus;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;

/**
 * The usages found at build time (RegisterAiUsagePass), read back from the attribute of their class.
 *
 * @internal
 */
class AiUsageRegistry implements AiUsageRegistryInterface
{
    /** @var array<string, AsAiUsage>|null */
    private ?array $usages = null;

    /**
     * @param array<string, class-string> $usageClasses usage identifier => class that declares it
     */
    public function __construct(
        #[Autowire(param: RegisterAiUsagePass::PARAMETER)]
        private readonly array $usageClasses,
        private readonly AiUsageSettingRepository $settings,
        private readonly AiSettings $globalSettings,
    ) {
    }

    public function get(string $usageId): AsAiUsage
    {
        return $this->usages()[$usageId] ?? throw new UnknownAiUsageException($usageId);
    }

    public function all(): array
    {
        return array_values($this->usages());
    }

    public function isActive(string $usageId): bool
    {
        $usage = $this->get($usageId);

        if (!$this->globalSettings->isEnabled()) {
            return false;
        }

        $setting = $this->settings->findByUsage($usageId);

        if (null !== $setting && !$setting->getEnabled()) {
            return false;
        }

        if (AiAudience::Public === $usage->audience) {
            return 1 === preg_match('/[1-9]/', (string) $setting?->getMonthlyBudget());
        }

        return true;
    }

    public function compliance(string $usageId): array
    {
        $usage = $this->get($usageId);
        $setting = $this->settings->findByUsage($usageId);

        return AiComplianceStatus::evaluate(
            $usage,
            $setting?->getTransparencyNotice() ?? true,
            $setting?->getProvenanceMark() ?? true,
        );
    }

    /**
     * @return array<string, AsAiUsage>
     */
    private function usages(): array
    {
        if (null !== $this->usages) {
            return $this->usages;
        }

        $usages = [];

        foreach ($this->usageClasses as $usageId => $class) {
            foreach ((new \ReflectionClass($class))->getAttributes(AsAiUsage::class) as $attribute) {
                $usage = $attribute->newInstance();

                if ($usage->id === $usageId) {
                    $usages[$usageId] = $usage;
                }
            }
        }

        return $this->usages = $usages;
    }
}
