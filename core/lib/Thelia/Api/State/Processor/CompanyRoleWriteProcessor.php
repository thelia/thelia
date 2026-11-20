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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\CompanyRole as CompanyRoleResource;
use Thelia\Domain\Company\Service\CompanyPermissionRegistry;
use Thelia\Model\CompanyQuery;
use Thelia\Model\CompanyRole;
use Thelia\Model\CompanyRoleQuery;

/**
 * Writes a role: the list of permissions (each one the shop knows, none twice) and its titles. The
 * code and the company of a role are chosen when it is created and never changed, since buyers
 * reach their role by that code; a role of a company may not take the code of a role every company
 * shares, which would never be the one found.
 */
final readonly class CompanyRoleWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyPermissionRegistry $permissionRegistry,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CompanyRoleResource
    {
        if (!$data instanceof CompanyRoleResource) {
            throw new UnprocessableEntityHttpException('A role is expected.');
        }

        $permissions = $this->checkedPermissions($data->getPermissions());

        if ($operation instanceof Post) {
            $role = $this->created($data);
        } else {
            $role = CompanyRoleQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Role not found.');

            if (null !== $data->getCode() && $data->getCode() !== $role->getCode()) {
                throw new UnprocessableEntityHttpException('The code of a role cannot be changed.');
            }

            if ($data->getCompanyId() !== ($role->getCompanyId() === null ? null : (int) $role->getCompanyId())) {
                throw new UnprocessableEntityHttpException('The company of a role cannot be changed.');
            }
        }

        $role->setPermissions(json_encode($permissions, \JSON_THROW_ON_ERROR));

        foreach ($data->getI18ns() as $locale => $i18n) {
            if (null !== $i18n->getTitle()) {
                $role->setLocale((string) $locale)->setTitle($i18n->getTitle());
            }
        }

        $role->save();

        /** @var CompanyRoleResource $resource */
        $resource = $this->transformer->modelToResource(CompanyRoleResource::class, CompanyRoleQuery::create()->findPk($role->getId()) ?? $role, $context);

        return $resource;
    }

    private function created(CompanyRoleResource $data): CompanyRole
    {
        $code = (string) $data->getCode();
        $companyId = $data->getCompanyId();

        if (null !== $companyId && !CompanyQuery::create()->filterById($companyId)->exists()) {
            throw new UnprocessableEntityHttpException('The company of the role does not exist.');
        }

        $sharedExists = CompanyRoleQuery::create()->filterByCode($code)->filterByCompanyId(null, Criteria::ISNULL)->exists();

        if (null !== $companyId && $sharedExists) {
            throw new UnprocessableEntityHttpException('A role of a company cannot take the code of a role every company shares.');
        }

        if (null === $companyId ? $sharedExists : CompanyRoleQuery::create()->filterByCode($code)->filterByCompanyId($companyId)->exists()) {
            throw new UnprocessableEntityHttpException('A role with this code already exists.');
        }

        return (new CompanyRole())->setCode($code)->setCompanyId($companyId);
    }

    /**
     * @param array<mixed> $permissions
     *
     * @return list<string>
     */
    private function checkedPermissions(array $permissions): array
    {
        $checked = [];

        foreach ($permissions as $permission) {
            if (!\is_string($permission) || !$this->permissionRegistry->isKnown($permission)) {
                throw new UnprocessableEntityHttpException(\sprintf('"%s" is not a permission of this shop.', \is_string($permission) ? $permission : \gettype($permission)));
            }

            $checked[] = $permission;
        }

        return array_values(array_unique($checked));
    }
}
