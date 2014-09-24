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

namespace Thelia\Core\Security;

/**
 * A simple security manager, in charge of checking user
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AccessManager
{
    const VIEW = 'VIEW';
    const CREATE = 'CREATE';
    const UPDATE = 'UPDATE';
    const DELETE = 'DELETE';

    protected $accessGranted = array(
        self::VIEW      =>  false,
        self::CREATE    =>  false,
        self::UPDATE    =>  false,
        self::DELETE    =>  false,
    );

    protected static $accessPows = array(
        self::VIEW      =>  3,
        self::CREATE    =>  2,
        self::UPDATE    =>  1,
        self::DELETE    =>  0,
    );

    protected $accessValue;

    public function __construct($accessValue)
    {
        $this->accessValue = $accessValue;

        $this->fillGrantedAccess();
    }

    public function can($type)
    {
        if (!array_key_exists($type, $this->accessGranted)) {
            return false;
        }

        return $this->accessGranted[$type];
    }

    public static function getMaxAccessValue()
    {
        return pow(2, current(array_slice(self::$accessPows, -1, 1, true))) - 1;
    }

    public function build($accesses)
    {
        $this->accessValue = 0;
        foreach ($accesses as $access) {
            if (array_key_exists($access, self::$accessPows)) {
                $this->accessValue += pow(2, self::$accessPows[$access]);
            }
        }

        $this->fillGrantedAccess();
    }

    protected function fillGrantedAccess()
    {
        $accessValue = $this->accessValue;
        foreach (self::$accessPows as $type => $value) {
            $pow = pow(2, $value);
            if ($accessValue >= $pow) {
                $accessValue -= $pow;
                $this->accessGranted[$type] = true;
            } else {
                $this->accessGranted[$type] = false;
            }
        }
    }

    public function getAccessValue()
    {
        return $this->accessValue;
    }
}
