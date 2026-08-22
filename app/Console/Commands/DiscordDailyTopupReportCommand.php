<?php

namespace App\Console\Commands;

use App\Features\Reporting\Services\TopupDiscordReporterService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class DiscordDailyTopupReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:discord-daily-topup
        {--date= : Ngày báo cáo theo định dạng YYYY-MM-DD, mặc định là hôm nay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xếp báo cáo tổng hợp đơn nạp game trong ngày vào queue Discord.';

    /**
     * Execute the console command.
     */
    public function handle(TopupDiscordReporterService $discordReporter): int
    {
        try {
            $date = filled($this->option('date'))
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'))
                : CarbonImmutable::now();
        } catch (Throwable) {
            $this->error('Ngày báo cáo không hợp lệ. Hãy dùng định dạng YYYY-MM-DD.');

            return self::FAILURE;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== ($this->option('date') ?: $date->format('Y-m-d'))) {
            $this->error('Ngày báo cáo không hợp lệ. Hãy dùng định dạng YYYY-MM-DD.');

            return self::FAILURE;
        }

        if (! $discordReporter->dailySummary($date)) {
            $this->warn('Chưa cấu hình DISCORD_WEBHOOK_SALES; báo cáo không được xếp queue.');

            return self::SUCCESS;
        }

        $this->info('Đã xếp báo cáo Discord ngày '.$date->format('d/m/Y').' vào queue.');

        return self::SUCCESS;
    }
}
