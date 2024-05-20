<?php

declare(strict_types=1);

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Exception\CartTotalUpdatedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RedirectToConfirmationPageSubscriber implements EventSubscriberInterface
{
    private RouterInterface $router;
    private TranslatorInterface $translator;

    public function __construct(
        RouterInterface $router,
        TranslatorInterface $translator
    ) {
        $this->router = $router;
        $this->translator = $translator;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'redirectToConfirmationPage',
        ];
    }

    public function redirectToConfirmationPage(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof CartTotalUpdatedException) {
            return;
        }

        $this->addFlashMessage($this->getSession($event)->getFlashBag());
        $event->setResponse(new RedirectResponse($this->router->generate('frontend.checkout.confirm.page')));
    }

    private function addFlashMessage(FlashBagInterface $bag): void
    {
        $bag->add('warning', $this->translator->trans('nexi-nets.exception.cartUpdated'));
    }

    private function getSession(ExceptionEvent $event): SessionInterface
    {
        return $event->getRequest()->getSession();
    }
}