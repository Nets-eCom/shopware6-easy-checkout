<?php

declare(strict_types=1);

namespace NexiNets\Administration\Controller;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\Order\OrderCharge;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [
    '_routeScope' => ['api'],
])]
class OrderActionController extends AbstractController
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderCharge $orderCharge,
    ) {
    }

    #[Route(
        path: '/api/order/{orderId}/nexinets-payment-charge',
        name: 'api.nexinets.payment.charge',
        defaults: [
            '_acl' => [
                'order:read',
                'order:write',
            ],
        ],
        methods: ['PUT']
    )
    ]
    public function charge(
        Context $context,
        string $orderId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ChargeData $chargeData
    ): Response {
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))
                ->addAssociation('transactions')
                ->addAssociation('stateMachineState'),
            $context
        )->get($orderId);

        if (!$order instanceof OrderEntity) {
            throw OrderException::orderNotFound($orderId);
        }


        try {
            $this->processCharge($order, $chargeData);
        } catch (PaymentApiException) {
            return $this->json([], status: Response::HTTP_BAD_REQUEST);
        }

        return $this->json([]);
    }

    /**
     * @throws PaymentApiException
     */
    private function processCharge(OrderEntity $order, ChargeData $chargeData): void
    {
        if ($chargeData->getAmount() < $order->getAmountTotal()) {
            $this->orderCharge->partialCharge($order, $chargeData);

            return;
        }

        $this->orderCharge->fullCharge($order);
    }
}
