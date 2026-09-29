<?php

namespace App\Console\Commands;

use App\Features\Admin\GameService\Actions\BackfillGameServiceOrderSettlementsAction;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class BackfillGameServiceOrderSettlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game-service-orders:backfill-settlements
        {--apply : Ghi snapshot kết toán được tái dựng vào các đơn cũ}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kiểm tra hoặc tái dựng snapshot kết toán cho các đơn dịch vụ game cũ';

    /**
     * Execute the console command.
     */
    public function handle(BackfillGameServiceOrderSettlementsAction $action, TenantContext $tenantContext): int
    {
        $apply = (bool) $this->option('apply');
        $mainTenant = $tenantContext->mainTenant();
        $result = $mainTenant
            ? $tenantContext->run($mainTenant, fn (): array => $action->handle($apply))
            : $action->handle($apply);

        $this->table(['Có thể tái dựng', 'Đã cập nhật', 'Thiếu liên kết giá'], [[
            $result['eligible'],
            $result['updated'],
            $result['missingPrice'],
        ]]);

        if (! $apply && $result['eligible'] > 0) {
            $this->components->warn('Đây là chế độ kiểm tra. Thêm --apply để ghi dữ liệu.');
        }

        if ($apply && $result['updated'] > 0) {
            $this->components->info('Đã tái dựng snapshot bằng giá CTV liên kết và cấu hình thuế hiện tại.');
        }

        if ($result['missingPrice'] > 0) {
            $this->components->warn('Các đơn thiếu liên kết giá không được tự động cập nhật.');
        }

        return self::SUCCESS;
    }
}
