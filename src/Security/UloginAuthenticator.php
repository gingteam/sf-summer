<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-import-type Ulogin from \App\StaticAnalysis\Type
 */
class UloginAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private HttpClientInterface $client,
        private UserRepository $userRepo,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'app_login' === $request->attributes->get('_route') && 'POST' === $request->getMethod();
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->request->getString('token');

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Missing ulogin token.');
        }

        $response = $this->client->request('GET', 'http://ulogin.ru/token.php?token='.$token);

        /** @var Ulogin */
        $data = $response->toArray();

        return new SelfValidatingPassport(
            new UserBadge($data['uid'], function (string $googleId) use ($data): User {
                $existUser = $this->userRepo->findOneBy(['googleId' => $googleId]);

                if (null !== $existUser) {
                    return $existUser;
                }

                $user = new User();
                $user->setName(sprintf('%s %s', $data['first_name'], $data['last_name']));
                $user->setGoogleId($googleId);

                $this->userRepo->save($user, true);

                return $user;
            }),
            [new RememberMeBadge()]
        );
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
