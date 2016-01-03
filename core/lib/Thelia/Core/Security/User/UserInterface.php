<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Security\User;

/**
 * This interface should be implemented by user classes
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
interface UserInterface
{
    /**
     * Return the user unique ID
     */
    public function getId();

    /**
     * Return the user unique name
     */
    public function getUsername();

    /**
     * Return the user encoded password
     */
    public function getPassword();

    /**
     * Check a string against a the user password
     */
    public function checkPassword($password);

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('USER');
     * }
     * </code>
     *
     * @return Role[] The user roles
     */
    public function getRoles();

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     *
     * @return void
     */
    public function eraseCredentials();

    /**
     * return the user token (used by remember me authnetication system)
     */
    public function getToken();

    /**
     * Set a token in the user data (used by remember me authnetication system)
     */
    public function setToken($token);

    /**
     * return the user serial  (used by remember me authnetication system)
     */
    public function getSerial();

    /**
     * Set a serial number int the user data  (used by remember me authnetication system)
     */
    public function setSerial($serial);
}
