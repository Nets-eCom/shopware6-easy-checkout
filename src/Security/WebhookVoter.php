<?php declare(strict_types=1);

namespace NexiNets\Security;

use NexiNets\Configuration\ConfigurationProvider;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class WebhookVoter extends NexiVoter
{
    public const HEADER_MATCH = 'nexinets_header_match';

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::HEADER_MATCH && $subject instanceof SalesChannelContext;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject): bool
    {
        /** @var SalesChannelContext $context */
        $context = $subject;
        $request = $this->requestStack->getMainRequest();

        if (!$this->isValidAuthHeader($request, $context)) {
            $this->logger->error(
                'Invalid Authorization Header',
                [
                    'authorization_header' => $request->headers->get('Authorization'),
                    'salesChannelId' => $context->getSalesChannelId(),
                ]
            );

            return false;
        }

        return true;
    }

    private function isValidAuthHeader(Request $request, SalesChannelContext $context): bool
    {
        $expected = $this->configurationProvider->getWebhookAuthorizationHeader($context->getSalesChannelId());
        if ($expected === '') {
            return false;
        }

        return $expected === $request->headers->get('Authorization');
    }
}
