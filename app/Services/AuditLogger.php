<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public const ACTIONS = [
        'auth.login.success',
        'auth.login.failed',
        'auth.logout',
        'password.reset.requested',
        'password.reset.completed',
        'tenant.lifecycle.changed',
        'invitation.created',
        'invitation.accepted',
        'invitation.revoked',
        'user.updated',
        'user.deactivated',
        'impersonation.started',
        'impersonation.ended',
        'audit.exported',
        'tenant.suspended',
        'tenant.restored',
        'tenant.terminated',
        'tenant.provisioned',
        'theme.staged',
        'theme.published',
        'theme.reset',
        'theme.template_changed',
        'cms.menu_created',
        'cms.menu_updated',
        'cms.menu_deleted',
        'cms.menu_reordered',
        'cms.media_uploaded',
        'cms.media_updated',
        'cms.media_deleted',
        'sub_reseller.invited',
        'sub_reseller.invitation_resent',
        'sub_reseller.activated',
        'sub_reseller.approved',
        'sub_reseller.rejected',
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?Request $request = null,
        ?int $tenantId = null,
        ?Model $actor = null,
    ): AuditLog {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('Unknown audit action: '.$action);
        }

        $context = app(TenantContext::class);
        $tenantId ??= $context->get()?->id;

        $metadata = $this->redact($metadata);
        $actor ??= $request?->user();

        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->getKey(),
            'impersonator_type' => null,
            'impersonator_id' => null,
            'action' => $action,
            'subject_type' => $subject ? $this->subjectType($subject) : 'system',
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->header('X-Request-ID') ?? (string) \Illuminate\Support\Str::uuid(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function subjectType(Model $subject): string
    {
        return match (true) {
            $subject instanceof \App\Models\User => 'user',
            $subject instanceof \App\Models\UserInvitation => 'invitation',
            $subject instanceof \App\Models\Tenant => 'tenant',
            $subject instanceof \App\Models\Reseller => 'reseller',
            default => strtolower(class_basename($subject)),
        };
    }

    private function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/(password|secret|token|card|bank|account|cvv|pin)/i', $key)) {
                $redacted[$key] = '[REDACTED]';
            } else {
                $redacted[$key] = $this->redact($item);
            }
        }

        return $redacted;
    }
}
