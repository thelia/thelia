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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\NewsletterTableMap;
use Thelia\Type\AlphaNumStringListType;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Customer loop.
 *
 * Class Customer
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method bool        getCurrent()
 * @method string      getRef()
 * @method bool        getReseller()
 * @method int         getSponsor()
 * @method bool|string getNewsletter()
 * @method string[]    getOrder()
 * @method bool        getWithPrevNextInfo()
 */
class Customer extends BaseLoop implements SearchLoopInterface, PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('current', 1),
            Argument::createIntListTypeArgument('id'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            new Argument(
                'ref',
                new TypeCollection(
                    new AlphaNumStringListType(),
                ),
            ),
            Argument::createBooleanTypeArgument('reseller'),
            Argument::createIntTypeArgument('sponsor'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
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
                            'registration_date_reverse',
                        ],
                    ),
                ),
                'lastname',
            ),
            Argument::createBooleanOrBothTypeArgument('newsletter', BooleanOrBothType::ANY),
        );
    }

    public function getSearchIn(): array
    {
        return [
            'ref',
            'firstname',
            'lastname',
            'email',
        ];
    }

    public function doSearch(ModelCriteria $search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();

        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }

            switch ($searchInElement) {
                case 'ref':
                    $search->filterByRef($searchTerm, $searchCriteria);
                    break;
                case 'firstname':
                    $search->filterByFirstname($searchTerm, $searchCriteria);
                    break;
                case 'lastname':
                    $search->filterByLastname($searchTerm, $searchCriteria);
                    break;
                case 'email':
                    $search->filterByEmail($searchTerm, $searchCriteria);
                    break;
            }
        }
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = CustomerQuery::create();

        // Join newsletter
        $newsletter = $this->getNewsletter();

        // if newsletter === "*" or false, it'll be a left join
        $join = new Join(
            CustomerTableMap::COL_EMAIL,
            NewsletterTableMap::COL_EMAIL,
            true === $newsletter ? Criteria::INNER_JOIN : Criteria::LEFT_JOIN,
        );

        $search
            ->addJoinObject($join, 'newsletter_join')
            ->addJoinCondition('newsletter_join', NewsletterTableMap::COL_UNSUBSCRIBED . ' = ?', false, null, \PDO::PARAM_BOOL)
            ->withColumn('IF(ISNULL(' . NewsletterTableMap::COL_EMAIL . '), 0, 1)', 'is_registered_to_newsletter');

        // If "*" === $newsletter, no filter will be applied, so it won't change anything
        if (false === $newsletter) {
            $search->having('is_registered_to_newsletter = 0');
        }

        $current = $this->getCurrent();

        if (true === $current) {
            $currentCustomer = $this->securityContext->getCustomerUser();

            if (null === $currentCustomer) {
                return $search;
            }

            $search->filterById($currentCustomer->getId(), Criteria::EQUAL);
        }

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $ref = $this->getRef();

        if (null !== $ref) {
            $search->filterByRef($ref, Criteria::IN);
        }

        $reseller = $this->getReseller();

        if (true === $reseller) {
            $search->filterByReseller(1, Criteria::EQUAL);
        } elseif (false === $reseller) {
            $search->filterByReseller(0, Criteria::EQUAL);
        }

        $sponsor = $this->getSponsor();

        if (null !== $sponsor) {
            $search->filterBySponsor($sponsor, Criteria::EQUAL);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
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
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Thelia\Model\Customer $customer */
        foreach ($loopResult->getResultDataCollection() as $customer) {
            $loopResultRow = new LoopResultRow($customer);

            $loopResultRow
                ->set('ID', $customer->getId())
                ->set('REF', $customer->getRef())
                ->set('TITLE', $customer->getTitleId())
                ->set('FIRSTNAME', $customer->getFirstname())
                ->set('LASTNAME', $customer->getLastname())
                ->set('EMAIL', $customer->getEmail())
                ->set('RESELLER', $customer->getReseller())
                ->set('SPONSOR', $customer->getSponsor())
                ->set('DISCOUNT', $customer->getDiscount())
                ->set('NEWSLETTER', $customer->getVirtualColumn('is_registered_to_newsletter'))
                ->set('CONFIRMATION_TOKEN', $customer->getConfirmationToken());

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = CustomerQuery::create()
                    ->filterById($customer->getId(), Criteria::LESS_THAN);
                $previous = $previousQuery
                    ->orderById(Criteria::DESC)
                    ->findOne();
                $nextQuery = CustomerQuery::create()
                    ->filterById($customer->getId(), Criteria::GREATER_THAN);
                $next = $nextQuery
                    ->orderById(Criteria::ASC)
                    ->findOne();
                $loopResultRow
                    ->set('HAS_PREVIOUS', null !== $previous ? 1 : 0)
                    ->set('HAS_NEXT', null !== $next ? 1 : 0)
                    ->set('PREVIOUS', null !== $previous ? $previous->getId() : -1)
                    ->set('NEXT', null !== $next ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $customer);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
