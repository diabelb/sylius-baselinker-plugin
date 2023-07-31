<?php

/**
 * @author Adam Terepora <adam@terepora.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Repository;

use Spinbits\SyliusBaselinkerPlugin\Filter\AbstractFilter;
use Spinbits\SyliusBaselinkerPlugin\Filter\OrderListFilter;
use Spinbits\SyliusBaselinkerPlugin\Filter\PaginatorFilterInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\LessThan1CurrentPageException;
use Pagerfanta\Pagerfanta;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\OrderInterface;

trait OrdersRepositoryTrait
{
	private bool $pricingsJoined = false;
	private bool $translationsJoined = false;

	public function fetchBaseLinkerData(OrderListFilter $filter): Pagerfanta
	{
		$queryBuilder = $this->prepareBaseLinkerQueryBuilder($filter);
		$this->applyFilters($queryBuilder, $filter);

		return $this->appendPaginator($filter, $queryBuilder);
	}

	private function prepareBaseLinkerQueryBuilder(AbstractFilter $filter): QueryBuilder
	{
		$queryBuilder = $this->createQueryBuilder('o');

		$queryBuilder
			->andWhere('o.channel = :channel')
			->andWhere('o.state != :state')
			->setParameter('channel', $filter->getChannel())
			->setParameter('state', OrderInterface::STATE_CART);

		return $queryBuilder;
	}

	private function appendPaginator(PaginatorFilterInterface $filter, QueryBuilder $queryBuilder): Pagerfanta
	{
		$paginator = new Pagerfanta(new QueryAdapter($queryBuilder));
		$paginator->setNormalizeOutOfRangePages(true);
		$paginator->setMaxPerPage($filter->getLimit());
		try {
			$paginator->setCurrentPage($filter->getPage());
		} catch (LessThan1CurrentPageException $exception) {
			// ignore
		}

		return $paginator;
	}

	private function applyFilters(QueryBuilder $queryBuilder, OrderListFilter $filter): void
	{
		if ($filter->hasTimeFrom()) {
			$this->filterByTimeFrom($queryBuilder, (string) $filter->getTimeFrom());
		}

		if ($filter->hasIdFrom()) {
			$this->filterByIdFrom($queryBuilder, $filter->getIdFrom());
		}

		if ($filter->hasOnlyPaid() && $filter->isPaidOnly()) {
			$this->filterByPaidOnly($queryBuilder);
		}

		if ($filter->hasOrderId()) {
			$this->filterByOrderId($queryBuilder, (int) $filter->getOrderId());
		}
	}

	private function filterByTimeFrom(QueryBuilder $queryBuilder, string $timestamp): void
	{
		$queryBuilder->andWhere('o.checkoutCompletedAt >= :timeFrom');
		$queryBuilder->setParameter('timeFrom', (new \DateTime())->setTimestamp((int) $timestamp));
	}

	private function filterByIdFrom(QueryBuilder $queryBuilder, string $idFrom): void
	{
		$queryBuilder->andWhere('o.id >= :id');
		$queryBuilder->setParameter('id', $idFrom);
	}

	private function filterByPaidOnly(QueryBuilder $queryBuilder): void
	{
		$queryBuilder->andWhere('o.paymentState = :paid');
		$queryBuilder->setParameter('paid', 'paid');
	}

	private function filterByOrderId(QueryBuilder $queryBuilder, int $orderId): void
	{
		$queryBuilder->andWhere('o.id = :orderId');
		$queryBuilder->setParameter('orderId', $orderId);
	}
}
