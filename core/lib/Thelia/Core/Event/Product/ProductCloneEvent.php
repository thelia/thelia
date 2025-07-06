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



    /** @var Product */
    protected $clonedProduct;

    /** @var array */
    protected $types = ['images', 'documents'];

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

    /**
     * @return Product
     */
    public function getClonedProduct()
    {
        return $this->clonedProduct;
    }

    /**
     * @param Product $clonedProduct
     */
    public function setClonedProduct($clonedProduct): void
    {
        $this->clonedProduct = $clonedProduct;
    }

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }
}
