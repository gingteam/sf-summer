<?php

namespace App\Security;

use App\Dto\TelegramUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TelegramAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        /** @var ObjectNormalizer */
        private NormalizerInterface $objectNormalizer,
        private UserRepository $userRepository,
        private TelegramCheckingAuthorization $checker
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'app_login_callback' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        try {
            /** @var TelegramUserDto */
            $teleUser = $this->objectNormalizer->denormalize($request->query->all(), TelegramUserDto::class);
        } catch (\Throwable $e) {
            throw new AuthenticationException($e->getMessage());
        }

        if (!$this->checker->isValid($teleUser)) {
            throw new AuthenticationException('Bad signature.');
        }

        return new SelfValidatingPassport(new UserBadge($teleUser->id, function () use ($teleUser): User {
            $user = $this->userRepository->findOneBy(['telegramId' => $teleUser->id]);

            if (null !== $user) {
                // update name of user if first_name has changed.
                if ($user->getName() !== $teleUser->first_name) {
                    $user->setName($teleUser->first_name);

                    $this->userRepository->save($user, true);
                }

                return $user;
            }

            $user = (new User())
                ->setTelegramId($teleUser->id)
                ->setName($teleUser->first_name)
                ->setPhoto($teleUser->photo_url)
            ;

            $this->userRepository->save($user, true);

            return $user;
        }), [new RememberMeBadge()]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        // TODO: Implement onAuthenticationFailure() method.
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
    {
        /*
         * If you would like this class to control what happens when an anonymous user accesses a
         * protected page (e.g. redirect to /login), uncomment this method and make this class
         * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
         *
         * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
         */
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
