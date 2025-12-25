<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();
        
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'domain' => fake()->domainName(),
            'subdomain' => Str::slug($name),
            'settings' => [
                'timezone' => 'UTC',
                'locale' => 'en',
            ],
            'is_active' => true,
            'max_devices' => fake()->numberBetween(100, 10000),
            'max_users' => fake()->numberBetween(5, 100),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], $settings),
        ]);
    }

    public function smallTenant(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_devices' => 50,
            'max_users' => 5,
        ]);
    }

    public function enterpriseTenant(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_devices' => 100000,
            'max_users' => 1000,
        ]);
    }
}
