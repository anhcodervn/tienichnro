<?php

namespace App\Console\Commands;

use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTenancyReadiness extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:check {--strict : Yêu cầu chế độ multi-site đã được bật}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kiểm tra schema, dữ liệu và domain trước khi bật mô hình website mẹ - con';

    /**
     * Execute the console command.
     */
    public function handle(TenantContext $tenantContext): int
    {
        if (! $tenantContext->isSchemaReady()) {
            $this->components->error('Schema multi-site chưa đầy đủ. Hãy chạy php artisan migrate trước.');

            return self::FAILURE;
        }

        $issues = [];
        $mainTenants = DB::table('tenants')->where('is_main', true)->get(['id', 'name']);

        if ($mainTenants->count() !== 1) {
            $issues[] = ['Website mẹ', "Cần đúng 1 bản ghi, hiện có {$mainTenants->count()}"];
        }

        $mainTenantId = $mainTenants->first()?->id;
        $mainDomain = strtolower((string) config('tenancy.main_domain', 'napcarot.com'));

        if ($mainTenantId !== null && ! DB::table('tenant_domains')
            ->where('tenant_id', $mainTenantId)
            ->where('domain', $mainDomain)
            ->where('is_primary', true)
            ->where('is_verified', true)
            ->exists()) {
            $issues[] = ['Domain mẹ', "{$mainDomain} chưa được xác minh làm domain chính"];
        }

        foreach (['users', 'orders', 'wallets', 'wallet_transactions', 'payment_transactions', 'support_conversations', 'support_messages'] as $tableName) {
            $nullCount = DB::table($tableName)->whereNull('tenant_id')->count();

            if ($nullCount > 0) {
                $issues[] = [$tableName, number_format($nullCount).' bản ghi chưa có tenant_id'];
            }
        }

        $invalidBillingAccounts = DB::table('tenants as child')
            ->leftJoin('users as billing_user', 'billing_user.id', '=', 'child.billing_user_id')
            ->where('child.is_main', false)
            ->where(function ($query) use ($mainTenantId): void {
                $query->whereNull('child.billing_user_id')
                    ->orWhereNull('billing_user.id')
                    ->when($mainTenantId !== null, fn ($nested) => $nested->orWhere('billing_user.tenant_id', '!=', $mainTenantId));
            })
            ->count();

        if ($invalidBillingAccounts > 0) {
            $issues[] = ['Tài khoản giá vốn', "{$invalidBillingAccounts} site con chưa liên kết đúng user NapCarot"];
        }

        if ($this->option('strict') && ! $tenantContext->isActive()) {
            $issues[] = ['TENANCY_ENABLED', 'Chưa được bật trong cấu hình runtime'];
        }

        if ($issues !== []) {
            $this->components->error('Multi-site chưa sẵn sàng.');
            $this->table(['Hạng mục', 'Vấn đề'], $issues);

            return self::FAILURE;
        }

        $this->components->info("Schema và dữ liệu multi-site hợp lệ. Domain mẹ: {$mainDomain}.");

        if (! $tenantContext->isActive()) {
            $this->components->warn('Hệ thống đang ở chế độ tương thích. Có thể bật TENANCY_ENABLED=true sau khi kiểm tra ingress và cache config.');
        }

        return self::SUCCESS;
    }
}
