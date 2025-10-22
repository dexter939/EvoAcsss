<?php

namespace Database\Factories;

use App\Models\SipProfile;
use App\Models\VoiceService;
use Illuminate\Database\Eloquent\Factories\Factory;

class SipProfileFactory extends Factory
{
    protected $model = SipProfile::class;

    public function definition(): array
    {
        return [
            'voice_service_id' => VoiceService::factory(),
            'profile_instance' => 1,
            'enabled' => true,
            'profile_name' => 'Default Profile',
            'proxy_server' => $this->faker->domainName(),
            'proxy_port' => 5060,
            'registrar_server' => $this->faker->domainName(),
            'registrar_port' => 5060,
            'outbound_proxy' => $this->faker->domainName(),
            'outbound_proxy_port' => 5060,
            'auth_username' => $this->faker->userName(),
            'auth_password' => bcrypt('password'),
            'domain' => $this->faker->domainName(),
            'realm' => $this->faker->domainName(),
            'transport_protocol' => $this->faker->randomElement(['UDP', 'TCP', 'TLS']),
            'register_expires' => 3600,
            'register_retry' => true,
            'register_retry_interval' => 30,
            'codec_list' => ['PCMU', 'PCMA', 'G729'],
            'packetization_period' => 20,
            'silence_suppression' => false,
            'dtmf_method' => 'RFC2833',
            'dtmf_payload_type' => 101,
            'sip_dscp' => 26,
            'vlan_id' => null,
            'vlan_priority' => null,
        ];
    }

    public function udpTransport(): static
    {
        return $this->state(fn (array $attributes) => [
            'transport_protocol' => 'UDP',
        ]);
    }

    public function tcpTransport(): static
    {
        return $this->state(fn (array $attributes) => [
            'transport_protocol' => 'TCP',
        ]);
    }

    public function tlsTransport(): static
    {
        return $this->state(fn (array $attributes) => [
            'transport_protocol' => 'TLS',
            'proxy_port' => 5061,
            'registrar_port' => 5061,
        ]);
    }
}
