<?php

namespace Database\Factories;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class OtpFactory extends Factory
{
    protected $model = Otp::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'otp_code' => str_pad(fake()->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT),
            'purpose' => 'login',
            'expires_at' => Carbon::now()->addMinutes(10),
            'is_used' => false,
            'used_at' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_used' => true,
            'used_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->addMinutes(10),
            'is_used' => false,
            'used_at' => null,
        ]);
    }

    public function forPurpose(string $purpose): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => $purpose,
        ]);
    }
}
