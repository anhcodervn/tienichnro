<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Client\Contact\Services\ContactService;
use App\Features\Reporting\Jobs\SendDiscordReport;
use App\Features\Reporting\Services\DiscordReportService;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
});

test('a new topup order queues a privacy safe sales report after commit', function (): void {
    Queue::fake([SendDiscordReport::class]);
    config(['services.discord.channels.sales' => 'https://discord.test/sales']);

    $order = Order::factory()->create([
        'email' => 'private-customer@example.com',
        'normalized_email' => 'private-customer@example.com',
        'game_account' => 'private-game-account',
        'metadata' => ['provider' => ['partner_key' => 'private-partner-key']],
        'total_amount' => 8500,
    ]);

    Queue::assertPushed(SendDiscordReport::class, function (SendDiscordReport $job) use ($order): bool {
        $serialized = serialize($job);

        return $job->channel === 'sales'
            && $job->title === 'Đơn nạp game mới'
            && $job->dedupeKey === "topup-order:{$order->id}:created"
            && $job->details['Mã đơn'] === $order->code
            && $job->details['Số tiền'] === '8.500đ'
            && ! str_contains($serialized, 'private-customer@example.com')
            && ! str_contains($serialized, 'private-game-account')
            && ! str_contains($serialized, 'private-partner-key');
    });
});

test('payment and terminal order changes queue the correct discord bot reports', function (): void {
    $order = Order::factory()->create();
    config([
        'services.discord.channels.sales' => 'https://discord.test/sales',
        'services.discord.channels.provider' => 'https://discord.test/provider',
    ]);
    Queue::fake([SendDiscordReport::class]);

    $order->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
    $order->forceFill(['order_status' => OrderStatus::Completed, 'completed_at' => now()])->save();

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'sales'
        && $job->title === 'Đã nhận thanh toán đơn nạp game'
        && $job->dedupeKey === "topup-order:{$order->id}:paid");
    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'sales'
        && $job->title === 'Đơn nạp game hoàn thành'
        && $job->dedupeKey === "topup-order:{$order->id}:status:completed");

    $failedOrder = Order::factory()->create();
    Queue::fake([SendDiscordReport::class]);
    $failedOrder->forceFill(['order_status' => OrderStatus::Failed, 'failed_at' => now()])->save();

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'provider'
        && $job->title === 'Đơn nạp game xử lý thất bại');
});

test('a refunded order queues one sales report and is excluded from successful daily revenue', function (): void {
    $order = Order::factory()->create([
        'created_at' => '2026-08-22 10:00:00',
        'payment_status' => PaymentStatus::Paid,
        'total_amount' => 8500,
    ]);
    config(['services.discord.channels.sales' => 'https://discord.test/sales']);
    Queue::fake([SendDiscordReport::class]);

    $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'sales'
        && $job->title === 'Đơn nạp game đã hoàn tiền'
        && $job->dedupeKey === "topup-order:{$order->id}:refunded"
        && $job->details['Thanh toán'] === PaymentStatus::Refunded->value);

    config(['services.discord.channels.daily_report' => 'https://discord.test/daily-report']);
    Queue::fake([SendDiscordReport::class]);
    $this->artisan('report:discord-daily-topup', ['--date' => '2026-08-22'])->assertSuccessful();

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'daily_report'
        && $job->dedupeKey === 'topup-daily:2026-08-22'
        && $job->details['Đơn thành công'] === 0
        && $job->details['Lượt nạp thành công'] === 0
        && $job->details['Doanh thu thành công'] === '0đ');
});

test('a new member registration queues an activity report without contact details', function (): void {
    config(['services.discord.channels.activity' => 'https://discord.test/activity']);
    Queue::fake();

    $this->post(route('auth.register.submit'), [
        'username' => 'discordmember',
        'name' => 'Discord Member',
        'email' => 'private-member@example.com',
        'phone' => '0900000000',
        'password' => 'password',
        'password_confirmation' => 'password',
        'accept_terms' => '1',
    ])->assertRedirect(route('auth.login'));

    $user = User::query()->where('username', 'discordmember')->firstOrFail();

    Queue::assertPushed(SendDiscordReport::class, function (SendDiscordReport $job) use ($user): bool {
        $serialized = serialize($job);

        return $job->channel === 'activity'
            && $job->title === 'Người dùng đăng ký mới'
            && $job->dedupeKey === "user:{$user->id}:registered"
            && $job->details['User ID'] === $user->id
            && ! str_contains($serialized, 'private-member@example.com')
            && ! str_contains($serialized, '0900000000');
    });
});

test('a failed recipient queues a provider report without provider or account data', function (): void {
    $order = Order::factory()->create([
        'email' => 'private-customer@example.com',
        'game_account' => 'private-game-account',
    ]);
    $recipient = OrderRecipient::factory()->for($order)->create([
        'recipient_data' => ['game_account' => 'private-recipient-account'],
        'provider_response' => ['partner_key' => 'private-partner-key'],
        'status' => 'processing',
    ]);
    config(['services.discord.channels.provider' => 'https://discord.test/provider']);
    Queue::fake([SendDiscordReport::class]);

    $recipient->forceFill([
        'status' => 'failed',
        'failure_reason' => 'Provider response contains private data',
        'status_check_attempts' => 4,
        'failed_at' => now(),
    ])->save();

    Queue::assertPushed(SendDiscordReport::class, function (SendDiscordReport $job) use ($order): bool {
        $serialized = serialize($job);

        return $job->channel === 'provider'
            && $job->details['Mã đơn'] === $order->code
            && $job->details['Số lần kiểm tra'] === 4
            && ! str_contains($serialized, 'private-customer@example.com')
            && ! str_contains($serialized, 'private-game-account')
            && ! str_contains($serialized, 'private-recipient-account')
            && ! str_contains($serialized, 'private-partner-key')
            && ! str_contains($serialized, 'Provider response contains private data');
    });
});

test('feedback bot queues only a safe admin notification', function (): void {
    config(['services.discord.channels.feedback' => 'https://discord.test/feedback']);
    Queue::fake([SendDiscordReport::class]);

    $feedback = app(ContactService::class)->createFeedback(null, [
        'name' => 'Private Customer',
        'email' => 'private-customer@example.com',
        'phone' => '0900000000',
        'subject' => 'Cần hỗ trợ đơn hàng',
        'content' => 'Nội dung riêng tư không được gửi sang Discord.',
    ]);

    Queue::assertPushed(SendDiscordReport::class, function (SendDiscordReport $job) use ($feedback): bool {
        $serialized = serialize($job);

        return $job->channel === 'feedback'
            && $job->dedupeKey === "feedback:{$feedback->id}:created"
            && ! str_contains($serialized, 'private-customer@example.com')
            && ! str_contains($serialized, '0900000000')
            && ! str_contains($serialized, 'Nội dung riêng tư');
    });
});

test('discord dispatcher removes sensitive labels and blocks mass mentions', function (): void {
    config(['services.discord.channels.sales' => 'https://discord.test/sales']);
    Queue::fake([SendDiscordReport::class]);

    app(DiscordReportService::class)->queue('sales', 'Test report', [
        'Mã đơn' => 'TOP123',
        'Email' => 'private@example.com',
        'partner_key' => 'private-key',
        'Ghi chú' => '@everyone kiểm tra đơn',
    ], 'sanitize-test');

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->details === [
        'Mã đơn' => 'TOP123',
        'Ghi chú' => '＠everyone kiểm tra đơn',
    ]);
});

test('discord report job posts with mentions disabled and retries transport failures', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    Http::preventStrayRequests();
    Http::fake([
        'https://discord.test/sales' => Http::response([], 204),
    ]);
    config(['services.discord.channels.sales' => 'https://discord.test/sales']);
    $job = new SendDiscordReport('sales', 'Báo cáo đơn hàng', ['Mã đơn' => 'TOP123'], 'test-report');

    $job->handle(app(DiscordReportService::class));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.test/sales'
        && $request['allowed_mentions'] === ['parse' => []]
        && str_contains((string) $request['content'], '[SALES]')
        && str_contains((string) $request['content'], 'TOP123'));
    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([10, 60, 300])
        ->and($job->queue)->toBe('default');
});

test('daily report job posts only to its dedicated webhook', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    Http::preventStrayRequests();
    Http::fake([
        'https://discord.test/daily-report' => Http::response([], 204),
        'https://discord.test/sales' => Http::response([], 204),
    ]);
    config([
        'services.discord.channels.daily_report' => 'https://discord.test/daily-report',
        'services.discord.channels.sales' => 'https://discord.test/sales',
    ]);
    $job = new SendDiscordReport('daily_report', 'Báo cáo cuối ngày', [], 'daily-report');

    $job->handle(app(DiscordReportService::class));

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.test/daily-report'
        && str_contains((string) $request['content'], '[DAILY_REPORT]'));
    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://discord.test/sales');
});

test('daily topup report contains only paid completed orders by completion date', function (): void {
    Order::factory()->create([
        'created_at' => '2026-08-21 23:00:00',
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'completed_at' => '2026-08-22 08:00:00',
        'total_amount' => 8500,
        'quantity' => 2,
        'sale_unit_price' => 4250,
        'provider_unit_cost' => 3500,
        'provider_total_cost' => 7000,
        'gross_profit' => 1500,
    ]);
    Order::factory()->create([
        'created_at' => '2026-08-22 09:00:00',
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
        'total_amount' => 17000,
    ]);
    config(['services.discord.channels.daily_report' => 'https://discord.test/daily-report']);
    Queue::fake([SendDiscordReport::class]);

    $this->artisan('report:discord-daily-topup', ['--date' => '2026-08-22'])
        ->expectsOutputToContain('Đã xếp báo cáo Discord ngày 22/08/2026 vào queue.')
        ->assertSuccessful();

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'daily_report'
        && $job->dedupeKey === 'topup-daily:2026-08-22'
        && $job->title === 'Báo cáo topup thành công ngày 22/08/2026'
        && $job->details['Đơn thành công'] === 1
        && $job->details['Lượt nạp thành công'] === 2
        && $job->details['Doanh thu thành công'] === '8.500đ'
        && $job->details['Tổng cost provider'] === '7.000đ'
        && $job->details['Lợi nhuận gộp'] === '1.500đ'
        && $job->details['Biên lợi nhuận'] === '17,6%'
        && $job->details['Đơn thiếu snapshot cost'] === 0
        && $job->details['Giá trị trung bình'] === '8.500đ'
        && ! str_contains(serialize($job), '17.000đ'));
});

test('daily topup report does not fall back to the sales webhook', function (): void {
    config([
        'services.discord.channels.daily_report' => null,
        'services.discord.channels.sales' => 'https://discord.test/sales',
    ]);
    Queue::fake([SendDiscordReport::class]);

    $this->artisan('report:discord-daily-topup', ['--date' => '2026-08-22'])
        ->expectsOutputToContain('Chưa cấu hình DISCORD_WEBHOOK_DAILY_REPORT;')
        ->assertSuccessful();

    Queue::assertNotPushed(SendDiscordReport::class);
});

test('daily report rejects an invalid date', function (): void {
    $this->artisan('report:discord-daily-topup', ['--date' => '2026-02-31'])
        ->expectsOutputToContain('Ngày báo cáo không hợp lệ.')
        ->assertFailed();
});
