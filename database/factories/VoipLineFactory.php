<?php

namespace Database\Factories;

use App\Models\VoipLine;
use App\Models\SipProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoipLineFactory extends Factory
{
    protected $model = VoipLine::class;

    public function definition(): array
    {
        $phoneNumber = $this->faker->phoneNumber();
        
        return [
            'sip_profile_id' => SipProfile::factory(),
            'line_instance' => 1,
            'enabled' => true,
            'directory_number' => $phoneNumber,
            'display_name' => $this->faker->name(),
            'sip_uri' => 'sip:' . $phoneNumber . '@' . $this->faker->domainName(),
            'auth_username' => $this->faker->userName(),
            'auth_password' => bcrypt('password'),
            'status' => $this->faker->randomElement(['Disabled', 'Registered', 'Registering', 'Error']),
            'call_state' => 'Idle',
            'last_registration' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'call_waiting_enabled' => true,
            'call_forward_enabled' => false,
            'call_forward_number' => null,
            'call_forward_on_busy' => false,
            'call_forward_on_no_answer' => false,
            'call_forward_no_answer_timeout' => 20,
            'dnd_enabled' => false,
            'caller_id_enable' => true,
            'caller_id_name' => $this->faker->name(),
            'anonymous_call_rejection' => false,
            'phy_interface' => 'FXS1',
            'incoming_calls_received' => 0,
            'incoming_calls_answered' => 0,
            'incoming_calls_failed' => 0,
            'outgoing_calls_attempted' => 0,
            'outgoing_calls_answered' => 0,
            'outgoing_calls_failed' => 0,
            'total_call_time' => 0,
        ];
    }

    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Registered',
            'last_registration' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Disabled',
            'last_registration' => null,
        ]);
    }

    public function withCallForwarding(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_forward_enabled' => true,
            'call_forward_number' => $this->faker->phoneNumber(),
        ]);
    }

    public function doNotDisturb(): static
    {
        return $this->state(fn (array $attributes) => [
            'dnd_enabled' => true,
        ]);
    }
}
