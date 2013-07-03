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

namespace Thelia\Core\Security\Token;

/**
 * TokenInterface is the interface for the user authentication information.
 *
 * Parts borrowed from Symfony Security Framework (Fabien Potencier <fabien@symfony.com> / Johannes M. Schmitt <schmittjoh@gmail.com>)
 */

interface TokenInterface extends \Serializable
{
	/**
	 * Returns the user credentials.
	 *
	 * @return mixed The user credentials
	*/
	public function getCredentials();

	/**
	 * Returns a user representation.
	 *
	 * @return mixed either returns an object which implements __toString(), or
	 * a primitive string is returned.
	*/
	public function getUser();

	/**
	 * Sets a user instance
	 *
	 * @param mixed $user
	*/
	public function setUser($user);

	/**
	 * Returns the username.
	 *
	 * @return string
	*/
	public function getUsername();

	/**
	 * Returns whether the user is authenticated or not.
	 *
	 * @return Boolean true if the token has been authenticated, false otherwise
	*/
	public function isAuthenticated();

	/**
	 * Sets the authenticated flag.
	 *
	 * @param Boolean $isAuthenticated The authenticated flag
	*/
	public function setAuthenticated($isAuthenticated);

	/**
	 * Removes sensitive information from the token.
	*/
	public function eraseCredentials();
}