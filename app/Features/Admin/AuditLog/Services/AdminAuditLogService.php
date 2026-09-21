<?php

namespace App\Features\Admin\AuditLog\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Stringable;

class AdminAuditLogService
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'secret',
        'api_key',
        'private_key',
        'client_secret',
        'connection_config',
        'custom_css',
        'custom_js',
        'custom_head_tags',
        'custom_script',
        'signature',
    ];

    public function latestId(): int
    {
        return (int) (AdminAuditLog::query()->max('id') ?? 0);
    }

    public function recordRequest(
        Request $request,
        User $admin,
        int $statusCode,
        int $durationMs,
        string $requestId,
        int $previousAuditId,
    ): void {
        $method = strtoupper($request->method());
        $requestContext = [
            'request_id' => $requestId,
            'route_name' => $request->route()?->getName(),
            'method' => $method,
            'path' => Str::limit('/'.$request->path(), 1000, ''),
            'status_code' => $statusCode,
            'duration_ms' => max(0, $durationMs),
        ];
        $detailedLogs = AdminAuditLog::query()
            ->where('id', '>', $previousAuditId)
            ->where('admin_id', $admin->id)
            ->whereNotIn('action', ['admin_request_read', 'admin_request_write'])
            ->get();

        if ($detailedLogs->isNotEmpty()) {
            $detailedLogs->each(fn (AdminAuditLog $auditLog) => $auditLog->forceFill($requestContext)->save());

            return;
        }

        $payload = $request->isMethodSafe()
            ? ['query' => $this->sanitize($request->query())]
            : ['input' => $this->sanitize($request->all())];

        $routeParameters = $this->sanitize($request->route()?->parameters() ?? []);

        if ($routeParameters !== []) {
            $payload['route_parameters'] = $routeParameters;
        }

        AdminAuditLog::query()->create([
            'admin_id' => $admin->id,
            ...$requestContext,
            'action' => $request->isMethodSafe() ? 'admin_request_read' : 'admin_request_write',
            'subject_type' => 'admin_request',
            'subject_id' => 0,
            'old_values' => null,
            'new_values' => $payload,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 2000, ''),
        ]);
    }

    /**
     * @param  array{search?:string,admin_id?:int,action?:string,method?:string,status_code?:int,date_from?:string,date_to?:string,per_page?:int}  $filters
     * @return array{logs:LengthAwarePaginator<int, AdminAuditLog>,filter_options:array{admins:mixed,actions:mixed}}
     */
    public function paginate(array $filters): array
    {
        $query = AdminAuditLog::query()
            ->with('admin:id,name,full_name,email,username')
            ->when($filters['admin_id'] ?? null, fn ($builder, int $adminId) => $builder->where('admin_id', $adminId))
            ->when($filters['action'] ?? null, fn ($builder, string $action) => $builder->where('action', $action))
            ->when($filters['method'] ?? null, fn ($builder, string $method) => $builder->where('method', $method))
            ->when($filters['status_code'] ?? null, fn ($builder, int $statusCode) => $builder->where('status_code', $statusCode))
            ->when($filters['date_from'] ?? null, fn ($builder, string $date) => $builder->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($builder, string $date) => $builder->where('created_at', '<=', $date.' 23:59:59'))
            ->when($filters['search'] ?? null, function ($builder, string $search): void {
                $builder->where(function ($nested) use ($search): void {
                    $like = '%'.$search.'%';
                    $nested->where('action', 'like', $like)
                        ->orWhere('route_name', 'like', $like)
                        ->orWhere('path', 'like', $like)
                        ->orWhere('subject_type', 'like', $like)
                        ->orWhere('ip', 'like', $like)
                        ->orWhereHas('admin', function ($adminQuery) use ($like): void {
                            $adminQuery->where('name', 'like', $like)
                                ->orWhere('full_name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('username', 'like', $like);
                        });
                });
            })
            ->latest('id');

        $logs = $query->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();

        return [
            'logs' => $logs,
            'filter_options' => [
                'admins' => User::query()
                    ->where('role', 'admin')
                    ->orderBy('name')
                    ->get(['id', 'name', 'full_name', 'email', 'username']),
                'actions' => AdminAuditLog::query()
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action'),
            ],
        ];
    }

    private function sanitize(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($depth >= 5) {
            return '[MAX_DEPTH]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'file_name' => Str::limit($value->getClientOriginalName(), 255, ''),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if ($value instanceof Model) {
            return ['type' => $value::class, 'id' => $value->getKey()];
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach (array_slice($value, 0, 100, true) as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitize($itemValue, (string) $itemKey, $depth + 1);
            }

            if (count($value) > 100) {
                $sanitized['_truncated_items'] = count($value) - 100;
            }

            return $sanitized;
        }

        if (is_string($value) || $value instanceof Stringable) {
            return Str::limit((string) $value, 2000, '...');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '['.get_debug_type($value).']';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::of($key)->lower()->replace(['-', '.'], '_')->toString();

        return collect(self::SENSITIVE_KEYS)->contains(
            fn (string $sensitive): bool => $normalized === $sensitive || str_ends_with($normalized, '_'.$sensitive),
        );
    }
}
