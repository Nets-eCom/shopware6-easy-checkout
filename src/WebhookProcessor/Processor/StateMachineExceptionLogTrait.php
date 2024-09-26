<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use Shopware\Core\System\StateMachine\StateMachineException;

trait StateMachineExceptionLogTrait
{
    abstract public function getEvent(): EventNameEnum;

    private function logStateMachineException(StateMachineException $stateMachineException, string $paymentId): void
    {
        $this->logger->error(
            \sprintf('%s failed: %s', $this->getEvent()->value, $stateMachineException->getMessage()),
            [
                'paymentId' => $paymentId,
                'exception' => $stateMachineException,
            ]
        );
    }
}
