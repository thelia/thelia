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

namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Product;

class ProductCloneEvent extends ActionEvent
{
    protected Product $clonedProduct;
    protected array $types = ['images', 'documents'];

    /**
     * Documents copied for the clone, keyed by the id of the source document.
     * Filled while the files are cloned, so later steps of the cloning process
     * can point the clone at its own copy of a document instead of the source one.
     *
     * @var array<int, int>
     */
    protected array $clonedDocumentIds = [];

    /**
     * ProductCloneEvent constructor.
     *
     * @param string $lang the locale (such as fr_FR)
     */
    public function __construct(protected string $ref, protected string $lang, protected Product $originalProduct)
    {
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): void
    {
        $this->ref = $ref;
    }

    /**
     * @return string the locale (such as fr_FR)
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @param string $lang the locale (such as fr_FR)
     */
    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    public function getOriginalProduct(): Product
    {
        return $this->originalProduct;
    }

    public function setOriginalProduct(Product $originalProduct): void
    {
        $this->originalProduct = $originalProduct;
    }

    public function getClonedProduct(): Product
    {
        return $this->clonedProduct;
    }

    public function setClonedProduct(Product $clonedProduct): void
    {
        $this->clonedProduct = $clonedProduct;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function addClonedDocumentId(int $originalDocumentId, int $clonedDocumentId): void
    {
        $this->clonedDocumentIds[$originalDocumentId] = $clonedDocumentId;
    }

    public function getClonedDocumentId(int $originalDocumentId): ?int
    {
        return $this->clonedDocumentIds[$originalDocumentId] ?? null;
    }
}
