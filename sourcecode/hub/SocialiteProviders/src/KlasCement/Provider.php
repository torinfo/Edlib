<?php

namespace SocialiteProviders\KlasCement;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    const IDENTIFIER = 'KLASCEMENT';

    /**
     * {@inheritdoc}
     */
    protected $scopes = ['name email'];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://www.klascement.net/oauth/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://www.klascement.net/oauth/token';
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $response = $this->getAccessTokenResponse($this->getCode());
        $this->credentialsResponseBody = $response;
        $token = $this->parseAccessToken($response);

        $this->user = $this->mapUserToObject($this->getUserByToken(
            $token_response = $response
        ));

        if ($this->user instanceof User) {
            $this->user->setAccessTokenResponseBody($this->credentialsResponseBody);
        }

        return $this->user->setToken($token)
            ->setRefreshToken($this->parseRefreshToken($response))
            ->setExpiresIn($this->parseExpiresIn($response))
            ->setApprovedScopes($this->parseApprovedScopes($response));
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token_response)
    {
        $response = $this->getHttpClient()->
            get('https://www.klascement.net/api/users/' . $token_response['user_id'] . '?access_token=' . $token_response['access_token']);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
	$user = $user['content']['objects'][0];
        return (new User)->setRaw($user)->map([
            'id'       => $user['id'],
            'name'     => $user['first_name'] . ' ' . $user['surname'],
            'email'    => $user['email'],
        ]);
    }
}
