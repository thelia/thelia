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

namespace Thelia\Condition;

use Thelia\Core\Translation\Translator;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represent available Operations in condition checking
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
    /** Param1 is in Param2 */
    CONST IN                =     'in';
    /** Param1 is not in Param2 */
    CONST OUT               =     'out';

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
                'condition'
            );
            break;
        case self::INFERIOR_OR_EQUAL:
            $ret = $translator->trans(
                'inferior or equal to',
                array(),
                'condition'
            );
            break;
        case self::EQUAL:
            $ret = $translator->trans(
                'equal to',
                array(),
                'condition'
            );
            break;
        case self::SUPERIOR_OR_EQUAL:
            $ret = $translator->trans(
                'superior or equal to',
                array(),
                'condition'
            );
            break;
        case self::SUPERIOR:
            $ret = $translator->trans(
                'superior to',
                array(),
                'condition'
            );
            break;
        case self::DIFFERENT:
            $ret = $translator->trans(
                'different from',
                array(),
                'condition'
            );
            break;
        case self::IN:
            $ret = $translator->trans(
                'in',
                array(),
                'condition'
            );
            break;
        case self::OUT:
            $ret = $translator->trans(
                'not in',
                array(),
                'condition'
            );
            break;
        default:
        }

        return $ret;
    }
}