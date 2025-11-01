<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\Auditable;
use App\Models\Scopes\UserDeviceScope;

/**
 * CpeDevice - Modello per dispositivi CPE (Customer Premises Equipment)
 * CpeDevice - Model for CPE (Customer Premises Equipment) devices
 * 
 * Rappresenta un dispositivo CPE gestito dal sistema ACS via TR-069 o TR-369 (USP)
 * Represents a CPE device managed by the ACS system via TR-069 or TR-369 (USP)
 * 
 * @property string $serial_number Numero seriale univoco dispositivo / Unique device serial number
 * @property string $protocol_type Protocollo: tr069, tr369 / Protocol: tr069, tr369
 * @property string $usp_endpoint_id Endpoint ID USP univoco (TR-369) / Unique USP Endpoint ID (TR-369)
 * @property string $mqtt_client_id Client ID MQTT per transport / MQTT Client ID for transport
 * @property string $websocket_client_id Client ID WebSocket per transport / WebSocket Client ID for transport
 * @property \DateTime $websocket_connected_at Timestamp connessione WebSocket / WebSocket connection timestamp
 * @property \DateTime $last_websocket_ping Ultimo ping WebSocket / Last WebSocket ping
 * @property string $mtp_type Message Transfer Protocol: mqtt, websocket, stomp, coap, uds
 * @property string $oui Organizationally Unique Identifier (IEEE)
 * @property string $product_class Classe prodotto TR-069 / TR-069 product class
 * @property string $manufacturer Produttore dispositivo / Device manufacturer
 * @property string $connection_request_url URL per richieste ACS->CPE / URL for ACS->CPE requests
 * @property string $status Stato: online, offline, provisioning, error
 * @property \DateTime $last_inform Ultimo messaggio Inform ricevuto / Last Inform message received
 * @property array $device_info Informazioni aggiuntive dispositivo (JSON) / Additional device info (JSON)
 * @property array $usp_capabilities Capabilities USP supportate (JSON) / Supported USP capabilities (JSON)
 */
class CpeDevice extends Model
{
    use HasFactory, SoftDeletes, Auditable;
    
    protected $auditCategory = 'device';

    /**
     * Campi assegnabili in massa
     * Mass assignable fields
     */
    protected $fillable = [
        'serial_number',
        'protocol_type',
        'usp_endpoint_id',
        'mqtt_client_id',
        'websocket_client_id',
        'websocket_connected_at',
        'last_websocket_ping',
        'mtp_type',
        'oui',
        'product_class',
        'manufacturer',
        'model_name',
        'hardware_version',
        'software_version',
        'connection_request_url',
        'connection_request_username',
        'connection_request_password',
        'auth_method',
        'ip_address',
        'mac_address',
        'status',
        'last_inform',
        'last_contact',
        'service_id',
        'configuration_profile_id',
        'device_info',
        'wan_info',
        'wifi_info',
        'usp_capabilities',
        'is_active',
        'notes'
    ];

    /**
     * Cast dei tipi di dato
     * Data type casting
     */
    protected $casts = [
        'device_info' => 'array',
        'wan_info' => 'array',
        'wifi_info' => 'array',
        'usp_capabilities' => 'array',
        'is_active' => 'boolean',
        'last_inform' => 'datetime',
        'last_contact' => 'datetime',
        'websocket_connected_at' => 'datetime',
        'last_websocket_ping' => 'datetime',
    ];

    /**
     * Bootstrap model - Apply global scopes
     * 
     * SECURITY: UserDeviceScope automatically filters devices by user access
     * Super admins bypass this scope automatically
     * 
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new UserDeviceScope);
    }

    /**
     * Relazione con servizio multi-tenant
     * Relationship with multi-tenant service
     * 
     * @return BelongsTo
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Relazione con profilo configurazione
     * Relationship with configuration profile
     * 
     * @return BelongsTo
     */
    public function configurationProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigurationProfile::class);
    }

    /**
     * Relazione con Data Model TR-069/369
     * Relationship with TR-069/369 Data Model
     * 
     * @return BelongsTo
     */
    public function dataModel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TR069DataModel::class, 'data_model_id');
    }

    /**
     * Relazione con parametri TR-181 del dispositivo
     * Relationship with device TR-181 parameters
     * 
     * @return HasMany
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(DeviceParameter::class);
    }

    /**
     * Relazione con task di provisioning
     * Relationship with provisioning tasks
     * 
     * @return HasMany
     */
    public function provisioningTasks(): HasMany
    {
        return $this->hasMany(ProvisioningTask::class);
    }

    /**
     * Relazione con comandi accodati (NAT Traversal)
     * Relationship with pending commands (NAT Traversal)
     * 
     * @return HasMany
     */
    public function pendingCommands(): HasMany
    {
        return $this->hasMany(PendingCommand::class);
    }

    /**
     * Relazione con test diagnostici TR-143
     * Relationship with TR-143 diagnostic tests
     * 
     * @return HasMany
     */
    public function diagnosticTests(): HasMany
    {
        return $this->hasMany(DiagnosticTest::class);
    }

    /**
     * Relazione con deployment firmware
     * Relationship with firmware deployments
     * 
     * @return HasMany
     */
    public function firmwareDeployments(): HasMany
    {
        return $this->hasMany(FirmwareDeployment::class);
    }

    /**
     * Relazione con sottoscrizioni USP (TR-369)
     * Relationship with USP subscriptions (TR-369)
     * 
     * @return HasMany
     */
    public function uspSubscriptions(): HasMany
    {
        return $this->hasMany(UspSubscription::class);
    }

    /**
     * Relazione con alarms real-time
     * Relationship with real-time alarms
     * 
     * @return HasMany
     */
    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class, 'device_id');
    }

    /**
     * Ottieni alarm attivi per questo dispositivo
     * Get active alarms for this device
     * 
     * @return HasMany
     */
    public function activeAlarms(): HasMany
    {
        return $this->alarms()->where('status', 'active');
    }

    /**
     * Relazione con cronologia eventi dispositivo
     * Relationship with device event history
     * 
     * @return HasMany
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class);
    }

    /**
     * Relazione con utenti che hanno accesso a questo dispositivo (Multi-Tenant)
     * Relationship with users who have access to this device (Multi-Tenant)
     * 
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_devices',
            'cpe_device_id',
            'user_id'
        )->withPivot('role', 'department')
          ->withTimestamps();
    }

    /**
     * Verifica se un utente ha accesso a questo dispositivo
     * Check if a user has access to this device
     * 
     * @param int|User $user User ID or model instance
     * @param string|null $minRole Minimum required role (viewer, manager, admin)
     * @return bool
     */
    public function hasUserAccess(int|User $user, ?string $minRole = null): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        
        $query = $this->users()->where('users.id', $userId);
        
        if ($minRole) {
            // Role hierarchy: admin > manager > viewer
            $roleHierarchy = [
                'viewer' => ['viewer', 'manager', 'admin'],
                'manager' => ['manager', 'admin'],
                'admin' => ['admin']
            ];
            
            $allowedRoles = $roleHierarchy[$minRole] ?? [];
            $query->whereIn('user_devices.role', $allowedRoles);
        }
        
        return $query->exists();
    }

    /**
     * Relazione con deployment units (TR-157)
     * Relationship with deployment units
     * 
     * @return HasMany
     */
    public function deploymentUnits(): HasMany
    {
        return $this->hasMany(\App\Models\DeploymentUnit::class);
    }

    /**
     * Relazione con execution units (TR-157)
     * Relationship with execution units
     * 
     * @return HasMany
     */
    public function executionUnits(): HasMany
    {
        return $this->hasMany(\App\Models\ExecutionUnit::class);
    }

    /**
     * Scope per filtrare dispositivi TR-069
     * Scope to filter TR-069 devices
     */
    public function scopeTr069($query)
    {
        return $query->where('protocol_type', 'tr069');
    }

    /**
     * Scope per filtrare dispositivi TR-369 (USP)
     * Scope to filter TR-369 (USP) devices
     */
    public function scopeTr369($query)
    {
        return $query->where('protocol_type', 'tr369');
    }

    /**
     * Scope per filtrare per tipo protocollo
     * Scope to filter by protocol type
     */
    public function scopeByProtocol($query, string $protocol)
    {
        return $query->where('protocol_type', $protocol);
    }

    /**
     * Scope per filtrare dispositivi USP con MQTT
     * Scope to filter USP devices with MQTT transport
     */
    public function scopeUspMqtt($query)
    {
        return $query->where('protocol_type', 'tr369')
                     ->where('mtp_type', 'mqtt');
    }

    /**
     * Scope per filtrare dispositivi USP con WebSocket
     * Scope to filter USP devices with WebSocket transport
     */
    public function scopeUspWebSocket($query)
    {
        return $query->where('protocol_type', 'tr369')
                     ->where('mtp_type', 'websocket');
    }

    /**
     * Scope per dispositivi WebSocket connessi
     * Scope for connected WebSocket devices
     * 
     * Considera un device connesso se:
     * - Ha un last_websocket_ping recente (< 5 min), oppure
     * - Se ping è null, usa websocket_connected_at recente (< 5 min)
     */
    public function scopeWebSocketConnected($query)
    {
        $timeout = now()->subMinutes(5);
        
        return $query->where('mtp_type', 'websocket')
                     ->where(function($q) use ($timeout) {
                         $q->where('last_websocket_ping', '>', $timeout)
                           ->orWhere(function($subq) use ($timeout) {
                               $subq->whereNull('last_websocket_ping')
                                    ->where('websocket_connected_at', '>', $timeout);
                           });
                     });
    }

    /**
     * Verifica se il dispositivo usa TR-369/USP
     * Check if device uses TR-369/USP
     */
    public function isUsp(): bool
    {
        return $this->protocol_type === 'tr369';
    }

    /**
     * Verifica se il dispositivo usa TR-069
     * Check if device uses TR-069
     */
    public function isTr069(): bool
    {
        return $this->protocol_type === 'tr069';
    }

    /**
     * Relazione con servizi VoIP TR-104
     * Relationship with TR-104 VoIP services
     * 
     * @return HasMany
     */
    public function voiceServices(): HasMany
    {
        return $this->hasMany(VoiceService::class);
    }

    /**
     * Relazione con servizi Storage TR-140
     * Relationship with TR-140 Storage services
     * 
     * @return HasMany
     */
    public function storageServices(): HasMany
    {
        return $this->hasMany(StorageService::class);
    }

    /**
     * Relazione con device capabilities TR-111
     * Relationship with TR-111 Device Capabilities
     * 
     * @return HasMany
     */
    public function deviceCapabilities(): HasMany
    {
        return $this->hasMany(DeviceCapability::class);
    }

    /**
     * Relazione con LAN devices TR-64
     * Relationship with TR-64 LAN Devices
     * 
     * @return HasMany
     */
    public function lanDevices(): HasMany
    {
        return $this->hasMany(LanDevice::class);
    }

    /**
     * Relazione con smart home devices TR-181 IoT
     * Relationship with TR-181 IoT Smart Home Devices
     * 
     * @return HasMany
     */
    public function smartHomeDevices(): HasMany
    {
        return $this->hasMany(SmartHomeDevice::class);
    }

    /**
     * Relazione con IoT services TR-181
     * Relationship with TR-181 IoT Services
     * 
     * @return HasMany
     */
    public function iotServices(): HasMany
    {
        return $this->hasMany(IotService::class);
    }

    public function femtocellConfig()
    {
        return $this->hasOne(FemtocellConfig::class);
    }

    public function stbServices()
    {
        return $this->hasMany(StbService::class);
    }

    /**
     * Relazione con network clients connessi (LAN/WiFi)
     * Relationship with connected network clients (LAN/WiFi)
     * 
     * @return HasMany
     */
    public function networkClients(): HasMany
    {
        return $this->hasMany(NetworkClient::class, 'device_id');
    }
}
