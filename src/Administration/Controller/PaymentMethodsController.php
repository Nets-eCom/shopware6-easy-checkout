<?php declare(strict_types=1);

namespace Nexi\Checkout\Administration\Controller;

use Nexi\Checkout\PaymentMethods\PaymentMethodsFetcher;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [
    '_routeScope' => ['api'],
])]
class PaymentMethodsController extends AbstractController
{
    public function __construct(
        private readonly PaymentMethodsFetcher $paymentMethodsFetcher,
    ) {
    }

    #[Route(
        path: '/api/nexicheckout/payment-methods',
        name: 'api.nexicheckout.payment_methods',
        defaults: [
            '_acl' => ['system_config:read'],
        ],
        methods: ['GET']
    )]
    public function getPaymentMethods(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = \in_array($request->query->getString('salesChannelId'), ['', '0'], true)
            ? null : $request->query->getString('salesChannelId');

        $names = $this->paymentMethodsFetcher->fetchAvailableMethodNames($salesChannelId);

        $methods = array_map(
            static fn (string $name) => [
                'name' => $name,
                'paymentType' => $name,
            ],
            $names
        );

        return new JsonResponse([
            'paymentMethods' => $methods,
        ]);
    }
}
