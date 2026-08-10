<?php

use App\LtiKey;
use App\LtiRegistration;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| LtiRegistration Factory
|--------------------------------------------------------------------------
|
| Matches the actual lti_registrations schema (confirmed via `describe
| lti_registrations`). lti_key_id defaults to a freshly created LtiKey
| (see LtiKeyFactory.php) - override it explicitly if a test needs to
| reuse a specific one.
|
*/

$factory->define(LtiRegistration::class, function (Faker $faker) {
    return [
        'campus_id' => $faker->unique()->numerify('campus-####'),
        'admin_name' => $faker->name,
        'admin_email' => $faker->unique()->safeEmail,
        'iss' => $faker->unique()->url,
        'api_key' => null,
        'api_secret' => null,
        'auth_login_url' => $faker->url,
        'auth_token_url' => $faker->url,
        'auth_server' => $faker->url,
        'client_id' => $faker->uuid,
        'key_set_url' => $faker->url,
        'kid' => $faker->uuid,
        'lti_key_id' => function () {
            return factory(LtiKey::class)->create()->id;
        },
        'active' => 1,
    ];
});
