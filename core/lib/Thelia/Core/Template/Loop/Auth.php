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
namespace Thelia\Core\Template\Loop;

use Exception;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\AlphaNumStringListType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method string[] getRole()
 * @method string[] getResource()
 * @method int[]    getModule()
 * @method string[] getAccess()
 */
class Auth extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
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
                    new EnumListType([AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE])
                )
            )
        );
    }

    public function buildArray(): array
    {
        return [];
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $roles = $this->getRole();
        $resource = $this->getResource();
        $module = $this->getModule();
        $access = $this->getAccess();

        try {
            if (true === $this->securityContext->isGranted(
                $roles,
                $resource ?? [],
                $module ?? [],
                $access ?? []
            )
            ) {
                // Create an empty row: loop is no longer empty :)
                $loopResult->addRow(new LoopResultRow());
            }
        } catch (Exception) {
            // Not granted, loop is empty
        }

        return $loopResult;
    }
}
