<?php declare(strict_types=1);

namespace Nexi\Checkout\Security;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class NexiVoter
{
    /**
     * @throws AccessDeniedHttpException
     */
    public function denyAccessUnlessGranted(mixed $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->isGranted($attribute, $subject)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    protected function isGranted(string $attribute, mixed $subject): bool
    {
        if (!$this->supports($attribute, $subject)) {
            return false;
        }

        return $this->voteOnAttribute($attribute, $subject);
    }

    abstract protected function supports(string $attribute, mixed $subject): bool;

    abstract protected function voteOnAttribute(string $attribute, mixed $subject): bool;
}
