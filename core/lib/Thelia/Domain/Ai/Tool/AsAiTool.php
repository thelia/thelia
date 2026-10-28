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

namespace Thelia\Domain\Ai\Tool;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Core\Security\AccessManager;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * Declares a tool the AI layer and the MCP server of the core can offer: put it on a service that implements
 * AiReadToolInterface (reads) or AiWriteToolInterface (changes the shop, only once an administrator approves).
 *
 * The core finds it on every autoconfigured service, those of a module included, and checks the declaration
 * when the container is built: a declaration that breaks a rule stops the build with the reason.
 *
 *     #[AsAiTool(
 *         name: 'commerceagents_search_products',
 *         description: 'Searches the catalogue by reference or title.',
 *         resource: AdminResources::PRODUCT,
 *         access: AccessManager::VIEW,
 *         risks: [AiToolRisk::PrivateData],
 *     )]
 *     final readonly class SearchProductsTool implements AiReadToolInterface { ... }
 *
 * Rules:
 *  - `name` is `<module>_<action>` in lower case, at most 64 characters; the prefix is the code of the module
 *    that declares the tool in lower case (`CommerceAgents\` gives `commerceagents_`); `thelia_` is reserved
 *    to the core; the name is unique in the shop.
 *  - An administrator tool names the right it needs, once: a `resource` that is a constant of AdminResources,
 *    or the `module` that declares it, with the `access` it asks for. The right is the one of the administrator
 *    the call is made for, read again at each call. A tool without a right would be open to every administrator.
 *  - A tool that changes the shop asks for CREATE, UPDATE or DELETE and is offered to administrators only;
 *    a tool that reads asks for VIEW.
 *  - A tool for customers or visitors reads public data only: no right, no `private_data` risk.
 */
#[Exclude]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsAiTool
{
    /** At most 64 characters, `<module>_<action>`, lower case: the limit of the providers. */
    public const string NAME_PATTERN = '/^(?=.{1,64}$)[a-z][a-z0-9]{0,30}_[a-z0-9_]{1,40}$/';

    public const int DEFAULT_MAXIMUM_RESULT_BYTES = 16384;

    public const int MAXIMUM_RESULT_BYTES = 262144;

    /**
     * @param list<AiAudience> $audiences      who may be offered the tool: the audience of the usage that asks must be listed
     * @param string|null      $resource       the AdminResources constant that guards the tool (`admin.product`)
     * @param string|null      $module         the code of the declaring module, when it guards the tool with its own right
     * @param string           $access         AccessManager::VIEW, CREATE, UPDATE or DELETE
     * @param list<AiToolRisk> $risks          the legs of the lethal trifecta the tool brings
     * @param bool             $mcp            false keeps the tool away from external clients
     * @param int              $maxResultBytes the size of the JSON a result may take before it is refused, at most 262 144
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $audiences = [AiAudience::Admin],
        public ?string $resource = null,
        public ?string $module = null,
        public string $access = AccessManager::VIEW,
        public array $risks = [],
        public bool $mcp = true,
        public int $maxResultBytes = self::DEFAULT_MAXIMUM_RESULT_BYTES,
    ) {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidAiArgumentException(\sprintf('The tool name "%s" must be "<module>_<action>" in lower case with at most 64 characters, like "commerceagents_search_products".', $name));
        }

        AiAssert::notBlank($description, \sprintf('The description of the tool "%s"', $name));

        if (mb_strlen($description) > 2048) {
            throw new InvalidAiArgumentException(\sprintf('The description of the tool "%s" holds at most 2048 characters.', $name));
        }

        AiAssert::listOf($audiences, AiAudience::class, \sprintf('The audiences of the tool "%s"', $name));
        AiAssert::listOf($risks, AiToolRisk::class, \sprintf('The risks of the tool "%s"', $name));

        if ([] === $audiences || \count($audiences) !== \count(array_unique(array_map(static fn (AiAudience $audience): string => $audience->value, $audiences)))) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" lists at least one audience, each once.', $name));
        }

        if (\in_array(AiAudience::System, $audiences, true)) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" cannot be offered to the audience "system": a job has no right of its own, it acts for the administrator who configured it.', $name));
        }

        if (\count($risks) !== \count(array_unique(array_map(static fn (AiToolRisk $risk): string => $risk->value, $risks)))) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" repeats a risk.', $name));
        }

        if (!\in_array($access, [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE], true)) {
            throw new InvalidAiArgumentException(\sprintf('The access of the tool "%s" is VIEW, CREATE, UPDATE or DELETE, "%s" given.', $name, $access));
        }

        if ($maxResultBytes < 1 || $maxResultBytes > self::MAXIMUM_RESULT_BYTES) {
            throw new InvalidAiArgumentException(\sprintf('The result of the tool "%s" is limited to 1..%d bytes, %d given.', $name, self::MAXIMUM_RESULT_BYTES, $maxResultBytes));
        }

        $this->assertRight();
    }

    public function isForAdministrators(): bool
    {
        return \in_array(AiAudience::Admin, $this->audiences, true);
    }

    private function assertRight(): void
    {
        if ('' === $this->resource || '' === $this->module) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" declares an empty right: an empty resource is skipped by the access check, name a resource or a module, or none.', $this->name));
        }

        if (null !== $this->resource && null !== $this->module) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" declares a resource and a module: name one of them.', $this->name));
        }

        if (!$this->isForAdministrators()) {
            if (null !== $this->resource || null !== $this->module) {
                throw new InvalidAiArgumentException(\sprintf('The tool "%s" is offered to customers or visitors: it reads public data and declares no right.', $this->name));
            }

            if (\in_array(AiToolRisk::PrivateData, $this->risks, true)) {
                throw new InvalidAiArgumentException(\sprintf('The tool "%s" is offered to customers or visitors and returns private data: offer it to administrators only.', $this->name));
            }

            return;
        }

        if ([AiAudience::Admin] !== $this->audiences) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" mixes the audience "admin", which needs a right, with an audience that has none: declare two tools.', $this->name));
        }

        if (null === $this->resource && null === $this->module) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" is for administrators and declares no right: name a resource (AdminResources) or a module. Without one, every administrator would be allowed.', $this->name));
        }
    }
}
