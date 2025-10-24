<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ApiResponse;
use App\Models\VoiceService;
use App\Models\SipProfile;
use App\Models\VoipLine;
use App\Models\CpeDevice;
use App\Jobs\ProvisionVoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VoiceServiceController extends Controller
{
    use ApiResponse;
    public function index(Request $request): JsonResponse
    {
        $query = VoiceService::with(['cpeDevice', 'sipProfiles.voipLines']);

        if ($request->has('cpe_device_id')) {
            $query->where('cpe_device_id', $request->cpe_device_id);
        }

        if ($request->has('protocol')) {
            $query->where('protocol', $request->protocol);
        }

        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        $services = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }

    public function store(Request $request, ?CpeDevice $device = null): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cpe_device_id' => $device ? 'nullable' : 'required|exists:cpe_devices,id',
            'service_instance' => 'nullable|integer',
            'service_type' => ['nullable', Rule::in(['SIP', 'MGCP', 'H.323'])],
            'service_name' => 'nullable|string|max:255',
            'bound_interface' => 'nullable|string',
            'enabled' => 'boolean',
            'protocol' => ['nullable', Rule::in(['SIP', 'MGCP', 'H.323'])],
            'max_profiles' => 'integer|min:1',
            'max_lines' => 'integer|min:1',
            'max_sessions' => 'integer|min:1',
            'capabilities' => 'array',
            'codecs' => 'array',
            'rtp_dscp' => 'integer|between:0,63',
            'rtp_port_min' => 'integer|between:1024,65535',
            'rtp_port_max' => 'integer|between:1024,65535',
            'stun_enabled' => 'boolean',
            'stun_server' => 'nullable|string',
            'stun_port' => 'nullable|integer|between:1,65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Use device ID from route parameter if available
        if ($device) {
            $data['cpe_device_id'] = $device->id;
        }
        
        // Map service_type to both protocol and service_type fields
        if (isset($data['service_type'])) {
            if (!isset($data['protocol'])) {
                $data['protocol'] = $data['service_type'];
            }
            // Keep service_type for database
        }

        // Generate service_instance if not provided
        if (!isset($data['service_instance'])) {
            $maxInstance = VoiceService::where('cpe_device_id', $data['cpe_device_id'])
                ->max('service_instance');
            $data['service_instance'] = $maxInstance ? $maxInstance + 1 : 1;
        }

        $service = VoiceService::create($data);

        return response()->json([
            'success' => true,
            'data' => $service->load('cpeDevice')
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $service = VoiceService::with(['cpeDevice', 'sipProfiles.voipLines'])->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Voice service not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $service = VoiceService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Voice service not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'boolean',
            'protocol' => [Rule::in(['SIP', 'MGCP', 'H.323'])],
            'max_profiles' => 'integer|min:1',
            'max_lines' => 'integer|min:1',
            'max_sessions' => 'integer|min:1',
            'capabilities' => 'array',
            'codecs' => 'array',
            'rtp_dscp' => 'integer|between:0,63',
            'rtp_port_min' => 'integer|between:1024,65535',
            'rtp_port_max' => 'integer|between:1024,65535',
            'stun_enabled' => 'boolean',
            'stun_server' => 'nullable|string',
            'stun_port' => 'nullable|integer|between:1,65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $service->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $service->fresh(['cpeDevice'])
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $service = VoiceService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Voice service not found'
            ], 404);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Voice service deleted successfully'
            ]
        ]);
    }

    public function createSipProfile(Request $request, string $serviceId): JsonResponse
    {
        $service = VoiceService::find($serviceId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Voice service not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'profile_instance' => 'nullable|integer',
            'enabled' => 'boolean',
            'profile_name' => 'required|string|max:255',
            'proxy_server' => 'required|string',
            'proxy_port' => 'required|integer|between:1,65535',
            'registrar_server' => 'required|string',
            'registrar_port' => 'required|integer|between:1,65535',
            'auth_username' => 'nullable|string',
            'auth_password' => 'nullable|string',
            'domain' => 'nullable|string',
            'transport_protocol' => ['required', Rule::in(['UDP', 'TCP', 'TLS'])],
            'register_expires' => 'integer|min:60',
            'codec_list' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['voice_service_id'] = $serviceId;

        $profile = SipProfile::create($data);

        return response()->json([
            'success' => true,
            'data' => $profile
        ], 201);
    }

    public function createVoipLine(Request $request, string $serviceOrProfileId): JsonResponse
    {
        // Check if sip_profile_id is in request (voice-services route)
        if ($request->has('sip_profile_id')) {
            $profileId = $request->input('sip_profile_id');
        } else {
            // Otherwise, use the route parameter (sip-profiles route)
            $profileId = $serviceOrProfileId;
        }

        $profile = SipProfile::find($profileId);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'SIP profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'sip_profile_id' => 'nullable|exists:sip_profiles,id',
            'line_number' => 'nullable|integer',
            'line_instance' => 'nullable|integer',
            'enabled' => 'boolean',
            'directory_number' => 'nullable|string',
            'display_name' => 'nullable|string',
            'sip_uri' => 'required|string',
            'auth_username' => 'required|string',
            'auth_password' => 'required|string',
            'call_waiting_enabled' => 'boolean',
            'call_forward_enabled' => 'boolean',
            'call_forward_number' => 'nullable|string',
            'dnd_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['sip_profile_id'] = $profileId;
        $data['status'] = 'Idle';

        // Remove sip_profile_id from data if it was in request (avoid duplicate)
        unset($data['sip_profile_id']);
        $data['sip_profile_id'] = $profileId;

        $line = VoipLine::create($data);

        return response()->json([
            'success' => true,
            'data' => $line
        ], 201);
    }

    public function getStatistics(Request $request): JsonResponse
    {
        $stats = [
            'total_services' => VoiceService::count(),
            'enabled_services' => VoiceService::where('enabled', true)->count(),
            'total_profiles' => SipProfile::count(),
            'total_lines' => VoipLine::count(),
            'active_lines' => VoipLine::where('status', 'Registered')->count(),
            'by_type' => VoiceService::select('protocol')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('protocol')
                ->get(),
        ];

        return $stats;
    }

    public function provisionService(Request $request, string $id): JsonResponse
    {
        $service = VoiceService::with(['cpeDevice', 'sipProfiles.voipLines'])->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Voice service not found'
            ], 404);
        }

        $device = $service->cpeDevice;

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        if ($device->protocol_type !== 'tr069') {
            return response()->json([
                'success' => false,
                'message' => 'VoIP provisioning only works with TR-069 devices',
                'device_protocol' => $device->protocol_type
            ], 422);
        }

        ProvisionVoiceService::dispatch($service);

        return response()->json([
            'success' => true,
            'data' => [
                'voice_service_id' => $service->id,
                'device_id' => $device->id,
                'service_instance' => $service->service_instance,
                'status' => 'queued'
            ]
        ]);
    }
}
