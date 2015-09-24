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

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Type\AlphaNumStringListType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * {@inheritdoc}
 * @method string[] getRole()
 * @method string[] getResource()
 * @method int[] getModule()
 * @method string[] getAccess()
 */
class Auth extends BaseLoop implements ArraySearchLoopInterface
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            new Argument(
                'role',
                new TypeCollection(
                    new AlphaNumStringListType()
                ),
                null,
                true
            ),
            new Argument(
                'resource',
                new TypeCollection(
                    new AlphaNumStringListType()
                )
            ),
            new Argument(
                'module',
                new TypeCollection(
                    new AlphaNumStringListType()
                )
            ),
            new Argument(
                'access',
                new TypeCollection(
                    new EnumListType(array(AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE))
                )
            )
        );
    }

    public function buildArray()
    {
        return array();
    }

    public function parseResults(LoopResult $loopResult)
    {
        $roles = $this->getRole();
        $resource = $this->getResource();
        $module = $this->getModule();
        $access = $this->getAccess();

        try {
            if (true === $this->securityContext->isGranted(
                $roles,
                $resource === null ? array() : $resource,
                $module === null ? array() : $module,
                $access === null ? array() : $access
            )
            ) {
                // Create an empty row: loop is no longer empty :)
                $loopResult->addRow(new LoopResultRow());
            }
        } catch (\Exception $ex) {
            // Not granted, loop is empty
        }

        return $loopResult;
    }
}
