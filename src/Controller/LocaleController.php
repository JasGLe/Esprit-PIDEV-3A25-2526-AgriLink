<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    private const ALLOWED_LOCALES = ['fr', 'en', 'ar'];

    #[Route('/change-locale/{locale}', name: 'app_change_locale', methods: ['GET'])]
    public function change(string $locale, Request $request): RedirectResponse
    {
        if (!\in_array($locale, self::ALLOWED_LOCALES, true)) {
            $locale = 'fr';
        }

        $session = $request->getSession();
        if ($session !== null) {
            $session->set('_locale', $locale);
        }

        $target = (string) $request->query->get('redirect', '');
        if ($target === '') {
            $target = (string) $request->headers->get('referer', '');
        }
        if ($target === '') {
            $target = $this->generateUrl('marketplace_index');
        }

        return $this->redirect($target);
    }
}
