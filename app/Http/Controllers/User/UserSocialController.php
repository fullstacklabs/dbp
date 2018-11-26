<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\APIController;

use App\Models\User\ProjectOauthProvider;
use App\Models\User\Account;
use App\Models\User\User;

use Laravel\Socialite\Two\BitbucketProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;

use Socialite;

class UserSocialController extends APIController
{


    /**
     *
     * @OAS\Get(
     *     path="/users/login/{driver}",
     *     tags={"Users"},
     *     summary="Add a new oAuth provider to a project",
     *     description="",
     *     operationId="v4_projects_oAuthProvider.store",
     *     @OAS\Parameter(ref="#/components/parameters/version_number"),
     *     @OAS\Parameter(ref="#/components/parameters/key"),
     *     @OAS\Parameter(ref="#/components/parameters/pretty"),
     *     @OAS\Parameter(ref="#/components/parameters/format"),
     *     @OAS\Parameter(
     *          name="driver",
     *          in="path",
     *          required=true,
     *          @OAS\Schema(ref="#/components/schemas/ProjectOauthProvider/properties/name"),
     *          description="The Provider name, the currently supported providers are: facebook, bitbucket, github, & google",
     *     ),
     *     @OAS\Parameter(
     *          name="project_id",
     *          in="query",
     *          required=true,
     *          @OAS\Schema(ref="#/components/schemas/Project/properties/id"),
     *          description="The Project id"
     *     ),
     *     @OAS\Parameter(
     *          name="alt_url",
     *          in="query",
     *          @OAS\Schema(ref="#/components/schemas/ProjectOauthProvider/properties/callback_url_alt"),
     *          description="When this parameter is set, the return will use the alternative callback url"
     *     ),
     *     @OAS\Response(
     *         response=200,
     *         description="successful operation",
     *         @OAS\MediaType(mediaType="application/json", @OAS\Schema(type="string")),
     *         @OAS\MediaType(mediaType="application/xml",  @OAS\Schema(type="string")),
     *         @OAS\MediaType(mediaType="text/x-yaml",      @OAS\Schema(type="string"))
     *     )
     * )
     *
     * @param $provider
     * @return mixed
     *
     */
    public function getSocialRedirect($provider = null)
    {
        $project_id = checkParam('project_id');
        $provider   = checkParam('provider', true, $provider);

        $oAuthDriver = $this->getOauthProvider($project_id, $provider);
        if(!$oAuthDriver) return $this->setStatusCode(404)->replyWithError('Socialite Provider not found');

        return $this->reply([
            'data' => [
                'provider_id'  => $provider,
                'redirect_url' => urldecode($oAuthDriver->stateless()->redirect()->getTargetUrl()),
            ]
        ]);
    }

    public function handleProviderCallback($provider)
    {
        $project_id = checkParam('project_id', true);

        $oAuthDriver = $this->getOauthProvider($project_id, $provider);
        if(!$oAuthDriver) return $this->setStatusCode(404)->replyWithError('Socialite Provider not found');

        return $this->createOrGetUser($oAuthDriver->user(), $provider, $project_id);
    }

    private function getOauthProvider($project_id, $provider)
    {
        $driver = ProjectOauthProvider::where('project_id', $project_id)->where('name', $provider)->first();
        switch ($provider) {
            case 'bitbucket':
                $providerClass = BitbucketProvider::class;
                break;
            case 'facebook':
                $providerClass = FacebookProvider::class;
                break;
            case 'github':
                $providerClass = GithubProvider::class;
                break;
            case 'google':
                $providerClass = GoogleProvider::class;
                break;
            default:
                $providerClass = null;
        }

        if(!$providerClass || !$driver) return null;

        return Socialite::buildProvider($providerClass, [
            'client_id'     => $driver->client_id,
            'client_secret' => $driver->client_secret,
            'redirect'      => $driver->callback_url.'?project_id='.$project_id,
        ])->stateless();
    }

    private function createOrGetUser($providerUser, $provider, $project_id)
    {
        $account = Account::where('provider_id', $provider)->where('provider_user_id', $providerUser->getId())->first();
        if (!$account) {

            $user = User::where('email', $providerUser->getEmail())->first();
            if (!$user) {
                $user = User::create([
                    'id'        => str_random(24),
                    'email'     => $providerUser->getEmail(),
                    'name'      => $providerUser->getName(),
                    'activated' => 1,
                ]);
            }

            Account::create([
                'user_id'          => $user->id,
                'provider_user_id' => $providerUser->getId(),
                'provider_id'      => $provider,
                'project_id'       => $project_id
            ]);

            return $user;
        }
        return $account->user;
    }
}
