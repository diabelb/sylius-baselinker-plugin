<?php

/**
 * @author Adam Terepora <adam@terepora.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Mapper;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\Intl\Countries;

class ListOrderMapper
{
	public function map(Order $order, ChannelInterface $channel): array
	{
		$products = [];
		/** @var OrderItemInterface $orderItem */
		foreach ($order->getItems() as $orderItem) {
			/** @var ProductVariant $v */
			$v = $orderItem->getVariant();
			$taxAdjustments = $orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);
			foreach ($taxAdjustments as $taxAdjustment) {
				$t = '';
			}

	    $tempArr = [];
            $attrs = [];
            foreach ($orderItem->getCustomerOptionConfiguration() as $orderItemOption) {
                if ( ! $orderItemOption->getCustomerOptionValueName()) {
                    continue;
                }
	    	if (!array_key_exists($orderItemOption->getCustomerOptionName(), $tempArr) && !in_array($orderItemOption->getCustomerOptionValueName(), $tempArr)) {
                	$attrs[] = [
                    		'name' => $orderItemOption->getCustomerOptionName(),
                    		'value' => $orderItemOption->getCustomerOptionValueName()
                	];
			$tempArr[$orderItemOption->getCustomerOptionName()] = $orderItemOption->getCustomerOptionValueName();
		}
            }

	    // additional options
            $optionsTotal = 0;
            foreach ($orderItem->getUnits() as $unit) {
                $unitAdditionalValueAdjustments = $unit->getAdditionalUnitOptions();
                foreach ($unitAdditionalValueAdjustments as $unitAdditionalValueAdjustment) {
                    $attrs[] = [
                        'name' => $unitAdditionalValueAdjustment->getLabel(),
                        'value' => 'Tak'
                    ];
                    $optionsTotal += $unitAdditionalValueAdjustment->getAmount();
                }
            }
            // end additional options
			
			$product = [
				'id'         => $orderItem->getProduct()->getId(),
				'name'       => $orderItem->getVariantName() ? sprintf('%s (%s)', $orderItem->getProductName(), $orderItem->getVariantName()) : $orderItem->getProductName(),
				'quantity'   => $orderItem->getQuantity(),
				'price'      => ($orderItem->getFullDiscountedUnitPrice() + $optionsTotal) / 100,
				'tax'        => 23, // here should be proper order item tax rate
				'weight'     => 0,
				'sku'        => $orderItem->getVariant()->getCode(),
				'ean'        => null,
				'attributes' => $attrs,
			];

			array_push($products, $product);
		}

	$tempArr2 = [];
        $customerOptionsComments = [];
        foreach ($order->getItems() as $item) {
            /** @var OrderItem $item */
            if (!$item->getCustomerOptionConfiguration()) continue;

            array_push($customerOptionsComments, $item->getProductName());
            array_push($customerOptionsComments, '----------------');
	    foreach ($item->getUnits() as $unit) {
                $unitAdditionalValueAdjustments = $unit->getAdditionalUnitOptions();
                foreach ($unitAdditionalValueAdjustments as $unitAdditionalValueAdjustment) {
                    $entry = sprintf('%s: %s', $unitAdditionalValueAdjustment->getLabel(), 'Tak');
                    array_push($customerOptionsComments, $entry);
                }
            }
            foreach ($item->getCustomerOptionConfiguration() as $orderItemOption) {
                if (!$orderItemOption->getCustomerOptionValueName()) continue;

		if (!array_key_exists($orderItemOption->getCustomerOptionName(), $tempArr2) && !in_array($orderItemOption->getCustomerOptionValueName(), $tempArr2)) {
                	$entry = sprintf('%s: %s', $orderItemOption->getCustomerOptionName(), $orderItemOption->getCustomerOptionValueName());
                	array_push($customerOptionsComments, $entry);

			$tempArr2[$orderItemOption->getCustomerOptionName()] = $orderItemOption->getCustomerOptionValueName();
		}
            }
            array_push($customerOptionsComments, '================');
        }

		return [
			'delivery_fullname'     => $order->getShippingAddress()->getFullName(),
			'delivery_company'      => $order->getShippingAddress()->getCompany(),
			'delivery_address'      => $order->getShippingAddress()->getStreet(),
			'delivery_city'         => $order->getShippingAddress()->getCity(),
			'delivery_postcode'     => $order->getShippingAddress()->getPostcode(),
			'delivery_country'      => Countries::getName($order->getShippingAddress()->getCountryCode()),
			'delivery_country_code' => $order->getShippingAddress()->getCountryCode(),
			'invoice_fullname'      => $order->getBillingAddress()->getFullName(),
			'invoice_company'       => $order->getBillingAddress()->getCompany(),
			'invoice_nip'           => '',
			'invoice_address'       => $order->getBillingAddress()->getStreet(),
			'invoice_city'          => $order->getBillingAddress()->getCity(),
			'invoice_postcode'      => $order->getBillingAddress()->getPostcode(),
			'invoice_country'       => Countries::getName($order->getBillingAddress()->getCountryCode()),
			'invoice_country_code'  => $order->getBillingAddress()->getCountryCode(),
			'delivery_point_id'     => $order->getPoint() ? $order->getPoint()->getName() : '',
			'delivery_point_name'   => $order->getPoint() ? sprintf('Paczkomat %s', $order->getPoint()->getName()): '',
			'phone'                 => $order->getShippingAddress()->getPhoneNumber() ?? $order->getCustomer()->getPhoneNumber(),
			'email'                 => $order->getCustomer()->getEmail(),
			'want_invoice'          => (int)false, // will be replaced with checking whether billing address provided and tax number provided
			'date_add'              => $order->getCheckoutCompletedAt()->format('U'),
			'user_comments'         => $order->getNotes(),
			'user_comments_long'    => join("\n", $customerOptionsComments),
			'delivery_method'       => $order->getShipments()->count() > 0 ? $order->getShipments()->last()->getMethod()->getName() : '',
			'payment_method'        => $order->getLastPayment() ? $order->getLastPayment()->getMethod()->getName() : ($order->getPromotionCoupon() ? 'kupon promocyjny' : 'Bez płatności'),
			'payment_method_cod'    => $order->getLastPayment() ? (in_array($order->getLastPayment()->getMethod()->getCode(), ['cash_on_delivery', 'za-pobraniem-przy-odbiorze']) ? 1 : 0) : 0,
			'delivery_price'        => $order->getShippingTotal() / 100,
			'currency'              => $order->getLastPayment() ? $order->getLastPayment()->getCurrencyCode() : 'PLN',
			'status_id'             => $order->getCheckoutState(),
			'paid'                  => $order->getPaymentState() === OrderPaymentStates::STATE_PAID ? 1 : 0,
			'paid_time'             => $order->getPaymentState() === OrderPaymentStates::STATE_PAID ? ($order->getLastPayment() ? $order->getLastPayment()->getUpdatedAt()->format('U') : null) : null,
			'products'              => $products,
		];
	}
}
