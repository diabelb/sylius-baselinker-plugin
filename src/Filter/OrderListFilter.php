<?php

/**
 * @author Marcin Hubert <hubert.m.j@gmail.com>
 * @author Jakub Lech <info@smartbyte.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Filter;

use Spinbits\SyliusBaselinkerPlugin\Rest\Input;

class OrderListFilter extends AbstractFilter implements PaginatorFilterInterface
{
	public function getLimit(): int
	{
		$limit = (int) $this->get('filter_limit', 100);

		if ($limit > 200) {
			return 200;
		}

		if ($limit < 1) {
			return 100;
		}

		return $limit;
	}

	public function getPage(): int
	{
		$page = (int) $this->get('page', 1);
		return $page < 1 ? 1 : $page;
	}

	public function hasTimeFrom(): bool
	{
		return '' !== $this->getTimeFrom();
	}

	public function getTimeFrom(): ?string
	{
		return (string) $this->get('time_from');
	}

	public function hasIdFrom(): bool
	{
		return '' !== $this->getIdFrom();
	}

	public function getIdFrom(): ?string
	{
		return (string) $this->get('id_from');
	}

	public function hasOnlyPaid(): bool
	{
		return '' !== $this->isPaidOnly();
	}

	public function isPaidOnly(): ?int
	{
		$onlyPaid = $this->get('only_paid');
		if ($onlyPaid && !in_array($onlyPaid, [0, 1])) return null;

		return (int) $onlyPaid;
	}

	public function hasOrderId(): bool
	{
		return '' !== $this->getOrderId();
	}

	public function getOrderId(): ?string
	{
		return (string) $this->get('order_id');
	}
}
