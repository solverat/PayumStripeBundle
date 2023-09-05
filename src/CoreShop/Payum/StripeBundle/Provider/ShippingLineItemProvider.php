<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2021 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

declare(strict_types=1);

namespace CoreShop\Payum\StripeBundle\Provider;

use CoreShop\Component\Core\Model\OrderInterface;

final class ShippingLineItemProvider implements ShippingLineItemProviderInterface
{
    /** @var ShippingLineItemNameProviderInterface */
    private $shippingLineItemProvider;

    public function __construct(ShippingLineItemNameProviderInterface $shippingLineItemProvider)
    {
        $this->shippingLineItemProvider = $shippingLineItemProvider;
    }

    public function getLineItem(OrderInterface $order): ?array
    {
        if (0 === $order->getShipping(false)) {
            return null;
        }

        return [
            'quantity' => 1,
            'price_data' => [
                'currency' => $order->getCurrency()->getIsoCode(),
                'unit_amount' => $order->getShipping(),
                'product_data' => [
                    'name' => $this->shippingLineItemProvider->getItemName($order),
                ],
            ],
        ];
    }
}
