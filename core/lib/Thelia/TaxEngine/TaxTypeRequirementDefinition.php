<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\TaxEngine;

use Thelia\Type\TypeInterface;

/**
 * This class defines a Tax type requirement
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class TaxTypeRequirementDefinition
{
    /**
     * @var string The requirement name
     */
    protected $name;

    /**
     * @var TypeInterface The requirement type
     */
    protected $type;

    /**
     * Create a new Tax type requirement
     *
     * @param string        $name the name of the requirement
     * @param TypeInterface $type the type of the data
     */
    public function __construct($name, TypeInterface $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function isValueValid($value)
    {
        return $this->type->isValid($value);
    }
}
