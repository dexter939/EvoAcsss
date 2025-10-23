<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

/**
 * ConfigurationProfile - Modello per profili di configurazione dispositivi
 * ConfigurationProfile - Model for device configuration profiles
 * 
 * Contiene template di parametri TR-181 per provisioning zero-touch
 * Contains TR-181 parameter templates for zero-touch provisioning
 * 
 * @property string $name Nome profilo / Profile name
 * @property string $description Descrizione profilo / Profile description
 * @property array $parameters Parametri TR-181 (JSON chiave-valore) / TR-181 parameters (JSON key-value)
 */
class ConfigurationProfile extends Model
{
    use Auditable;
    
    protected $auditCategory = 'configuration';
    
    /**
     * Campi assegnabili in massa
     * Mass assignable fields
     */
    protected $fillable = [
        'name',
        'description',
        'parameters',
        'is_active'
    ];

    /**
     * Cast dei tipi di dato
     * Data type casting
     */
    protected $casts = [
        'parameters' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Relazione con dispositivi che usano questo profilo
     * Relationship with devices using this profile
     * 
     * @return HasMany
     */
    public function devices(): HasMany
    {
        return $this->hasMany(CpeDevice::class);
    }
}
