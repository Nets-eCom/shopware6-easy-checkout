<?php declare(strict_types=1);

namespace NexiNets\Storefront\Controller;

use NexiNets\CheckoutApi\Model\Webhook\WebhookBuilder;
use NexiNets\Core\Content\NetsCheckout\Event\WebhookProcessed;
use NexiNets\Security\WebhookVoter;
use NexiNets\WebhookProcessor\WebhookProcessor;
use NexiNets\WebhookProcessor\WebhookProcessorException;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    #[Route(path: '/webhook', name: 'payment.nexinets.webhook', methods: ['POST'])]
    public function webhook(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $this->webhookVoter->denyAccessUnlessGranted(WebhookVoter::HEADER_MATCH, $salesChannelContext);

        try {
            $webhook = WebhookBuilder::fromJson($request->getContent());
        } catch (\Throwable $throwable) {
            $this->logger->critical(
                'Webhook payload parsing failed',
                [
                    'content' => $request->getContent(),
                    'exception' => $throwable,
                ]
            );

            throw $throwable;
        }

        $paymentId = $webhook->getData()->getPaymentId();

        try {
            $this->webhookProcessor->process($webhook, $salesChannelContext);
        } catch (WebhookProcessorException $webhookProcessorException) {
            $this->logger->error(
                'Webhook processing failed',
                [
                    'paymentId' => $paymentId,
                    'exception' => $webhookProcessorException,
                ]
            );

            throw new BadRequestHttpException('Webhook processing failed.', $webhookProcessorException);
        }

        $this->dispatcher->dispatch(new WebhookProcessed($webhook, $paymentId));

        return new JsonResponse();
    }
}
