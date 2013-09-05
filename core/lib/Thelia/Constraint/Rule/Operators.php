<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Constraint\Rule;

use Symfony\Component\Translation\Translator;
use Thelia\Constraint\Validator\ComparableInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represent available Operations in rule checking
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class Operators
{
    /** Param1 is inferior to Param2 */
    CONST INFERIOR          =    '<';
    /** Param1 is inferior to Param2 */
    CONST INFERIOR_OR_EQUAL =    '<=';
    /** Param1 is equal to Param2 */
    CONST EQUAL             =     '==';
    /** Param1 is superior to Param2 */
    CONST SUPERIOR_OR_EQUAL =     '>=';
    /** Param1 is superior to Param2 */
    CONST SUPERIOR          =     '>';
    /** Param1 is different to Param2 */
    CONST DIFFERENT         =     '!=';

    /**
     * Check if a parameter is valid against a ComparableInterface from its operator
     *
     * @param mixed               $a        Parameter to validate
     * @param string              $operator Operator to validate against
     * @param ComparableInterface $b        Comparable  to validate against
     *
     * @return bool
     */
    public static function isValid($a, $operator, ComparableInterface $b)
    {
        $ret = false;

        try {
            $comparison = $b->compareTo($a);
        } catch (\Exception $e) {
            return false;
        }

        switch ($operator) {
        case self::INFERIOR:
            if ($comparison == 1) {
                return true;
            }
            break;
        case self::INFERIOR_OR_EQUAL:
            if ($comparison == 1 || $comparison == 0) {
                return true;
            }
            break;
        case self::EQUAL:
            if ($comparison == 0) {
                return true;
            }
            break;
        case self::SUPERIOR_OR_EQUAL:
            if ($comparison == -1 || $comparison == 0) {
                return true;
            }
            break;
        case self::SUPERIOR:
            if ($comparison == -1) {
                return true;
            }
            break;
        case self::DIFFERENT:
            if ($comparison != 0) {
                return true;
            }
            break;
        default:
        }

        return $ret;
    }

    /**
     * Get operator translation
     *
     * @param Translator $translator Provide necessary value from Thelia
     * @param string     $operator   Operator const
     *
     * @return string
     */
    public static function getI18n(Translator $translator, $operator)
    {
        $ret = $operator;
        switch ($operator) {
        case self::INFERIOR:
            $ret = $translator->trans(
                'inferior to',
                array(),
                'constraint'
            );
            break;
        case self::INFERIOR_OR_EQUAL:
            $ret = $translator->trans(
                'inferior or equals to',
                array(),
                'constraint'
            );
            break;
        case self::EQUAL:
            $ret = $translator->trans(
                'equals to',
                array(),
                'constraint'
            );
            break;
        case self::SUPERIOR_OR_EQUAL:
            $ret = $translator->trans(
                'superior or equals to',
                array(),
                'constraint'
            );
            break;
        case self::SUPERIOR:
            $ret = $translator->trans(
                'superior to',
                array(),
                'constraint'
            );
            break;
        case self::DIFFERENT:
            $ret = $translator->trans(
                'different from',
                array(),
                'constraint'
            );
            break;
        default:
        }

        return $ret;
    }
}