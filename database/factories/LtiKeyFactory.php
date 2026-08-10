<?php

use App\LtiKey;
use Faker\Generator as Faker;

$factory->define(LtiKey::class, function (Faker $faker) {
    return [
        'private_key_file' => $faker->unique()->uuid . '.key',
        'alg' => 'RS256'
    ];
});
