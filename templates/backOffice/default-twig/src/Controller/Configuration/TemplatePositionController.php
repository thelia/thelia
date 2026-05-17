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

namespace BackOfficeDefaultTwigBundle\Controller\Configuration;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

final class TemplatePositionController
{
    private const RESOURCE = AdminResources::TEMPLATE;
    private const EDIT_ROUTE = 'admin.configuration.templates.update';

    public function __construct(
        private readonly AdminFormAction $action,
    ) {
    }

    #[Route('/admin/template/update-feature-position', name: 'admin.configuration.templates.attributes.update-feature-position', methods: ['GET', 'POST'])]
    public function updateFeaturePosition(Request $request): Response
    {
        $templateId = (int) $request->get('template_id', 0);

        $event = new UpdatePositionEvent(
            (int) $request->get('feature_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::TEMPLATE_CHANGE_FEATURE_POSITION,
            actionLabel: 'Template feature reorder',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['template_id' => $templateId],
        );
    }

    #[Route('/admin/template/update-attribute-position', name: 'admin.configuration.templates.attributes.update-attribute-position', methods: ['GET', 'POST'])]
    public function updateAttributePosition(Request $request): Response
    {
        $templateId = (int) $request->get('template_id', 0);

        $event = new UpdatePositionEvent(
            (int) $request->get('attribute_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::TEMPLATE_CHANGE_ATTRIBUTE_POSITION,
            actionLabel: 'Template attribute reorder',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['template_id' => $templateId],
        );
    }
}
