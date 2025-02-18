<?php

namespace SocialiteProviders\KlasCement;

use SocialiteProviders\Manager\SocialiteWasCalled;

class KlasCementExtendSocialite
{
    /**
     * Register the provider.
     *
     * @param  \SocialiteProviders\Manager\SocialiteWasCalled  $socialiteWasCalled
     * @return void
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('klascement', Provider::class);
    }
}
