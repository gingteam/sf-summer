<?php

namespace App\Controller;

use App\Dto\TelegramUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\TelegramAuthenticator;
use App\Security\TelegramCheckingAuthorization;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        return $this->render('user/login.html.twig');
    }

    #[Route('/login/callback', name: 'app_login_callback')]
    public function callback(
        #[MapQueryString] TelegramUserDto $teleUser,
        UserRepository $userRepo,
        UrlGeneratorInterface $urlGenerator,
        Security $security,
        TelegramCheckingAuthorization $checker,
    ): RedirectResponse {
        if (!$checker->isValid($teleUser)) {
            return new RedirectResponse($urlGenerator->generate('app_login'));
        }

        $user = $userRepo->findOneBy(['telegramId' => $teleUser->id]);

        if (null === $user) {
            $user = (new User())
                ->setName($teleUser->first_name)
                ->setPhoto($teleUser->photo_url)
                ->setTelegramId($teleUser->id);

            $userRepo->save($user, true);
        }

        // @phpstan-ignore-next-line
        return $security->login($user, TelegramAuthenticator::class);
    }

    /** @return void */
    #[Route('/logout', name: 'app_logout')]
    public function logout()
    {
    }

    #[Route('/', name: 'app_user')]
    public function index(): Response
    {
        return $this->render('user/index.html.twig');
    }
}
