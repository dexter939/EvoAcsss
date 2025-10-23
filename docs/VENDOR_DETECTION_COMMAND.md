# Vendor Detection Command - Operational Guide

## Overview

Il comando `vendor:detect` esegue l'auto-detection di manufacturer e product per i dispositivi CPE gestiti dal sistema ACS. Questo comando identifica automaticamente il vendor corretto utilizzando OUI prefix, manufacturer name, model name e product class.

## Command Syntax

```bash
php artisan vendor:detect [options]
```

## Available Options

| Option | Description | Example |
|--------|-------------|---------|
| `--all` | Processa tutti i dispositivi nel sistema | `vendor:detect --all` |
| `--unmatched` | Processa solo dispositivi senza vendor match | `vendor:detect --unmatched` |
| `--device=<ID/SN>` | Processa un dispositivo specifico (ID o serial number) | `vendor:detect --device=123` |
| `--force` | Forza re-detection anche su dispositivi già matchati | `vendor:detect --all --force` |

## Usage Examples

### 1. Detection per Dispositivi Non Matchati (Default)

Il comportamento default processa solo i dispositivi che non hanno ancora un vendor match:

```bash
php artisan vendor:detect
# OR
php artisan vendor:detect --unmatched
```

**Output:**
```
╔══════════════════════════════════════════════════════════════╗
║         Vendor Auto-Detection for CPE Devices              ║
╚══════════════════════════════════════════════════════════════╝

Found 15 device(s) to process.

 15/15 [████████████████████████████████] 100%

╔══════════════════════════════════════════════════════════════╗
║                   Detection Summary                        ║
╚══════════════════════════════════════════════════════════════╝

┌────────────────────────┬───────┬────────────┐
│ Metric                 │ Count │ Percentage │
├────────────────────────┼───────┼────────────┤
│ Total Processed        │ 15    │ 100%       │
│ ✓ Successfully Detected│ 12    │ 80.0%      │
│ ✗ Failed               │ 3     │ 20.0%      │
│ ↷ Skipped              │ 0     │ 0%         │
└────────────────────────┴───────┴────────────┘

✓ Successfully detected vendor information for 12 device(s)
✗ Failed to detect vendor information for 3 device(s)
```

### 2. Detection per Tutti i Dispositivi

Processa tutti i dispositivi nel sistema, inclusi quelli già matchati:

```bash
php artisan vendor:detect --all
```

**Use Case:** Dopo aver aggiunto nuovi manufacturer/product al database vendor library.

### 3. Re-Detection Forzata

Forza la re-detection anche su dispositivi che hanno già un vendor match:

```bash
php artisan vendor:detect --all --force
```

**Use Case:**
- Dopo aggiornamento algoritmo di detection
- Dopo correzione di OUI database
- Per validare accuracy del matching

### 4. Detection per Dispositivo Specifico

Processa un singolo dispositivo identificato per ID o Serial Number:

```bash
# Per ID dispositivo
php artisan vendor:detect --device=123

# Per Serial Number
php artisan vendor:detect --device=SN-ABC123XYZ
```

**Use Case:**
- Troubleshooting specifico dispositivo
- Test dopo modifica manuale manufacturer info

## Integration with Cron/Scheduler

### Scheduled Automatic Detection

Aggiungi al file `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run vendor detection ogni notte per nuovi dispositivi
    $schedule->command('vendor:detect --unmatched')
             ->dailyAt('02:00')
             ->withoutOverlapping()
             ->runInBackground();
}
```

### Production Monitoring

```bash
# Configurazione Supervisor per execution monitoring
[program:vendor-detection]
command=php /path/to/artisan vendor:detect --all
directory=/path/to/project
autostart=false
autorestart=false
startsecs=0
stdout_logfile=/var/log/vendor-detection.log
```

## Detection Algorithm

Il comando utilizza `VendorDetectionService` che implementa:

1. **OUI Prefix Matching** (85% confidence)
   - Lookup IEEE OUI database
   - Match esatto con manufacturer OUI

2. **Manufacturer Name Fuzzy Matching** (85% threshold)
   - Levenshtein distance calculation
   - Case-insensitive comparison

3. **Model Name Matching**
   - Exact match su product model_name
   - Partial match con similarity threshold

4. **Product Class Matching**
   - TR-069 ProductClass parameter
   - Vendor-specific identifier

## Success Criteria

| Status | Condition | Action |
|--------|-----------|--------|
| **Success** | Device manufacturer e model_name aggiornati | Contatori incrementati |
| **Failed** | Nessun match trovato nel vendor library | Log warning, continua |
| **Skipped** | Device già matchato, --force non presente | Skip processing |

## Error Handling

### Common Errors

#### 1. No Devices Found
```
No devices found matching the specified criteria.
```
**Solution:** Verifica filtri applicati o aggiungi dispositivi al database.

#### 2. Vendor Library Empty
```
Failed to detect vendor information for N device(s)
```
**Solution:** Popolare vendor library con manufacturer/product.

#### 3. Database Connection Error
```
Operation failed: SQLSTATE[...]
```
**Solution:** Verifica connessione database e credenziali.

## Performance Considerations

### Batch Size Recommendations

| Device Count | Recommended Execution | Estimated Time |
|--------------|----------------------|----------------|
| < 100 | Immediate execution | < 10 seconds |
| 100 - 1,000 | Background job | 1-2 minutes |
| 1,000 - 10,000 | Scheduled overnight | 10-20 minutes |
| > 10,000 | Chunked processing | 1-2 hours |

### Memory Usage

Il comando processa dispositivi uno alla volta con progress bar, minimizzando l'utilizzo di memoria:

- **Peak Memory:** ~50MB per batch di 1000 devices
- **Database Queries:** Ottimizzato con select specifici campi
- **Progress Tracking:** Streaming output, no buffer accumulation

## Monitoring & Logging

### Log Output Locations

```bash
# Laravel log standard
tail -f storage/logs/laravel.log | grep "Vendor detection"

# Filtro detection events
grep -E "(Successfully detected|Failed to detect)" storage/logs/laravel.log
```

### Metrics Tracking

Il comando traccia metriche utili per monitoring:

```php
// Esempio output JSON per integrazione monitoring systems
{
  "total_processed": 100,
  "success_count": 85,
  "failure_count": 15,
  "skipped_count": 0,
  "success_rate": 85.0,
  "execution_time_seconds": 45.3
}
```

## Troubleshooting Guide

### Issue: Low Success Rate (<70%)

**Diagnosi:**
```bash
# Verifica vendor library completeness
php artisan tinker
>>> App\Models\RouterManufacturer::count()
>>> App\Models\RouterProduct::count()
```

**Solution:**
- Popolare vendor library con manufacturer/product mancanti
- Verificare OUI prefix database aggiornato

### Issue: Command Timeout

**Diagnosi:**
```bash
# Controlla numero dispositivi
php artisan vendor:detect --all --dry-run
```

**Solution:**
- Dividere processing in batch con `--device`
- Aumentare `max_execution_time` in php.ini
- Usare scheduled jobs per processing notturno

### Issue: Memory Exhausted

**Diagnosi:**
```bash
# Monitor memory durante execution
watch -n 1 'ps aux | grep "artisan vendor:detect"'
```

**Solution:**
- Ridurre batch size processando subset di devices
- Aumentare `memory_limit` in php.ini temporaneamente
- Implementare chunked processing per >100K devices

## Best Practices

### 1. Pre-Detection Checklist

- ✅ Vendor library popolata (min 10+ manufacturers)
- ✅ OUI database aggiornato
- ✅ Database backup completato
- ✅ Test su subset dispositivi (`--device`)

### 2. Production Execution

- ✅ Esegui durante maintenance window (off-peak hours)
- ✅ Monitor progress con `tail -f storage/logs/laravel.log`
- ✅ Verifica success rate (target: >80%)
- ✅ Review failed devices manualmente

### 3. Post-Detection Validation

```bash
# Verifica dispositivi matchati
php artisan tinker
>>> CpeDevice::whereNotNull('manufacturer')->whereNotNull('model_name')->count()

# Lista dispositivi non matchati
>>> CpeDevice::whereNull('manufacturer')->orWhereNull('model_name')->count()
```

## Integration with CI/CD

### Automated Testing

```yaml
# .github/workflows/test.yml
- name: Test Vendor Detection Command
  run: |
    php artisan migrate:fresh --seed
    php artisan vendor:detect --all
    php artisan test --filter=VendorDetectionCommandTest
```

### Deployment Hook

```bash
# Dopo deploy, run detection su nuovi dispositivi
php artisan migrate
php artisan vendor:detect --unmatched
```

## API Alternative

Per integration programmatica, usa gli endpoint API REST:

```bash
# Bulk vendor detection via API
curl -X POST https://acs.example.com/api/v1/vendors/bulk/detect \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"device_ids": [1, 2, 3]}'
```

## Support & Feedback

Per issue o feature request sul comando vendor detection:
- **Log Location:** `storage/logs/laravel.log`
- **Debug Mode:** `php artisan vendor:detect --device=X -vvv`
- **GitHub Issues:** Repository ACS project

---

**Version:** 1.0.0  
**Last Updated:** 2025-10-23  
**Maintainer:** ACS Development Team
