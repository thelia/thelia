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

namespace Thelia\Domain\Ai\Tool\Internal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiReadToolInterface;
use Thelia\Domain\Ai\Tool\AiToolDescriptor;
use Thelia\Domain\Ai\Tool\AiToolEffect;
use Thelia\Domain\Ai\Tool\AiWriteToolInterface;
use Thelia\Domain\Ai\Tool\AsAiTool;

/**
 * A tool class and its #[AsAiTool], checked against the rules of the declaration. The compilation pass builds
 * one for every tool service, so that a declaration that breaks a rule stops the build; the registry builds
 * the same objects at run time.
 *
 * @internal
 */
#[Exclude]
final readonly class AiToolDeclaration
{
    /**
     * @param class-string $class
     * @param string       $module the root namespace of the class: the code of the module that declares the tool, `Thelia` for the core
     */
    private function __construct(
        public AsAiTool $attribute,
        public string $class,
        public AiToolEffect $effect,
        public string $module,
    ) {
    }

    /**
     * @param class-string $class
     *
     * @throws InvalidAiArgumentException when the declaration breaks a rule
     */
    public static function fromClass(string $class): self
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsAiTool::class);

        if ([] === $attributes) {
            throw new InvalidAiArgumentException(\sprintf('"%s" is a tool but carries no #[AsAiTool]: declare its name, description and right.', $class));
        }

        try {
            $attribute = $attributes[0]->newInstance();
        } catch (InvalidAiArgumentException $invalid) {
            throw new InvalidAiArgumentException(\sprintf('Invalid #[AsAiTool] on "%s": %s', $class, $invalid->getMessage()), 0, $invalid);
        }

        $module = explode('\\', ltrim($class, '\\'), 2)[0];
        $declaration = new self($attribute, $class, self::effectOf($reflection), $module);
        $declaration->assertRules();

        return $declaration;
    }

    public function name(): string
    {
        return $this->attribute->name;
    }

    public function prefix(): string
    {
        return strtolower($this->module);
    }

    public function describe(): AiToolDescriptor
    {
        return new AiToolDescriptor(
            $this->attribute->name,
            $this->attribute->description,
            $this->module,
            $this->effect,
            $this->attribute->resource,
            $this->attribute->module,
            $this->attribute->access,
            array_map(static fn (AiAudience $audience): string => $audience->value, $this->attribute->audiences),
            $this->attribute->risks,
            $this->attribute->mcp,
        );
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private static function effectOf(\ReflectionClass $reflection): AiToolEffect
    {
        $reads = $reflection->implementsInterface(AiReadToolInterface::class);
        $writes = $reflection->implementsInterface(AiWriteToolInterface::class);

        if ($reads === $writes) {
            throw new InvalidAiArgumentException(\sprintf('"%s" implements %s: a tool either reads (AiReadToolInterface) or writes (AiWriteToolInterface), never both and never neither.', $reflection->getName(), $reads ? 'both interfaces' : 'neither of the tool interfaces'));
        }

        return $writes ? AiToolEffect::Write : AiToolEffect::Read;
    }

    private function assertRules(): void
    {
        $name = $this->attribute->name;
        $prefix = $this->prefix();

        if (!str_starts_with($name, $prefix.'_')) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" of "%s" must start with "%s_", the code of its module in lower case%s.', $name, $this->class, $prefix, 'thelia' === $prefix ? '' : ' ("thelia_" is reserved to the core)'));
        }

        if ($this->attribute->isForAdministrators()) {
            $this->assertRight();
        }

        if (AiToolEffect::Write === $this->effect) {
            if (!\in_array($this->attribute->access, [AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE], true)) {
                throw new InvalidAiArgumentException(\sprintf('The tool "%s" changes the shop: its access is CREATE, UPDATE or DELETE, "%s" given.', $name, $this->attribute->access));
            }

            if ([AiAudience::Admin] !== $this->attribute->audiences) {
                throw new InvalidAiArgumentException(\sprintf('The tool "%s" changes the shop: it is offered to the audience "admin" only.', $name));
            }
        } elseif (AccessManager::VIEW !== $this->attribute->access) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" only reads: its access is VIEW, "%s" given.', $name, $this->attribute->access));
        }
    }

    private function assertRight(): void
    {
        $name = $this->attribute->name;
        $resource = $this->attribute->resource;

        if (null !== $resource && !\in_array($resource, self::adminResources(), true)) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" names the resource "%s", which is not a constant of AdminResources: use one, or declare `module` for the right of your module.', $name, $resource));
        }

        if (null !== $this->attribute->module && $this->attribute->module !== $this->module) {
            throw new InvalidAiArgumentException(\sprintf('The tool "%s" names the module "%s" but is declared by the module "%s": a tool can only be guarded by the right of its own module.', $name, $this->attribute->module, $this->module));
        }
    }

    /**
     * @return list<string> the values of the constants of AdminResources that name a resource
     */
    private static function adminResources(): array
    {
        $values = [];

        foreach ((new \ReflectionClass(AdminResources::class))->getConstants() as $value) {
            if (\is_string($value) && str_starts_with($value, 'admin.')) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
