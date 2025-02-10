<?php
declare(strict_types=1);

namespace Nexi\Checkout\WebhookProcessor\Processor;

use NexiCheckout\Model\Webhook\EventNameEnum;
use Shopware\Core\System\StateMachine\StateMachineException;

trait ProcessorLogTrait
{
    private function logStateMachineException(
        StateMachineException $stateMachineException,
        EventNameEnum $eventNameEnum,
        string $paymentId
    ): void {
        $this->logger->error(
            \sprintf('%s failed: %s', $eventNameEnum->value, $stateMachineException->getMessage()),
            [
                'paymentId' => $paymentId,
                'exception' => $stateMachineException,
            ]
        );
    }

    private function logProcessMessage(EventNameEnum $eventNameEnum, string $message, string $paymentId): void
    {
        $this->logger->info(
            $this->createEventLogMessage($eventNameEnum, $message),
            [
                'paymentId' => $paymentId,
            ]
        );
    }

    private function createEventLogMessage(EventNameEnum $eventNameEnum, string $message): string
    {
        return \sprintf('%s %s', $eventNameEnum->value, $message);
    }
}
