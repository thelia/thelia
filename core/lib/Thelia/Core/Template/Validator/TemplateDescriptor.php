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

namespace Thelia\Core\Template\Validator;

use Thelia\Core\Template\TemplateDefinition;

/**
 * Class TemplateDescriptor.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class TemplateDescriptor
{
    protected array $languages = [];
    protected array $descriptives = [];
    protected string $theliaVersion;
    protected string $version;
    protected ?TemplateDefinition $parent = null;
    protected string $documentation;
    protected string $stability;
    protected array $authors = [];
    protected string $assets = '';

    /**
     * TemplateDescriptor constructor.
     */
    public function __construct(
        /** @var string the template directory name (e.g. 'default') */
        protected string $name,
        /** @var int the template type (front, back, pdf) */
        protected int $type,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return $this
     */
    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @return $this
     */
    public function setLanguages(array $languages): self
    {
        $this->languages = $languages;

        return $this;
    }

    public function getDescriptives(): array
    {
        return $this->descriptives;
    }

    /**
     * @return $this
     */
    public function setDescriptives(array $descriptives): self
    {
        $this->descriptives = $descriptives;

        return $this;
    }

    public function getTheliaVersion(): string
    {
        return $this->theliaVersion;
    }

    /**
     * @return $this
     */
    public function setTheliaVersion(string $theliaVersion): self
    {
        $this->theliaVersion = $theliaVersion;

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return $this
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getParent(): ?TemplateDefinition
    {
        return $this->parent;
    }

    /**
     * @return $this
     */
    public function setParent(?TemplateDefinition $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function hasParent(): bool
    {
        return $this->parent instanceof TemplateDefinition;
    }

    public function getDocumentation(): string
    {
        return $this->documentation;
    }

    /**
     * @return $this
     */
    public function setDocumentation(string $documentation): self
    {
        $this->documentation = $documentation;

        return $this;
    }

    public function getStability(): string
    {
        return $this->stability;
    }

    /**
     * @return $this
     */
    public function setStability(string $stability): self
    {
        $this->stability = $stability;

        return $this;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    /**
     * @return $this
     */
    public function setAuthors(array $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    public function getAssets(): string
    {
        return $this->assets;
    }

    /**
     * @return $this
     */
    public function setAssets(string $assets): self
    {
        $this->assets = $assets;

        return $this;
    }
}
