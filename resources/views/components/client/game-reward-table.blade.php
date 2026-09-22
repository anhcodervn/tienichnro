@props(['game'])

<section class="home-reward-card" aria-labelledby="game-reward-title">
    <header class="border-b border-slate-200 bg-white p-4 sm:p-5">
        <p class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.14em] text-cyan-700">
            <i class="bx bx-gift text-base" aria-hidden="true"></i>Giá trị nhận trong game
        </p>
        <h2 id="game-reward-title" class="mt-1 text-xl font-extrabold text-slate-950">Bảng thực nhận {{ $game->name }}</h2>
        <p id="game-reward-description" class="mt-2 text-sm leading-6 text-slate-600">
            Dữ liệu được cập nhật từ các gói đang mở bán. Khuyến mãi thực tế có thể thay đổi theo máy chủ và thời điểm xử lý.
        </p>
    </header>

    @if ($game->packages->isNotEmpty())
        <div class="home-table-scroll" tabindex="0" role="region" aria-label="Bảng thực nhận {{ $game->name }}">
            <table class="home-price-table home-reward-table" aria-describedby="game-reward-description">
                <caption class="sr-only">Giá trị nhận theo từng gói nạp {{ $game->name }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Gói nạp</th>
                        <th scope="col">Mệnh giá</th>
                        <th scope="col">Cơ bản</th>
                        <th scope="col">KM X2</th>
                        <th scope="col">KM X3</th>
                        <th scope="col">Nạp đầu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($game->packages as $package)
                        <tr>
                            <th scope="row">{{ $package->name }}</th>
                            <td>{{ number_format((int) $package->denomination, 0, ',', '.') }}đ</td>
                            <td>{{ $package->rewardDisplay('base_amount', 1, $game->reward_label) ?: '—' }}</td>
                            <td>{{ $package->rewardDisplay('reward_x2_amount', 1, $game->reward_label) ?: '—' }}</td>
                            <td>{{ $package->rewardDisplay('reward_x3_amount', 1, $game->reward_label) ?: '—' }}</td>
                            <td>{{ $package->rewardDisplay('first_topup_reward_amount', 1, $game->reward_label) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="px-4 py-10 text-center text-sm text-slate-500">Bảng thực nhận đang được cập nhật.</p>
    @endif
</section>
