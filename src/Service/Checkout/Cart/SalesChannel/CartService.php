<?php

namespace Nets\Checkout\Service\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as CartServiceBase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Nets\Checkout\Service\ConfigService;

class CartService extends CartServiceBase
{

    const HANDLER_IDENTIFIER = 'Nets\Checkout\Service\Checkout';

    private $persister;

    /**
     * @var ConfigService
     */
    private $configService;

    public function __construct(CartPersisterInterface $persister,
                                OrderPersisterInterface $orderPersister,
                                CartRuleLoader $cartRuleLoader,
                                EntityRepositoryInterface $orderRepository,
                                EntityRepositoryInterface $orderCustomerRepository,
                                EventDispatcherInterface $eventDispatcher, ConfigService $configService)
    {
        $this->persister= $persister;
        $this->configService = $configService;

        parent::__construct($persister, $orderPersister, $cartRuleLoader, $orderRepository, $orderCustomerRepository, $eventDispatcher);
    }

     function order(Cart $cart, SalesChannelContext $context): string
     {
         $orderId = parent::order($cart, $context);

         $checkoutType = $this->configService->getCheckoutType($context->getSalesChannel()->getId());

         if('hosted' == $checkoutType &&
             $context->getPaymentMethod()->getHandlerIdentifier() == self::HANDLER_IDENTIFIER) {
             $this->persister->save($cart, $context);
         }
         return $orderId;
     }
}
