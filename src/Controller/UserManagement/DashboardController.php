<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Repository\Activity\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OPENWEATHER_API_KEY)%')]
        private readonly string $openWeatherApiKey,
        #[Autowire('%env(OPENWEATHER_BASE_URL)%')]
        private readonly string $openWeatherBaseUrl,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $role = $user->getRole();

        // Redirect to role-specific dashboard
        return match ($role) {
            User::ROLE_ADMIN => $this->redirectToRoute('admin_dashboard'),
            User::ROLE_AGRICULTEUR => $this->redirectToRoute('app_dashboard_agriculteur'),
            User::ROLE_FOURNISSEUR => $this->redirectToRoute('app_dashboard_fournisseur'),
            User::ROLE_AGRIPLUS => $this->redirectToRoute('app_dashboard_agriplus'),
            default => $this->redirectToRoute('app_dashboard_user'),
        };
    }

    #[Route('/dashboard/admin', name: 'app_dashboard_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        // Redirect to new admin dashboard
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/dashboard/agriculteur', name: 'app_dashboard_agriculteur')]
    #[IsGranted('ROLE_AGRICULTEUR')]
    public function agriculteurDashboard(EvenementRepository $evenementRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $weather = [];
        $userCity = $user->getVille();

        if ($userCity) {
            try {
                $weather = $this->fetchCurrentWeather($userCity);
            } catch (\Throwable) {
                $weather = [];
            }
        }

        return $this->render('user_management/dashboard/agriculteur.html.twig', [
            'myEvenementsCount' => $evenementRepository->countByOrganisateurId((int) $user->getId()),
            'myEvenements' => $evenementRepository->findLatestByOrganisateurId((int) $user->getId(), 6),
            'weather' => $weather,
            'userCity' => $userCity,
        ]);
    }

    #[Route('/dashboard/fournisseur', name: 'app_dashboard_fournisseur')]
    #[IsGranted('ROLE_FOURNISSEUR')]
    public function fournisseurDashboard(): Response
    {
        return $this->render('user_management/dashboard/fournisseur.html.twig');
    }

    #[Route('/dashboard/agriplus', name: 'app_dashboard_agriplus')]
    #[IsGranted('ROLE_AGRIPLUS')]
    public function agriplusDashboard(): Response
    {
        return $this->render('user_management/dashboard/agriplus.html.twig');
    }

    #[Route('/dashboard/user', name: 'app_dashboard_user')]
    public function userDashboard(): Response
    {
        return $this->render('user_management/dashboard/user.html.twig');
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('user_management/home/landing.html.twig');
    }

    /**
     * Fetch current weather for a city
     *
     * @return array{
     *     icon: string,
     *     description: string,
     *     temp: float,
     *     feels_like: float,
     *     humidity: int,
     *     windSpeed: float
     * }
     */
    private function fetchCurrentWeather(string $city): array
    {
        $response = $this->httpClient->request('GET', rtrim($this->openWeatherBaseUrl, '/') . '/weather', [
            'query' => [
                'q' => sprintf('%s,TN', $city),
                'appid' => trim($this->openWeatherApiKey),
                'units' => 'metric',
                'lang' => 'fr',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Unable to retrieve weather data.');
        }

        $payload = $response->toArray(false);

        return [
            'icon' => (string) $payload['weather'][0]['icon'],
            'description' => ucfirst((string) $payload['weather'][0]['description']),
            'temp' => (float) $payload['main']['temp'],
            'feels_like' => (float) $payload['main']['feels_like'],
            'humidity' => (int) $payload['main']['humidity'],
            'windSpeed' => (float) $payload['wind']['speed'],
        ];
    }
}
