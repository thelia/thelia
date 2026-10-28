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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Guardrail\CoreGuardrail;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Internal\AiAssert;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModelQuery;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiTierAssignmentQuery;
use Thelia\Model\AiUsageSetting;
use Thelia\Model\AiUsageSettingQuery;

/**
 * The one way to write the settings of the AI layer: connections, the model of each range, what the merchant
 * decided for a usage, and the global switches. The admin API, the back-office and the modules all come here, so
 * a rule is written once. A key goes through the vault and is never kept anywhere else. Every refusal is an
 * {@see AiExceptionInterface} whose message says what to fix; nothing is saved when one is raised.
 */
final readonly class AiSettingsWriter
{
    private const string CODE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(
        private AiKeyVault $vault,
        private AiBaseUrlValidator $baseUrlValidator,
        private AiUsageRegistryInterface $usages,
        private AiSettings $settings,
    ) {
    }

    /**
     * Creates the connection when none is given, updates it otherwise. A blank `$apiKey` keeps the stored key, as
     * long as the connection keeps its provider and its address: the stored key is never sent to a new server.
     *
     * @param string|null $jurisdiction "eu" or "non_eu", read only for a provider that cannot tell
     *
     * @throws AiExceptionInterface
     */
    public function saveConnection(
        ?AiConnection $connection,
        string $code,
        string $title,
        string $provider,
        ?string $jurisdiction,
        ?string $baseUrl,
        ?string $apiKey,
        ?string $apiKeyEnv,
        bool $allowPrivateNetwork,
        bool $active,
    ): AiConnection {
        $connection ??= new AiConnection();

        if (1 !== preg_match(self::CODE_PATTERN, $code)) {
            throw new InvalidAiArgumentException('The code is lower case letters, digits, "-" and "_", 64 characters at most.');
        }

        $taken = AiConnectionQuery::create()->filterByCode($code);

        if (null !== $connection->getId()) {
            $taken->filterById($connection->getId(), Criteria::NOT_EQUAL);
        }

        if ($taken->count() > 0) {
            throw new InvalidAiArgumentException('Another connection already has this code.');
        }

        if ('' === trim($title)) {
            throw new InvalidAiArgumentException('The title is required.');
        }

        $provider = AiProvider::tryFrom($provider)
            ?? throw new InvalidAiArgumentException(\sprintf('The provider must be one of: %s.', implode(', ', array_column(AiProvider::cases(), 'value'))));

        $baseUrl = null === $baseUrl || '' === trim($baseUrl) ? null : trim($baseUrl);

        if (null !== $baseUrl) {
            $this->baseUrlValidator->validate($baseUrl, $allowPrivateNetwork);
        }

        $jurisdiction = $provider->defaultJurisdiction($baseUrl)
            ?? AiJurisdiction::tryFrom((string) $jurisdiction)
            ?? throw new InvalidAiArgumentException('The jurisdiction ("eu" or "non_eu") is required for this provider.');

        $apiKeyEnv = null === $apiKeyEnv || '' === trim($apiKeyEnv) ? null : trim($apiKeyEnv);

        if (null !== $apiKeyEnv) {
            $this->vault->assertEnvironmentVariableName($apiKeyEnv);
        }

        if (null !== $connection->getId() && $connection->getProvider() !== $provider->value) {
            $this->assertNothingReliesOnTheProviderOf($connection, $provider);
        }

        $newKey = null === $apiKey || '' === trim($apiKey) ? null : trim($apiKey);
        $movesTheStoredKey = null !== $connection->getId()
            && null === $newKey
            && null !== $connection->getApiKeyEncrypted() && '' !== $connection->getApiKeyEncrypted()
            && ($connection->getBaseUrl() !== $baseUrl || $connection->getProvider() !== $provider->value);

        if ($movesTheStoredKey) {
            throw new InvalidAiArgumentException('The connection now points to another server: type its API key again, the saved key is not sent to a new address.');
        }

        $connection
            ->setCode($code)
            ->setTitle(trim($title))
            ->setProvider($provider->value)
            ->setJurisdiction($jurisdiction->value)
            ->setBaseUrl($baseUrl)
            ->setApiKeyEnv($apiKeyEnv)
            ->setAllowPrivateNetwork((int) $allowPrivateNetwork)
            ->setActive((int) $active);

        if (null !== $newKey) {
            $connection->setApiKeyEncrypted($this->vault->encrypt($newKey));
        }

        $connection->save();

        return $connection;
    }

    /**
     * Gives the range a model. The model must belong to the provider of the connection, and to the connection
     * itself when it is bound to one; a range has one assignment: pass the one it has to change it.
     *
     * @throws AiExceptionInterface
     */
    public function assignTier(?AiTierAssignment $assignment, string $tier, int $connectionId, int $modelId): AiTierAssignment
    {
        $assignment ??= new AiTierAssignment();

        $tier = AiTier::tryFrom($tier)
            ?? throw new InvalidAiArgumentException(\sprintf('The tier must be one of: %s.', implode(', ', array_column(AiTier::cases(), 'value'))));
        $connection = AiConnectionQuery::create()->findPk($connectionId)
            ?? throw new InvalidAiArgumentException('The connection does not exist.');
        $model = AiModelQuery::create()->findPk($modelId)
            ?? throw new InvalidAiArgumentException('The model does not exist.');

        if ($model->getProvider() !== $connection->getProvider() || (null !== $model->getConnectionId() && $model->getConnectionId() !== $connection->getId())) {
            throw new InvalidAiArgumentException('The model is not served by this connection.');
        }

        $taken = AiTierAssignmentQuery::create()->filterByTier($tier->value);

        if (null !== $assignment->getId()) {
            $taken->filterById($assignment->getId(), Criteria::NOT_EQUAL);
        }

        if ($taken->count() > 0) {
            throw new InvalidAiArgumentException('This tier already has an assignment: update it instead.');
        }

        $assignment->setTier($tier->value)->setConnectionId($connection->getId())->setModelId($model->getId())->save();

        return $assignment;
    }

    /**
     * Writes what the merchant decided for a usage a module declared: creates the row of the usage when none is
     * given, replaces its values otherwise. `$tier` null keeps the range the module declared. A range whose model
     * lacks a capability the usage declared is accepted here and refuses the calls (the usage shows as inactive).
     *
     * @param string|null           $monthlyBudget a non-negative decimal like "25.00", null for no budget of its own
     * @param array<string, string> $guardrails    the name of an optional guardrail to "block" or "annotate"
     *
     * @throws AiExceptionInterface
     */
    public function saveUsageSetting(
        ?AiUsageSetting $setting,
        string $usageId,
        bool $enabled = true,
        ?string $tier = null,
        ?string $monthlyBudget = null,
        string $currency = 'USD',
        ?int $requestsPerMinute = null,
        ?int $requestsPerDay = null,
        ?int $maxOutputTokens = null,
        ?int $maxInputCharacters = null,
        ?int $pinnedConnectionId = null,
        ?int $pinnedModelId = null,
        array $guardrails = [],
        bool $transparencyNotice = true,
        bool $provenanceMark = true,
        bool $storePrompt = false,
        ?int $promptRetentionDays = null,
    ): AiUsageSetting {
        $setting ??= new AiUsageSetting();
        $this->usages->get($usageId);

        $taken = AiUsageSettingQuery::create()->filterByUsageId($usageId);

        if (null !== $setting->getId()) {
            $taken->filterById($setting->getId(), Criteria::NOT_EQUAL);
        }

        if ($taken->count() > 0) {
            throw new InvalidAiArgumentException('This usage already has settings: update them instead.');
        }

        $tier = null === $tier || '' === $tier ? null : (AiTier::tryFrom($tier)
            ?? throw new InvalidAiArgumentException(\sprintf('The tier must be one of: %s.', implode(', ', array_column(AiTier::cases(), 'value')))));

        $budget = null === $monthlyBudget || '' === trim($monthlyBudget) ? null : trim($monthlyBudget);

        if (null !== $budget) {
            AiAssert::decimal($budget, 'The monthly budget');
        }

        $currency = strtoupper(trim($currency));
        AiAssert::currency($currency);

        $connection = null === $pinnedConnectionId ? null : (AiConnectionQuery::create()->findPk($pinnedConnectionId) ?? throw new InvalidAiArgumentException('The pinned connection does not exist.'));
        $model = null === $pinnedModelId ? null : (AiModelQuery::create()->findPk($pinnedModelId) ?? throw new InvalidAiArgumentException('The pinned model does not exist.'));

        if (null !== $connection && null !== $model && ($model->getProvider() !== $connection->getProvider() || (null !== $model->getConnectionId() && $model->getConnectionId() !== $connection->getId()))) {
            throw new InvalidAiArgumentException('The pinned model is not served by the pinned connection.');
        }

        $modes = [];

        foreach ($guardrails as $name => $mode) {
            AiAssert::token((string) $name, 'A guardrail name');

            if (CoreGuardrail::isReserved((string) $name)) {
                throw new InvalidAiArgumentException(\sprintf('"%s" is not the name of an optional guardrail.', $name));
            }

            if (!\is_string($mode) || null === GuardrailMode::tryFrom($mode)) {
                throw new InvalidAiArgumentException(\sprintf('The mode of the guardrail "%s" is "block" or "annotate".', $name));
            }

            $modes[(string) $name] = $mode;
        }

        $limits = [
            'The limit per minute' => $requestsPerMinute,
            'The limit per day' => $requestsPerDay,
            'The output limit' => $maxOutputTokens,
            'The input limit' => $maxInputCharacters,
            'The prompt retention' => $promptRetentionDays,
        ];

        foreach ($limits as $label => $value) {
            if (null !== $value && $value < 1) {
                throw new InvalidAiArgumentException($label.' must be a positive number.');
            }
        }

        $setting
            ->setUsageId($usageId)
            ->setEnabled((int) $enabled)
            ->setTier($tier?->value)
            ->setMonthlyBudget($budget)
            ->setCurrency($currency)
            ->setRequestsPerMinute($requestsPerMinute)
            ->setRequestsPerDay($requestsPerDay)
            ->setMaxOutputTokens($maxOutputTokens)
            ->setMaxInputCharacters($maxInputCharacters)
            ->setPinnedConnectionId($pinnedConnectionId)
            ->setPinnedModelId($pinnedModelId)
            ->setGuardrailMap($modes)
            ->setTransparencyNotice((int) $transparencyNotice)
            ->setProvenanceMark((int) $provenanceMark)
            ->setStorePrompt((int) $storePrompt)
            ->setPromptRetentionDays($promptRetentionDays)
            ->save();

        return $setting;
    }

    /**
     * A tier, a pinned usage or a model bound to the connection names a model of its current provider: under
     * another provider the connection would no longer serve it, and the usages on it would turn inactive. A usage
     * pinned to the connection without a model of its own runs the model of its tier there.
     *
     * @throws InvalidAiArgumentException
     */
    private function assertNothingReliesOnTheProviderOf(AiConnection $connection, AiProvider $provider): void
    {
        $modelIds = [];

        foreach (AiTierAssignmentQuery::create()->filterByConnectionId($connection->getId())->find() as $assignment) {
            $modelIds[] = $assignment->getModelId();
        }

        foreach (AiUsageSettingQuery::create()->filterByPinnedConnectionId($connection->getId())->find() as $setting) {
            $modelIds[] = $setting->getPinnedModelId() ?? $this->tierModelIdOf($setting);
        }

        $modelIds = array_filter($modelIds, static fn (?int $modelId): bool => null !== $modelId);
        $boundModels = AiModelQuery::create()->filterByConnectionId($connection->getId())->filterByProvider($provider->value, Criteria::NOT_EQUAL)->count();
        $usedModels = [] === $modelIds ? 0 : AiModelQuery::create()->filterById(array_values(array_unique($modelIds)), Criteria::IN)->filterByProvider($provider->value, Criteria::NOT_EQUAL)->count();

        if ($boundModels + $usedModels > 0) {
            throw new InvalidAiArgumentException('Tiers, usages or models rely on the provider of this connection: move them to another connection before changing its provider.');
        }
    }

    /**
     * The model of the tier a usage runs at: the tier chosen for it, else the one its module declares (only the
     * chosen one when the module is not installed).
     */
    private function tierModelIdOf(AiUsageSetting $setting): ?int
    {
        try {
            $tier = $setting->effectiveTier($this->usages->get((string) $setting->getUsageId())->tier);
        } catch (UnknownAiUsageException) {
            $tier = AiTier::tryFrom((string) $setting->getTier());
        }

        return null === $tier ? null : AiTierAssignmentQuery::create()->filterByTier($tier->value)->findOne()?->getModelId();
    }

    public function setEnabled(bool $enabled): void
    {
        $this->settings->setEnabled($enabled);
    }

    public function setMcpEnabled(bool $enabled): void
    {
        $this->settings->setMcpEnabled($enabled);
    }

    /**
     * @param string $cap a non-negative decimal like "25.00", '0' for no global cap
     *
     * @throws InvalidAiArgumentException when it is not a decimal
     */
    public function setMonthlyCap(string $cap): void
    {
        $this->settings->setMonthlyCap($cap);
    }

    public function setEuropeanUnionOnly(bool $europeanUnionOnly): void
    {
        $this->settings->setEuropeanUnionOnly($europeanUnionOnly);
    }
}
