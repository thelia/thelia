<?php

namespace Thelia\Core\Template;

use Imagine\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @mixin ParserInterface
 */
trait ParserTemplateTrait
{
    protected array $tplStack = [];
    protected ?TemplateDefinition $templateDefinition = null;
    protected bool $fallbackToDefaultTemplate = false;
    protected int $status = 200;
    protected TemplateHelperInterface $templateHelper;

    #[Required]
    public RequestStack $requestStack;

    public function getRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        if (null !== $this->templateDefinition) {
            $this->tplStack[] = [$this->templateDefinition, $this->fallbackToDefaultTemplate];
        }

        $this->setTemplateDefinition($templateDefinition, $fallbackToDefaultTemplate);
    }

    public function popTemplateDefinition(): void
    {
        if (\count($this->tplStack) > 0) {
            [$templateDefinition, $fallbackToDefaultTemplate] = array_pop($this->tplStack);

            $this->setTemplateDefinition($templateDefinition, $fallbackToDefaultTemplate);
        }
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false): void
    {
        if (true === $unshift && isset($this->templateDirectories[$templateType][$templateName])) {
            // When using array_merge, the key was set to 0. Use + instead.
            $this->templateDirectories[$templateType][$templateName] =
                [$key => $templateDirectory] + $this->templateDirectories[$templateType][$templateName]
            ;
        } else {
            $this->templateDirectories[$templateType][$templateName][$key] = $templateDirectory;
        }
    }

    public function getTemplateDefinition($webAssetTemplateName = false): ?TemplateDefinition
    {
        return $this->templateDefinition;
    }

    public function hasTemplateDefinition(): bool
    {
        return $this->templateDefinition !== null;
    }

    public function getFallbackToDefaultTemplate(): bool
    {
        return $this->fallbackToDefaultTemplate;
    }

    public function getTemplateDirectories($templateType)
    {
        if (!isset($this->templateDirectories[$templateType])) {
            throw new InvalidArgumentException('Failed to get template type %', $templateType);
        }

        return $this->templateDirectories[$templateType];
    }

    public function getTemplateHelper(): TemplateHelperInterface
    {
        return $this->templateHelper;
    }
}
