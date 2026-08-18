<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity personalizado:
 * - Emite cada registro también al canal Monolog `activity` (JSON rotativo).
 * - Sanitiza propiedades y acota tamaños de strings.
 * - Expone helpers `summary` y `domain` para el frontend.
 */
class Activity extends SpatieActivity
{
    protected $casts = [
        'properties' => AsArrayObject::class,
    ];

    protected $appends = [
        'summary',
        'domain',
    ];

    protected static function booted(): void
    {
        static::created(function (self $activity): void {
            // Espejo en archivo (canal activity) para búsquedas/ELK sin afectar request
            try {
                Log::channel('activity')->info('activity', self::toLogRecord($activity));
            } catch (\Throwable) {
                // Nunca romper por logging
            }
        });
    }

    /** Resumen compacto para mostrar en UI. */
    public function summary(): Attribute
    {
        return Attribute::get(function (): string {
            $subject = $this->subject ? class_basename($this->subject_type).'#'.$this->subject_id : '—';
            $causer = $this->causer ? ($this->causer->name ?? ('User#'.$this->causer_id)) : 'anon';

            return sprintf('[%s] %s — %s — %s', $this->log_name, $this->event ?? '-', $subject, $causer);
        });
    }

    /** Solo el bloque de dominio si existe en properties. */
    public function domain(): Attribute
    {
        return Attribute::get(fn () => $this->properties['domain'] ?? $this->properties['request']['domain'] ?? []);
    }

    /** Construye el payload para Monolog ya sanitizado. */
    private static function toLogRecord(self $activity): array
    {
        $data = [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'event' => $activity->event,
            'description' => $activity->description,
            'batch_uuid' => $activity->batch_uuid,
            'created_at' => optional($activity->created_at)->toISOString(),
            'subject' => [
                'type' => $activity->subject_type,
                'id' => $activity->subject_id,
            ],
            'causer' => [
                'type' => $activity->causer_type,
                'id' => $activity->causer_id,
            ],
            'properties' => self::sanitizeProperties($activity->properties->toArray()),
        ];

        // Reducimos ruido (el archivo ya tendrá muchísimos registros http/CRUD)
        if (isset($data['properties']['request']['payload'])) {
            // En archivo dejamos fuera payloads para no inflar
            unset($data['properties']['request']['payload']);
        }

        return $data;
    }

    /** Enmascara claves sensibles y corta strings largos. */
    private static function sanitizeProperties(array $data): array
    {
        $sensitive = [
            'password', 'password_confirmation', 'current_password',
            'token', 'access_token', 'refresh_token', 'authorization',
            'cookie', 'cookies', 'session', 'api_key', 'secret', 'bearer',
        ];

        $walk = function (&$value, $key) use ($sensitive): void {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $value = '***';

                return;
            }

            if (is_string($value) && mb_strlen($value) > 1000) {
                $value = mb_substr($value, 0, 1000).'…';
            }
        };

        array_walk_recursive($data, $walk);

        return $data;
    }
}
