<?php
namespace App\Controller;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class ApiLoginController extends AbstractController
{
    private $authenticationUtils;
    private $jwtManager;
    private $userProvider;
    private $passwordEncoder;

    public function __construct(
        AuthenticationUtils $authenticationUtils,
        JWTTokenManagerInterface $jwtManager,
        UserProviderInterface $userProvider,
        UserPasswordHasherInterface $passwordEncoder
    ) {
        $this->authenticationUtils = $authenticationUtils;
        $this->jwtManager = $jwtManager;
        $this->userProvider = $userProvider;
        $this->passwordEncoder = $passwordEncoder;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST', 'OPTIONS'])]
    public function login(Request $request): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', Response::HTTP_OK);
        }
        
        $data = json_decode($request->getContent(), true);
        $email = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        try {
            $user = $this->userProvider->loadUserByIdentifier($email);
        } catch (UserNotFoundException $e) {
            return new Response(json_encode([
                'errors' => ['message' => 'User not found'],
            ]), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }
        
        // check if user has armin role
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return new Response(json_encode([
                'errors' => ['message' => 'You are not authorized to access this resource'],
            ]), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        if (!$user instanceof PasswordAuthenticatedUserInterface || !$this->passwordEncoder->isPasswordValid($user, $password)) {
            return new Response(json_encode([
                'errors' => ['message' => 'Invalid credentials'],
            ]), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        $jwt = $this->jwtManager->create($user);

        return new Response(json_encode([
            'username' => $user->getName(),
            'token' => $jwt,
            'message' => 'You have successfully logged in',
        ]), Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }
}
