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

namespace Thelia\Controller\Api;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\CustomerTitleI18nTableMap;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;


/**
 * Class CustomerController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerController extends BaseApiController
{

    public function listAction()
    {
        $this->checkAuth(AdminResources::CUSTOMER, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $orderArgument = new Argument(
            'order',
            new TypeCollection(
                new EnumType(array(
                    'id',
                    'id_reverse',
                    'reference',
                    'reference_reverse',
                    'firstname',
                    'firstname_reverse',
                    'lastname',
                    'lastname_reverse',
                    'last_order',
                    'last_order_reverse',
                    'order_amount',
                    'order_amount_reverse',
                    'registration_date',
                    'registration_date_reverse'
                ))
            ),
            'lastname'
        );

        $offset = $request->query->get('offset', 0);
        $limit = $request->query->get('limit', 10);
        $order = $request->query->get('order', $orderArgument->default);



        if (!$orderArgument->type->isValid($order)) {
            throw new BadRequestHttpException('{"error": "order parameter is invalid"}');
        }

        $orderArgument->setValue($order);

        $query = $this->getBaseCustomerQuery()
            ->offset($offset)
            ->limit($limit)
        ;

        $query = $this->getOrderClause($orderArgument->getValue(), $query);

        $results = $query->find()->toJSON(false, true);

        return Response::create($results);

    }

    /**
     * @return \Thelia\Model\CustomerQuery
     */
    private function getBaseCustomerQuery()
    {
        $locale = $this->getRequest()->query->get('locale', 'en_US');
        $titleJoin = new Join();
        $titleJoin->addExplicitCondition(CustomerTableMap::TABLE_NAME, 'TITLE_ID', null, CustomerTitleI18nTableMap::TABLE_NAME, 'ID', null);
        $titleJoin->setJoinType(Criteria::LEFT_JOIN);

        $query = CustomerQuery::create()
            ->select(['Id', 'Ref', 'TitleId', 'Title', 'Firstname', 'Lastname', 'Email', 'Reseller', 'Lang', 'Sponsor', 'Discount', 'CreatedAt', 'UpdatedAt'])
            ->addJoinObject($titleJoin, 'title_join')
            ->addJoinCondition('title_join', '`customer_title_i18n`.`locale` = ?', $locale, null, \PDO::PARAM_STR)
            ->withColumn('`customer_title_i18n`.`long`', 'Title')
        ;

        return $query;
    }

    /**
     * @param $order
     * @param $search
     * @return \Thelia\Model\CustomerQuery
     */
    private function getOrderClause($order, $search)
    {
        switch ($order) {
            case 'id':
                $search->orderById(Criteria::ASC);
                break;
            case 'id_reverse':
                $search->orderById(Criteria::DESC);
                break;

            case 'reference':
                $search->orderByRef(Criteria::ASC);
                break;
            case 'reference_reverse':
                $search->orderByRef(Criteria::DESC);
                break;

            case 'lastname':
                $search->orderByLastname(Criteria::ASC);
                break;
            case 'lastname_reverse':
                $search->orderByLastname(Criteria::DESC);
                break;

            case 'firstname':
                $search->orderByFirstname(Criteria::ASC);
                break;
            case 'firstname_reverse':
                $search->orderByFirstname(Criteria::DESC);
                break;

            case 'registration_date':
                $search->orderByCreatedAt(Criteria::ASC);
                break;
            case 'registration_date_reverse':
                $search->orderByCreatedAt(Criteria::DESC);
                break;

        }

        return $search;
    }

    public function getCustomerAction($customer_id)
    {
        $this->checkAuth(AdminResources::CUSTOMER, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $customer = $this->getBaseCustomerQuery()
            ->filterByPrimaryKey($customer_id)
            ->find();

        if ($customer->isEmpty()) {
            throw new HttpException(404, sprintf('{"error": "customer with id %d not found"}', $customer_id));
        }

        return Response::create($customer->toJSON(false, true));

    }

    public function createCustomerAction()
    {
        $this->checkAuth(AdminResources::CUSTOMER, [], AccessManager::CREATE);


    }
}