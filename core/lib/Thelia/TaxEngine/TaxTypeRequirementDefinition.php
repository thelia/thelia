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
namespace Thelia\TaxEngine;

use Thelia\Type\TypeInterface;

/**
 * This class defines a Tax type requirement.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class TaxTypeRequirementDefinition
{
    /**
     * @var string The translated requirement title
     */
    protected $title;

    /**
     * Create a new Tax type requirement.
     *
     * @param string        $name the name of the requirement
     * @param TypeInterface $type the type of the data
     */
    public function __construct(protected $name, protected TypeInterface $type, $title = null)
    {
        $this->title = $title ?: $this->name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function isValueValid($value)
    {
        return $this->type->isValid($value);
    }
}
