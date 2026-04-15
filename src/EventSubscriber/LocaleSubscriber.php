<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const DEFAULT_LOCALE = 'fr';
    private const ALLOWED_LOCALES = ['fr', 'en', 'ar'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            $request->setLocale(self::DEFAULT_LOCALE);
            return;
        }

        $session = $request->getSession();
        $savedLocale = (string) $session->get('_locale', self::DEFAULT_LOCALE);
        if (!\in_array($savedLocale, self::ALLOWED_LOCALES, true)) {
            $savedLocale = self::DEFAULT_LOCALE;
            $session->set('_locale', self::DEFAULT_LOCALE);
        }

        $request->setLocale($savedLocale);
    }
}
