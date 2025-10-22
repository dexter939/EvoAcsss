<?php

namespace Database\Factories;

use App\Models\VoiceService;
use App\Models\CpeDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoiceServiceFactory extends Factory
{
    protected $model = VoiceService::class;

    public function definition(): array
    {
        return [
            'cpe_device_id' => CpeDevice::factory(),
            'service_instance' => 1,
            'service_name' => 'VoiceService1',
            'service_type' => 'VoIP',
            'enabled' => true,
            'protocol' => $this->faker->randomElement(['SIP', 'MGCP', 'H.323']),
            'max_profiles' => 4,
            'max_lines' => 8,
            'max_sessions' => 2,
            'capabilities' => ['SIP'],
            'codecs' => ['G.711', 'G.729', 'G.722', 'Opus'],
            'rtp_dscp' => 46,
            'rtp_port_min' => 10000,
            'rtp_port_max' => 20000,
            'stun_enabled' => false,
            'stun_server' => null,
            'stun_port' => 3478,
        ];
    }

    public function sipProtocol(): static
    {
        return $this->state(fn (array $attributes) => [
            'protocol' => 'SIP',
            'capabilities' => ['SIP'],
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
