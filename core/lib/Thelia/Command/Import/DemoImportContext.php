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

namespace Thelia\Command\Import;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Model\Attribute;
use Thelia\Model\Brand;
use Thelia\Model\Category;
use Thelia\Model\Content;
use Thelia\Model\Customer;
use Thelia\Model\Feature;
use Thelia\Model\Folder;
use Thelia\Model\Product;
use Thelia\Model\Template;

/**
 * Shared state passed between demo importers. Producers register the entities
 * they create; downstream importers read them. Instantiated by the command,
 * never as a service (hence #[Exclude]).
 */
#[Exclude]
final class DemoImportContext
{
    /** @var array<string, Category> */
    public array $categoriesByTitle = [];

    /** @var array<string, Brand> */
    public array $brandsByTitle = [];

    /** @var array<string, Folder> */
    public array $foldersByTitle = [];

    /** @var array<string, Content> */
    public array $contentsByTitle = [];

    /** @var list<Product> */
    public array $products = [];

    /** @var list<Customer> */
    public array $customers = [];

    private ?Template $template = null;

    private ?Attribute $colorsAttribute = null;

    private ?Feature $materialsFeature = null;

    public function __construct(
        public readonly ConnectionInterface $connection,
        public readonly OutputInterface $output,
        public readonly bool $withImages,
        public readonly bool $deterministic,
        public readonly string $dataDir,
        public readonly string $imagesDir,
    ) {
    }

    public function setTemplate(Template $template): void
    {
        $this->template = $template;
    }

    public function template(): Template
    {
        return $this->template ?? throw new \LogicException('Demo template requested before it was imported — check importer priorities.');
    }

    public function setColorsAttribute(Attribute $attribute): void
    {
        $this->colorsAttribute = $attribute;
    }

    public function colorsAttribute(): Attribute
    {
        return $this->colorsAttribute ?? throw new \LogicException('Colors attribute requested before it was imported — check importer priorities.');
    }

    public function setMaterialsFeature(Feature $feature): void
    {
        $this->materialsFeature = $feature;
    }

    public function materialsFeature(): Feature
    {
        return $this->materialsFeature ?? throw new \LogicException('Materials feature requested before it was imported — check importer priorities.');
    }
}
