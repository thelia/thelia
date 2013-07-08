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
 * This interface defines a password encoder.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
interface PasswordEncoderInterface {

	/**
	 * Encode a string.
	 *
	 * @param  string    $password    the password to encode
	 * @param  string    $algorithm   the hash() algorithm
	 * @return string    $salt        the salt
	 */
	public function encode($password, $algorithm, $salt);

	/**
	 * Check a string against an encoded password.
	 *
	 * @param  string    $string      the string to compare against password
	 * @param  string    $password    the encoded password
	 * @param  string    $algorithm   the hash() algorithm
	 * @return string    $salt        the salt
	 */
	public function isEqual($string, $password, $algorithm, $salt);
}
