<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialAccountFactory extends Factory
{
    public function definition(): array
    {
        return ['client_id' => Client::factory(), 'platform' => 'instagram', 'name' => fake()->company(), 'handle' => fake()->userName()];
    }
}
