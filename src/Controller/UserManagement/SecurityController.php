<?php

namespace App\Controller\UserManagement;

use App\Service\FaceRecognitionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Repository\UserManagement\UserRepository;

class SecurityController extends AbstractController
{
    public function __construct(
        private string $recaptchaSiteKey,
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Get remembered email from cookie
        $rememberedEmail = $request->cookies->get('remembered_email', '');

        // Prepare response data
        $responseData = [
            'last_username' => $lastUsername ?: $rememberedEmail,
            'remembered_email' => $rememberedEmail,
            'error' => $error,
            'recaptcha_site_key' => $this->recaptchaSiteKey,
        ];

        return $this->render('user_management/security/login.html.twig', $responseData);
    }

    #[Route('/api/face/verify', name: 'app_face_verify', methods: ['POST'])]
    public function verifyFace(
        Request $request,
        FaceRecognitionService $faceRecognitionService,
        UserRepository $userRepository,
        TokenStorageInterface $tokenStorage
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['descriptor'])) {
            return new JsonResponse(['error' => 'Descriptor requis.'], Response::HTTP_BAD_REQUEST);
        }

        $capturedDescriptor = json_decode($data['descriptor'], true);
        if (!is_array($capturedDescriptor)) {
            return new JsonResponse(['error' => 'Format de descriptor invalide (128 valeurs attendues).'], Response::HTTP_BAD_REQUEST);
        }
        $normalizedCaptured = $this->normalizeNumericList($capturedDescriptor, 128);
        if ($normalizedCaptured === null) {
            return new JsonResponse(['error' => 'Format de descriptor invalide (128 valeurs attendues).'], Response::HTTP_BAD_REQUEST);
        }

        // Scan all active enrolled users to find the closest face match
        $enrolledUsers = $userRepository->findAllWithFaceDescriptor();
        if (empty($enrolledUsers)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun visage enregistré dans le système.',
            ], Response::HTTP_NOT_FOUND);
        }

        $bestUser     = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($enrolledUsers as $candidate) {
            $storedJson = $candidate->getFaceDescriptor();
            if ($storedJson === null || $storedJson === '') {
                continue;
            }
            $stored = json_decode($storedJson, true);
            if (!is_array($stored)) {
                continue;
            }
            $normalizedStored = $this->normalizeNumericList($stored, 128);
            if ($normalizedStored === null) {
                continue;
            }
            $distance = $faceRecognitionService->euclideanDistance($normalizedStored, $normalizedCaptured);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestUser     = $candidate;
            }
        }

        if ($bestUser === null || !$faceRecognitionService->isWithinThreshold($bestDistance)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Visage non reconnu. Veuillez réessayer.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Authenticate: create a session token directly so 2FA is not re-triggered
        // (face recognition is itself a strong second factor).
        $token = new UsernamePasswordToken($bestUser, 'main', $bestUser->getRoles());
        $tokenStorage->setToken($token);

        return new JsonResponse([
            'success'      => true,
            'message'      => 'Visage reconnu avec succès.',
            'redirect_url' => $this->generateUrl('app_dashboard'),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @param array<mixed> $values
     * @return list<float>|null
     */
    private function normalizeNumericList(array $values, int $expectedCount): ?array
    {
        if (\count($values) !== $expectedCount) {
            return null;
        }

        $list = [];
        foreach (array_values($values) as $value) {
            if (!is_int($value) && !is_float($value)) {
                return null;
            }
            $list[] = (float) $value;
        }

        return $list;
    }
}
