<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Logging de modelos optimizado para el dominio:
 * - Loguea SOLO atributos relevantes (fillable) y SOLO si cambiaron (dirty).
 * - Excluye timestamps y campos sensibles.
 * - Adjunta etiqueta legible y contexto de dominio (examen/caso/pregunta/...).
 * - Registra diff (before/after) compacto.
 */
trait ActivityLogsTrait
{
    use LogsActivity;

    /** Campos ruidosos/sensibles a excluir si aparecieran en fillable. */
    protected array $activityHidden = [
        'password', 'remember_token', 'two_factor_secret',
        'created_at', 'updated_at', 'deleted_at',
        'email_verified_at', 'last_login_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        // Tomamos los fillable del modelo, menos los ocultos/sensibles
        $attributes = $this->activityLogAttributes();

        return LogOptions::defaults()
            ->useLogName($this->activityLogName())
            ->logOnly($attributes)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => $this->activityDescriptionForEvent($event));
    }

    public function tapActivity(ActivityContract $activity, string $eventName): void
    {
        /** @var Model $model */
        $model = $this;

        // Etiqueta legible (preferimos nombre/titulo/descripcion/email... y fallback a "Clase#id")
        $label = $this->activityLabel();

        // Diff compacto: old/new solo de atributos logueados que cambiaron
        $changed = array_keys($model->getChanges());
        $logAttrs = $this->activityLogAttributes();
        $interesting = array_values(array_intersect($changed, $logAttrs));

        $diff = [];
        foreach ($interesting as $attr) {
            $diff[$attr] = [
                'old' => $this->sanitizeValue($model->getOriginal($attr)),
                'new' => $this->sanitizeValue($model->getAttribute($attr)),
            ];
        }

        // Contexto de dominio a partir del modelo
        $domain = $this->domainContextFromModel();

        // Datos adicionales
        $extras = [
            'label' => $label,
            'table' => $model->getTable(),
            'class' => $model::class,
            'key' => $model->getKey(),
            'domain' => $domain,
            'diff' => $diff,
            'dirty_keys' => $interesting,
        ];

        $activity->properties = $activity->properties
            ->merge($extras);
    }

    /** Nombre para log_name en Spatie (por ejemplo: "model:examenes"). */
    protected function activityLogName(): string
    {
        return 'model:'.$this->getTable();
    }

    /** Descripción human-friendly del evento. */
    protected function activityDescriptionForEvent(string $event): string
    {
        $label = $this->activityLabel();

        return match ($event) {
            'created' => "Creación de {$label}",
            'updated' => "Actualización de {$label}",
            'deleted' => "Eliminación de {$label}",
            'restored' => "Restauración de {$label}",
            'forceDeleted' => "Eliminación definitiva de {$label}",
            default => Str::headline($event)." de {$label}",
        };
    }

    /** Retorna un label legible basado en atributos comunes. */
    protected function activityLabel(): string
    {
        $candidates = ['nombre', 'titulo', 'descripcion', 'email', 'name', 'subject'];
        foreach ($candidates as $c) {
            $v = $this->getAttribute($c);
            if (is_string($v) && $v !== '') {
                return sprintf('%s "%s"', class_basename($this), Str::limit($v, 80));
            }
        }

        return sprintf('%s#%s', class_basename($this), $this->getKey());
    }

    /** Atributos a registrar: fillable - hidden/sensibles. */
    protected function activityLogAttributes(): array
    {
        $fillable = property_exists($this, 'fillable') ? (array) $this->fillable : [];
        $fillable = array_values(array_unique($fillable));

        return array_values(array_diff($fillable, $this->activityHidden));
    }

    /** Contexto de dominio estándar para tu app desde el modelo. */
    protected function domainContextFromModel(): array
    {
        $keys = ['examen_id', 'caso_id', 'pregunta_id', 'seccion_id', 'tabla_cotejo_id', 'item_id', 'pseudonimo_id'];
        $out = [];

        foreach ($keys as $k) {
            $val = $this->getAttribute($k);
            if ($val !== null && $val !== '') {
                $out[$k] = is_numeric($val) ? (int) $val : $val;
            }
        }

        return $out;
    }

    protected function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            if (Str::of($value)->lower()->contains(['password', 'token', 'secret'])) {
                return '***';
            }

            return Str::limit($value, 500);
        }

        if (is_array($value)) {
            return Arr::map($value, fn ($v) => $this->sanitizeValue($v));
        }

        return $value;
    }
}
