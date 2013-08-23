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

namespace Thelia\Coupon;

use Thelia\Coupon\Parameter\PriceParam;
use Thelia\Coupon\Parameter\RuleValidator;
use Thelia\Coupon\Rule\AvailableForTotalAmount;
use Thelia\Coupon\Rule\CouponRuleInterface;
use Thelia\Coupon\Rule\Operators;
use Thelia\Exception\CouponExpiredException;
use Thelia\Model\Coupon;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test CouponFactory Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponFactoryTest extends \PHPUnit_Framework_TestCase
{

    CONST VALID_SHORT_DESCRIPTION = 'Coupon for Christmas removing 10€ if your total checkout is more than 40€';
    CONST VALID_DESCRIPTION = '<h3>Lorem ipsum dolor sit amet</h3>Consectetur adipiscing elit. Cras at luctus tellus. Integer turpis mauris, aliquet vitae risus tristique, pellentesque vestibulum urna. Vestibulum sodales laoreet lectus dictum suscipit. Praesent vulputate, sem id varius condimentum, quam magna tempor elit, quis venenatis ligula nulla eget libero. Cras egestas euismod tellus, id pharetra leo suscipit quis. Donec lacinia ac lacus et ultricies. Nunc in porttitor neque. Proin at quam congue, consectetur orci sed, congue nulla. Nulla eleifend nunc ligula, nec pharetra elit tempus quis. Vivamus vel mauris sed est dictum blandit. Maecenas blandit dapibus velit ut sollicitudin. In in euismod mauris, consequat viverra magna. Cras velit velit, sollicitudin commodo tortor gravida, tempus varius nulla.

Donec rhoncus leo mauris, id porttitor ante luctus tempus.
<script type="text/javascript">
    alert("I am an XSS attempt!");
</script>
Curabitur quis augue feugiat, ullamcorper mauris ac, interdum mi. Quisque aliquam lorem vitae felis lobortis, id interdum turpis mattis. Vestibulum diam massa, ornare congue blandit quis, facilisis at nisl. In tortor metus, venenatis non arcu nec, sollicitudin ornare nisl. Nunc erat risus, varius nec urna at, iaculis lacinia elit. Aenean ut felis tempus, tincidunt odio non, sagittis nisl. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec vitae hendrerit elit. Nunc sit amet gravida risus, euismod lobortis massa. Nam a erat mauris. Nam a malesuada lorem. Nulla id accumsan dolor, sed rhoncus tellus. Quisque dictum felis sed leo auctor, at volutpat lectus viverra. Morbi rutrum, est ac aliquam imperdiet, nibh sem sagittis justo, ac mattis magna lacus eu nulla.

Duis interdum lectus nulla, nec pellentesque sapien condimentum at. Suspendisse potenti. Sed eu purus tellus. Nunc quis rhoncus metus. Fusce vitae tellus enim. Interdum et malesuada fames ac ante ipsum primis in faucibus. Etiam tempor porttitor erat vitae iaculis. Sed est elit, consequat non ornare vitae, vehicula eget lectus. Etiam consequat sapien mauris, eget consectetur magna imperdiet eget. Nunc sollicitudin luctus velit, in commodo nulla adipiscing fermentum. Fusce nisi sapien, posuere vitae metus sit amet, facilisis sollicitudin dui. Fusce ultricies auctor enim sit amet iaculis. Morbi at vestibulum enim, eget adipiscing eros.

Praesent ligula lorem, faucibus ut metus quis, fermentum iaculis erat. Pellentesque elit erat, lacinia sed semper ac, sagittis vel elit. Nam eu convallis est. Curabitur rhoncus odio vitae consectetur pellentesque. Nam vitae arcu nec ante scelerisque dignissim vel nec neque. Suspendisse augue nulla, mollis eget dui et, tempor facilisis erat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi ac diam ipsum. Donec convallis dui ultricies velit auctor, non lobortis nulla ultrices. Morbi vitae dignissim ante, sit amet lobortis tortor. Nunc dapibus condimentum augue, in molestie neque congue non.

Sed facilisis pellentesque nisl, eu tincidunt erat scelerisque a. Nullam malesuada tortor vel erat volutpat tincidunt. In vehicula diam est, a convallis eros scelerisque ut. Donec aliquet venenatis iaculis. Ut a arcu gravida, placerat dui eu, iaculis nisl. Quisque adipiscing orci sit amet dui dignissim lacinia. Sed vulputate lorem non dolor adipiscing ornare. Morbi ornare id nisl id aliquam. Ut fringilla elit ante, nec lacinia enim fermentum sit amet. Aenean rutrum lorem eu convallis pharetra. Cras malesuada varius metus, vitae gravida velit. Nam a varius ipsum, ac commodo dolor. Phasellus nec elementum elit. Etiam vel adipiscing leo.';

    /**
     * Generate valid CouponInterface
     *
     * @param $code
     * @param $type
     * @param $title
     * @param $shortDescription
     * @param $description
     * @param $amount
     * @param $isUsed
     * @param $isEnabled
     * @param $expirationDate
     * @param $rules
     * @param $isCumulative
     * @param $isRemovingPostage
     *
     * @return CouponInterface
     */
    public function generateValidCoupon(
        $code = 'XMAS1',
        $type = '\Thelia\Coupon\Type\RemoveXAmount',
        $title = 'Christmas coupon',
        $shortDescription = self::VALID_SHORT_DESCRIPTION,
        $description = self::VALID_DESCRIPTION,
        $amount = 10.00,
        $isUsed = 1,
        $isEnabled = 1,
        $expirationDate = null,
        $rules = null,
        $isCumulative = 1,
        $isRemovingPostage = 0
    ) {
        $coupon = new Coupon();
        $coupon->setCode($code);
        $coupon->setType($type);
        $coupon->setTitle($title);
        $coupon->setShortDescription($shortDescription);
        $coupon->setDescription($description);
        $coupon->setAmount($amount);
        $coupon->setIsUsed($isUsed);
        $coupon->setIsEnabled($isEnabled);

        if ($expirationDate === null) {
            $date = new \DateTime();
            $coupon->setExpirationDate(
                $date->setTimestamp(strtotime("today + 2 months"))
            );
        }

        if ($rules === null) {
            $rules = $this->generateValidRules();
        }

        $couponFactory = new CouponFactory(new CouponBaseAdapter());
        $serializedData = $couponFactory->convertRulesInstancesIntoSerialized(
            $rules
        );

        $coupon->setSerializedRulesType($serializedData['rulesType']);
        $coupon->setSerializedRulesContent($serializedData['rulesContent']);

        $coupon->setIsCumulative($isCumulative);
        $coupon->setIsRemovingPostage($isRemovingPostage);

        return $coupon;
    }


    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }



    /**
     * Fake CouponQuery->findByCode
     *
     * @param string $code
     * @param string $type
     * @param string $title
     * @param string $shortDescription
     * @param string $description
     * @param float $amount
     * @param int $isUsed
     * @param int $isEnabled
     * @param null $expirationDate
     * @param null $rules
     * @param int $isCumulative
     * @param int $isRemovingPostage
     * @return Coupon
     */
    public function generateCouponModelMock(
        $code,
        $type,
        $title,
        $shortDescription,
        $description,
        $amount,
        $isUsed,
        $isEnabled,
        $expirationDate,
        $rules,
        $isCumulative,
        $isRemovingPostage
    ) {
        $coupon = $this->generateValidCoupon(
            $code,
            $type,
            $title,
            $shortDescription,
            $description,
            $amount,
            $isUsed,
            $isEnabled,
            $expirationDate,
            $rules,
            $isCumulative,
            $isRemovingPostage
        );

        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->getMock(
            'Thelia\Coupon\CouponBaseAdapter',
            array('findOneCouponByCode'),
            array()
        );
        $stubCouponBaseAdapter->expects($this->any())
            ->method('findOneCouponByCode')
            ->will($this->returnValue($coupon));

        return $stubCouponBaseAdapter;
    }



    /**
     * @covers Thelia\Coupon\CouponFactory::buildCouponFromCode
     * @expectedException Thelia\Exception\CouponExpiredException
     */
    public function testBuildCouponFromCodeExpiredDateBefore()
    {
        $date = new \DateTime();
        $date->setTimestamp(strtotime("today - 2 months"));

        /** @var CouponAdapterInterface $mockAdapter */
        $mockAdapter = $this->generateCouponModelMock(null, null, null, null, null, null, null, null, $date);
        $couponFactory = new CouponFactory($mockAdapter);
        $coupon = $couponFactory->buildCouponFromCode('XMAS1');
    }

    /**
     * @covers Thelia\Coupon\CouponFactory::buildCouponFromCode
     * @expectedException Thelia\Exception\CouponExpiredException
     */
    public function testBuildCouponFromCodeExpiredDateEquals()
    {
        $date = new \DateTime();

        /** @var CouponAdapterInterface $mockAdapter */
        $mockAdapter = $this->generateCouponModelMock(null, null, null, null, null, null, null, null, $date);
        $couponFactory = new CouponFactory($mockAdapter);
        $coupon = $couponFactory->buildCouponFromCode('XMAS1');
    }

    /**
     * @covers Thelia\Coupon\CouponFactory::buildCouponFromCode
     */
    public function testBuildCouponFromCode()
    {
        /** @var CouponAdapterInterface $mockAdapter */
        $mockAdapter = $this->generateCouponModelMock();
        $couponFactory = new CouponFactory($mockAdapter);
        $coupon = $couponFactory->buildCouponFromCode('XMAS1');

        $CouponManager = new CouponManager($mockAdapter)
    }

    /**
     * Generate valid CouponRuleInterfaces
     *
     * @return array Array of CouponRuleInterface
     */
    protected function generateValidRules()
    {
        $rule1 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::SUPERIOR,
                    new PriceParam(
                        40.00,
                        'EUR'
                    )
                )
            )
        );
        $rule2 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::INFERIOR,
                    new PriceParam(
                        400.00,
                        'EUR'
                    )
                )
            )
        );
        $rules = array($rule1, $rule2);

        return $rules;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
