<?php

namespace Thelia\Core\Security\User;

/**
 * This interface should be implemented by user classes
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
interface UserInterface {

    /**
     * Return the user unique name
     */
    public function getUsername();

    /**
     * Return the user encoded password
     */
    public function getPassword();

    /**
     * return the salt used to calculate the user password
     */
    public function getSalt();

    /**
     * return the algorithm used to calculate the user password
     */
    public function getAlgo();

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     *
     * @return void
     */
    public function eraseCredentials();
}