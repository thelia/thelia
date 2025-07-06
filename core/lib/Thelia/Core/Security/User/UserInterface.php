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

namespace Thelia\Core\Security\User;

/**
 * This interface should be implemented by user classes.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
interface UserInterface
{
    /**
     * Return the user unique ID.
     */
    public function getId(): int;

    /**
     * Return the user unique name.
     */
    public function getUsername(): string;

    /**
     * Return the user encoded password.
     */
    public function getPassword(): string;

    /**
     * Check a string against a the user password.
     */
    public function checkPassword(string $password): bool;

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('USER');
     * }
     * </code>
     */
    public function getRoles();

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials(): void;

    /**
     * return the user token (used by remember me authnetication system).
     */
    public function getToken(): string;

    /**
     * Set a token in the user data (used by remember me authnetication system).
     */
    public function setToken(string $token);

    /**
     * return the user serial  (used by remember me authnetication system).
     */
    public function getSerial(): string;

    /**
     * Set a serial number int the user data  (used by remember me authnetication system).
     */
    public function setSerial(string $serial);

    /**
     * Get the user preferred locale.
     *
     * @return string the locale
     */
    public function getLocale(): string;
}
