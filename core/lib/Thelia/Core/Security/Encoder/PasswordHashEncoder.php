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
 * This interface defines a hash based password encoder.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */

class PasswordHashEncoder implements PasswordEncoderInterface {

   /**
    * {@inheritdoc}
    */
    public function encode($password, $algorithm, $salt)
	{
	    if (!in_array($algorithm, hash_algos(), true)) {
	    	throw new \LogicException(sprintf('The algorithm "%s" is not supported.', $algorithm));
	    }

	    // Salt the string
	    $salted = $password.$salt;

	    // Create the hash
	    $digest = hash($algorithm, $salted, true);

	    // "stretch" hash
	    for ($i = 1; $i < 5000; $i++) {
	    	$digest = hash($algorithm, $digest.$salted, true);
	    }

	    return base64_encode($digest);
	}

   /**
    * {@inheritdoc}
    */
	public function isEqual($string, $password, $algorithm, $salt)
	{
	    $encoded = $this->encode($password, $algorithm, $salt);

	    return $encoded == $string;
	}
}