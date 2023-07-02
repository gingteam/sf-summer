<?php

namespace App\Controller;

use App\Dto\TelegramUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\TelegramAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire('%env(BOT_TOKEN)%')] string $bot_token,
        UserRepository $userRepo,
        UrlGeneratorInterface $urlGenerator,
        Security $security,
    ): RedirectResponse {
        $data = [];
        $vars = get_object_vars($teleUser);
        foreach ($vars as $key => $value) {
            $data[] = sprintf('%s=%s', $key, $value);
        }
        sort($data);
        $data = implode("\n", $data);

        $secret_key = hash('sha256', $bot_token, true);
        $isValid = hash_hmac('sha256', $data, $secret_key) === $teleUser->getHash();

        if (!$isValid) {
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

        $security->login($user, TelegramAuthenticator::class);

        return new RedirectResponse($urlGenerator->generate('app_home'));
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
