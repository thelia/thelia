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

namespace Thelia\Tools;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Thelia\Core\Security\Exception\TokenAuthenticationException;

/**
 * Class TokenProvider
 * @package Thelia\Tools
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TokenProvider
{
    /**
     * @var string The stored token for this page
     */
    protected $token;

    /**
     * @var SessionInterface The session where the token is stored
     */
    protected $session;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var TranslatorInterface The translator
     */
    protected $translator;

    /**
     * @var string the current name of the token
     */
    protected $tokenName;

    /**
     * @param RequestStack $requestStack
     * @param TranslatorInterface $translator
     * @param $tokenName
     */
    public function __construct(RequestStack $requestStack, TranslatorInterface $translator, $tokenName)
    {
        /**
         * Store the services
         */
        $this->requestStack = $requestStack;
        $this->session = $this->requestStack->getCurrentRequest()->getSession();
        $this->translator = $translator;
        $this->tokenName = $tokenName;

        $this->token = $this->session->get($this->tokenName);
    }

    /**
     * @return string
     */
    public function assignToken()
    {
        if (null === $this->token) {
            $this->token = $this->getToken();

            $this->session->set($this->tokenName, $this->token);
        }

        return $this->token;
    }

    /**
     * @param $entryValue
     * @return bool
     * @throws \Thelia\Core\Security\Exception\TokenAuthenticationException
     */
    public function checkToken($entryValue)
    {
        if (null === $this->token) {
            throw new TokenAuthenticationException(
                "Tried to check a token without assigning it before"
            );
        } else {
            if ($this->token !== $entryValue) {
                throw new TokenAuthenticationException(
                    "Tried to validate an invalid token"
                );
            } else {
                $this->refreshToken();
            }
        }

        return true;
    }

    protected function refreshToken()
    {
        $this->token = null;
        $this->assignToken();
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return self::generateToken();
    }
    /**
     * Same method as getToken but can be called statically
     *
     * @alias getToken
     * @return string
     */
    public static function generateToken()
    {
        $raw = self::getOpenSSLRandom();
        if (false === $raw) {
            $raw = self::getComplexRandom();
        }
        return md5($raw);
    }

    /**
     * @param int $length
     * @return string
     */
    protected static function getOpenSSLRandom($length = 40)
    {
        if (!function_exists("openssl_random_pseudo_bytes")) {
            return false;
        }

        return openssl_random_pseudo_bytes($length);
    }

    /**
     * @return string
     */
    protected static function getComplexRandom()
    {
        $firstValue = (float) (mt_rand(1, 0xFFFF) * rand(1, 0x10001));
        $secondValues = (float) (rand(1, 0xFFFF) * mt_rand(1, 0x10001));

        return microtime() . ceil($firstValue / $secondValues) . uniqid();
    }
}
