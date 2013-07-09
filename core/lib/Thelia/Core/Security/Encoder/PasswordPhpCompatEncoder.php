<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
namespace Thelia\Core\Security\Encoder;

/**
 *
 * use password api include in php 5.5 and available throw the password_compat library.
 *
 * Class PasswordPhpCompatEncoder
 * @package Thelia\Core\Security\Encoder
 */
class PasswordPhpCompatEncoder implements PasswordEncoderInterface {

    /**
     * Encode a string.
     *
     * @param  string $password    the password to encode
     * @param  string $algorithm   the hash() algorithm
     * @return string    $salt        the salt, the salt is not used here.
     */
    public function encode($password, $algorithm, $salt = null)
    {
        return password_hash($password, $algorithm);
    }

    /**
     * Check a string against an encoded password.
     *
     * @param  string $string      the string to compare against password
     * @param  string $password    the encoded password
     * @param  string $algorithm   the hash() algorithm, not used here
     * @return string    $salt        the salt, not used here
     */
    public function isEqual($string, $password, $algorithm = null, $salt = null)
    {
        return password_verify($string, $password);
    }
}