<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            static::logAudit($model, 'CREATE');
        });

        static::updated(function ($model) {
            static::logAudit($model, 'UPDATE');
        });

        static::deleted(function ($model) {
            static::logAudit($model, 'DELETE');
        });
    }

    protected static function logAudit($model, $action)
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $oldValues = null;
        $newValues = null;

        // Skip sensitive fields
        $hidden = array_merge($model->getHidden() ?? [], ['password', 'remember_token', 'ledger_pin']);

        if ($action === 'CREATE') {
            $newValues = collect($model->getAttributes())
                ->except($hidden)
                ->toArray();
        } elseif ($action === 'UPDATE') {
            $changes = $model->getChanges();
            
            // Only log if actual attributes changed (ignoring updated_at)
            $relevantChanges = collect($changes)->except(array_merge($hidden, ['updated_at']));
            if ($relevantChanges->isEmpty()) {
                return;
            }

            $oldValues = [];
            $newValues = [];
            
            foreach ($relevantChanges as $key => $value) {
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $value;
            }
        } elseif ($action === 'DELETE') {
            $oldValues = collect($model->getAttributes())
                ->except($hidden)
                ->toArray();
        }

        AuditLog::create([
            'user_id' => Auth::check() ? Auth::id() : null,
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
