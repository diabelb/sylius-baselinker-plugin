<?php

/**
 * @author Adam Terepora <adam@terepora.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Handler;

use Pagerfanta\Pagerfanta;
use Spinbits\SyliusBaselinkerPlugin\Filter\OrderListFilter;
use Spinbits\SyliusBaselinkerPlugin\Mapper\ListOrderMapper;
use Spinbits\SyliusBaselinkerPlugin\Model\OrderAddModel;
use Spinbits\SyliusBaselinkerPlugin\Repository\BaseLinkerOrderRepositoryInterface;
use Spinbits\SyliusBaselinkerPlugin\Service\OrderCreateService;
use Spinbits\SyliusBaselinkerPlugin\Handler\HandlerInterface;
use Spinbits\SyliusBaselinkerPlugin\Rest\Exception\InvalidArgumentException;
use Spinbits\SyliusBaselinkerPlugin\Rest\Input;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Order;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrdersGetActionHandler implements HandlerInterface
{
	private ListOrderMapper $mapper;
    private ChannelContextInterface $channelContext;
	private BaseLinkerOrderRepositoryInterface $orderRepository;

    public function __construct(
		ListOrderMapper $mapper,
		BaseLinkerOrderRepositoryInterface $orderRepository,
		ChannelContextInterface $channel
    )
    {
		$this->mapper = $mapper;
        $this->channelContext = $channel;
		$this->orderRepository = $orderRepository;
    }

    /**
     * @param Input $input
     * @return array
     * @throws InvalidArgumentException
     */
	public function handle(Input $input): array
	{
		/** @var ChannelInterface $channel */
		$channel = $this->channelContext->getChannel();
		$filter = new OrderListFilter($input, $channel);

		$paginator = $this->orderRepository->fetchBaseLinkerData($filter);
		$return = [];
		/** @var Order[] $paginator */
		foreach ($paginator as $order) {
			$return[(int) $order->getId()] = $this->mapper->map($order, $channel);
		}
		/** @var Pagerfanta $paginator */
		$return['pages'] = $paginator->getNbPages();
		return  $return;
	}
}
