<?php declare(strict_types=1);

namespace NexiNets\Storefront\Controller;

use NexiNets\CheckoutApi\Model\Webhook\Webhook;
use NexiNets\Security\WebhookVoter;
use NexiNets\WebhookProcessor\WebhookProcessor;
use NexiNets\WebhookProcessor\WebhookProcessorException;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/nexinets', name: 'nexinets_', defaults: [
    '_routeScope' => ['storefront'],
])]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookVoter $webhookVoter,
        private readonly WebhookProcessor $webhookProcessor,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/webhook', name: 'payment.nexinets.webhook', methods: ['POST'])]
    public function webhook(Request $request, SalesChannelContext $context): Response
    {
        $this->webhookVoter->denyAccessUnlessGranted(WebhookVoter::HEADER_MATCH, $context);

        $webhook = Webhook::fromJson($request->getContent());
        try {
            $this->webhookProcessor->process($webhook);
        } catch (WebhookProcessorException $webhookProcessorException) {
            $this->logger->error(
                'Webhook processing failed',
                [
                    'paymentId' => $webhook->getData()->getPaymentId(),
                    'exception' => $webhookProcessorException,
                ]
            );

            throw new BadRequestHttpException('Webhook processing failed.', $webhookProcessorException);
        }

        return new JsonResponse();
    }
}
