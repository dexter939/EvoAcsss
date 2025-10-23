<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

/**
 * FirmwareVersion - Modello per versioni firmware dispositivi CPE
 * FirmwareVersion - Model for CPE device firmware versions
 * 
 * Gestisce il versionamento e deployment di firmware per dispositivi TR-069
 * Manages versioning and deployment of firmware for TR-069 devices
 * 
 * @property string $version Versione firmware (es. 1.2.3) / Firmware version (e.g. 1.2.3)
 * @property string $manufacturer Produttore / Manufacturer
 * @property string $model Modello dispositivo / Device model
 * @property string $file_path Percorso file firmware / Firmware file path
 * @property string $file_hash Hash MD5/SHA256 per integrità / MD5/SHA256 hash for integrity
 * @property int $file_size Dimensione file in bytes / File size in bytes
 * @property bool $is_stable Flag versione stabile / Stable version flag
 */
class FirmwareVersion extends Model
{
    use HasFactory, Auditable;
    
    protected $auditCategory = 'firmware';
    
    /**
     * Campi assegnabili in massa
     * Mass assignable fields
     */
    protected $fillable = [
        'version',
        'manufacturer',
        'model',
        'file_path',
        'file_hash',
        'file_size',
        'release_notes',
        'is_stable',
        'is_active'
    ];

    /**
     * Cast dei tipi di dato
     * Data type casting
     */
    protected $casts = [
        'is_stable' => 'boolean',
        'is_active' => 'boolean',
        'file_size' => 'integer',
    ];
    
    /**
     * Attributi appended per compatibilità test
     * Appended attributes for test compatibility
     */
    protected $appends = ['checksum', 'release_date'];
    
    /**
     * Accessor per checksum (alias di file_hash)
     * Accessor for checksum (alias of file_hash)
     */
    public function getChecksumAttribute(): ?string
    {
        return $this->file_hash;
    }
    
    /**
     * Accessor per release_date (usa created_at)
     * Accessor for release_date (uses created_at)
     */
    public function getReleaseDateAttribute(): ?string
    {
        return $this->created_at?->toDateTimeString();
    }

    /**
     * Relazione con deployment di questo firmware
     * Relationship with deployments of this firmware
     * 
     * @return HasMany
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(FirmwareDeployment::class);
    }

    /**
     * Relazione con compatibility matrix
     * Relationship with compatibility matrix
     */
    public function compatibilities(): HasMany
    {
        return $this->hasMany(FirmwareCompatibility::class);
    }

    /**
     * Get compatible products count
     */
    public function getCompatibleProductsCount(): int
    {
        return $this->compatibilities()
            ->where('compatibility_status', 'compatible')
            ->count();
    }
}
