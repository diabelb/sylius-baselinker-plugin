<?php

/**
 * @author Marcin Hubert <>
 * @author Jakub Lech <info@smartbyte.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use SM\Factory\FactoryInterface as StateMachineFactory;
use Spinbits\SyliusBaselinkerPlugin\Model\OrderUpdateModel;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\OrderRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Shipping\ShipmentTransitions;
use Webmozart\Assert\Assert;

class OrderUpdateService
{
	private OrderRepository $orderRepository;
	private StateMachineFactory $stateMachineFactory;
	private EntityManagerInterface $orderEntityManager;

	public function __construct(
		OrderRepository $orderRepository,
		StateMachineFactory $stateMachineFactory,
		EntityManagerInterface $orderEntityManager
	) {
		$this->orderRepository = $orderRepository;
		$this->stateMachineFactory = $stateMachineFactory;
		$this->orderEntityManager = $orderEntityManager;
	}

	public function updateOrders(OrderUpdateModel $inputData): array
	{
		$orders = [];
		/** @var int|string $orderId */
		foreach ($inputData->getOrdersIds() as $orderId) {
			/** @var OrderInterface|null $order */
			$order = $this->orderRepository->find($orderId);
			Assert::isInstanceOf($order, OrderInterface::class, sprintf("Order %s was not found", (string) $orderId));

			$orders[] = $this->updateOrder($order, $inputData);
		}

		return $orders;
	}


	private function updateOrder(OrderInterface $order, OrderUpdateModel $inputData): OrderInterface
	{
		switch ($inputData->getUpdateType()) {
			case "paid":
				/** @var PaymentInterface|null $lastPayment */
				$lastPayment = $order->getLastPayment();
				if ($lastPayment === null) {
					throw new \RuntimeException("Missing payment for order: " . (string) $order->getId());
				}

				/** @var PaymentInterface $lastPayment */
				$this->setComplete($lastPayment, (bool) $inputData->getUpdateValue());
				break;
			case "status":
				$this->updateOrderStatus($order, $inputData->getUpdateValue());
				break;
			case "delivery_number":
				$this->updateShippingData($order, $inputData);
				break;
			default:
				// do nothing
				break;
		}
		$this->orderEntityManager->flush();
		return $order;
	}


	private function setComplete(PaymentInterface $payment, bool $paid): void
	{
		if (false === $paid) {
			return;
		}

		$paymentStateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
		if ($paymentStateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
			$paymentStateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
		}
	}

	private function updateOrderStatus(OrderInterface $order, string $updateValue): void
	{
		$orderStateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

		switch ($updateValue) {
			case 'in_progress':
				if ($orderStateMachine->can('process')) {
					$orderStateMachine->apply('process');
				}
				break;
			case 'sent':
				$lastShipment = $order->getShipments()->last();
				if ($lastShipment === null) {
					throw new \RuntimeException("Missing shipment for order: " . (string) $order->getId());
				}
				$shipmentStateMachine = $this->stateMachineFactory->get($lastShipment, ShipmentTransitions::GRAPH);
				if ($shipmentStateMachine->can(ShipmentTransitions::TRANSITION_SHIP)) {
					$shipmentStateMachine->apply(ShipmentTransitions::TRANSITION_SHIP);
				}
				break;
			case OrderInterface::STATE_FULFILLED:
				// shipment shipped
				// payment paid
				// order fulfilled
				if ($orderStateMachine->can(OrderTransitions::TRANSITION_FULFILL)) {
					$orderStateMachine->apply(OrderTransitions::TRANSITION_FULFILL);
				}

				break;
			case OrderInterface::STATE_CANCELLED:
				if ($orderStateMachine->can(OrderTransitions::TRANSITION_CANCEL)) {
					$orderStateMachine->apply(OrderTransitions::TRANSITION_CANCEL);
				}
				break;
			default:
				break;
		}
	}

	private function updateShippingData(OrderInterface $order, OrderUpdateModel $inputData)
	{
		$lastShipment = $order->getShipments()->last();
		if ($lastShipment === null) {
			throw new \RuntimeException("Missing shipment for order: " . (string) $order->getId());
		}
//		$shipmentStateMachine = $this->stateMachineFactory->get($lastShipment, ShipmentTransitions::GRAPH);
		// perhaps we can change state as
		$lastShipment->setTracking($inputData->getUpdateValue());
	}
}
