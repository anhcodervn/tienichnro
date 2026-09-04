@extends('client.layouts.app')

@section('title', 'Chương trình cộng tác viên')
@section('description', 'Giới thiệu chính sách cộng tác viên, mức hoa hồng theo gói game và quy trình nhận thưởng minh bạch.')
@section('robots', 'index,follow')

@section('content')
    @php
        $minimumConversion = number_format((int) $affiliatePolicy['minimum_conversion'], 0, ',', '.');
        $minimumWithdrawal = number_format((int) $affiliatePolicy['minimum_withdrawal'], 0, ',', '.');
    @endphp

    <section class="overflow-hidden border-b border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-amber-50">
        <div class="client-container grid gap-10 py-14 lg:grid-cols-[minmax(0,1.15fr)_minmax(22rem,.85fr)] lg:items-center lg:py-20">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-emerald-700 shadow-sm">
                    <i class="bx bx-group text-lg" aria-hidden="true"></i>
                    Affiliate minh bạch
                </span>
                <h1 class="mt-5 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-5xl">
                    Giới thiệu bạn bè,<br class="hidden sm:block"> nhận hoa hồng thật
                </h1>
                <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                    Chia sẻ đường dẫn riêng của bạn. Khi khách được giới thiệu mua gói game thành công, hoa hồng được ghi nhận theo chính sách của website và tự động duyệt sau thời gian an toàn.
                </p>
                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    <a class="client-button min-h-12 justify-center gap-2 px-6" href="{{ route('auth.register') }}">
                        <i class="bx bx-user-plus text-xl" aria-hidden="true"></i> Đăng ký để bắt đầu
                    </a>
                    <a class="client-button-secondary min-h-12 justify-center gap-2 bg-white px-6" href="{{ route('auth.login') }}">
                        <i class="bx bx-log-in-circle text-xl" aria-hidden="true"></i> Đã có tài khoản
                    </a>
                </div>
                <p class="mt-4 flex items-start gap-2 text-sm leading-6 text-slate-500">
                    <i class="bx bx-shield-quarter mt-0.5 text-lg text-emerald-600" aria-hidden="true"></i>
                    Không mất phí tham gia. Mỗi website quản lý và chi trả hoa hồng độc lập.
                </p>
            </div>

            <div class="grid gap-3 rounded-[18px] border border-slate-200 bg-white p-4 shadow-xl shadow-emerald-950/5 sm:grid-cols-2">
                <article class="rounded-[14px] bg-emerald-600 p-5 text-white sm:col-span-2">
                    <i class="bx bx-time-five text-2xl" aria-hidden="true"></i>
                    <p class="mt-5 text-sm font-semibold text-emerald-50">Thời gian đảm bảo</p>
                    <p class="mt-1 text-3xl font-extrabold">{{ $affiliatePolicy['holding_days'] }} ngày</p>
                    <p class="mt-2 text-sm leading-6 text-emerald-50">Tính từ lúc đơn đã thanh toán và hoàn tất.</p>
                </article>
                <article class="rounded-[14px] bg-slate-950 p-5 text-white">
                    <i class="bx bx-transfer-alt text-2xl text-emerald-300" aria-hidden="true"></i>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Đổi sang ví web</p>
                    <p class="mt-1 text-2xl font-extrabold">Từ {{ $minimumConversion }}đ</p>
                </article>
                <article class="rounded-[14px] bg-amber-100 p-5 text-amber-950">
                    <i class="bx bx-money-withdraw text-2xl text-amber-700" aria-hidden="true"></i>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-amber-700">Rút về ngân hàng</p>
                    <p class="mt-1 text-2xl font-extrabold">Từ {{ $minimumWithdrawal }}đ</p>
                </article>
            </div>
        </div>
    </section>

    <section class="client-container py-14 sm:py-20">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Cách hoạt động</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Ba bước để nhận hoa hồng</h2>
        </div>
        <div class="mt-9 grid gap-4 md:grid-cols-3">
            <article class="client-card p-6">
                <span class="grid h-11 w-11 place-items-center rounded-[10px] bg-emerald-100 font-extrabold text-emerald-800">01</span>
                <h3 class="mt-5 text-lg font-extrabold text-slate-950">Chia sẻ liên kết</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Đăng nhập dashboard để lấy mã và đường dẫn giới thiệu riêng. Lượt giới thiệu được ghi nhớ trong {{ $affiliatePolicy['referral_cookie_days'] }} ngày.</p>
            </article>
            <article class="client-card p-6">
                <span class="grid h-11 w-11 place-items-center rounded-[10px] bg-blue-100 font-extrabold text-blue-800">02</span>
                <h3 class="mt-5 text-lg font-extrabold text-slate-950">Khách mua gói game</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Khách đăng ký qua liên kết, thanh toán và hoàn tất đơn. Mức hoa hồng được chốt theo gói tại thời điểm tạo đơn.</p>
            </article>
            <article class="client-card p-6">
                <span class="grid h-11 w-11 place-items-center rounded-[10px] bg-amber-100 font-extrabold text-amber-800">03</span>
                <h3 class="mt-5 text-lg font-extrabold text-slate-950">Nhận và sử dụng tiền</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Sau {{ $affiliatePolicy['holding_days'] }} ngày an toàn, tiền vào ví affiliate để đổi sang ví web hoặc gửi yêu cầu rút về ngân hàng.</p>
            </article>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-slate-50">
        <div class="client-container py-14">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Chính sách hiện hành</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Mức hoa hồng theo gói game</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Hoa hồng có thể là số tiền cố định theo mỗi sản phẩm hoặc phần trăm trên tổng tiền thực trả.</p>
                </div>
                <span class="w-fit rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">Cập nhật theo cấu hình website</span>
            </div>

            @if ($affiliateRates !== [])
                <div class="mt-8 overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead class="bg-slate-950 text-white">
                                <tr>
                                    <th class="px-5 py-4 font-semibold">Game</th>
                                    <th class="px-5 py-4 font-semibold">Gói</th>
                                    <th class="px-5 py-4 font-semibold">Cách tính</th>
                                    <th class="px-5 py-4 text-right font-semibold">Hoa hồng</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($affiliateRates as $rate)
                                    <tr>
                                        <td class="px-5 py-4 font-semibold text-slate-700">{{ $rate['game'] }}</td>
                                        <td class="px-5 py-4 text-slate-600">{{ $rate['package'] }}</td>
                                        <td class="px-5 py-4 text-slate-500">{{ $rate['type'] === 'fixed' ? 'Cố định theo số lượng' : 'Theo tổng tiền thực trả' }}</td>
                                        <td class="px-5 py-4 text-right text-base font-extrabold text-emerald-700">
                                            @if ($rate['type'] === 'fixed')
                                                {{ number_format((int) $rate['fixed_amount'], 0, ',', '.') }}đ / sản phẩm
                                            @else
                                                {{ rtrim(rtrim(number_format((float) $rate['percentage'], 2, '.', ''), '0'), '.') }}%
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="mt-8 rounded-[14px] border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
                    Website đang cập nhật mức hoa hồng cho từng gói. Bạn có thể đăng ký trước và theo dõi chính sách mới nhất tại trang này.
                </div>
            @endif
        </div>
    </section>

    <section class="client-container grid gap-8 py-14 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)] lg:items-start">
        <div class="rounded-[18px] bg-slate-950 p-7 text-white sm:p-9">
            <i class="bx bx-check-shield text-4xl text-emerald-300" aria-hidden="true"></i>
            <h2 class="mt-5 text-2xl font-extrabold">Nguyên tắc duyệt minh bạch</h2>
            <ul class="mt-6 grid gap-4 text-sm leading-6 text-slate-300">
                <li class="flex gap-3"><i class="bx bx-check mt-0.5 text-xl text-emerald-300" aria-hidden="true"></i><span>Chỉ tính cho đơn đã thanh toán và hoàn tất thành công.</span></li>
                <li class="flex gap-3"><i class="bx bx-check mt-0.5 text-xl text-emerald-300" aria-hidden="true"></i><span>Đơn hoàn tiền, bị hủy hoặc hết hạn sẽ không được nhận hoa hồng.</span></li>
                <li class="flex gap-3"><i class="bx bx-check mt-0.5 text-xl text-emerald-300" aria-hidden="true"></i><span>Khoản có dấu hiệu bất thường có thể được tạm giữ để đối soát thủ công.</span></li>
                <li class="flex gap-3"><i class="bx bx-check mt-0.5 text-xl text-emerald-300" aria-hidden="true"></i><span>Hoa hồng của website này không liên thông với bất kỳ website nào khác.</span></li>
            </ul>
        </div>

        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Nhận tiền</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Chọn cách phù hợp với bạn</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <article class="client-card p-6">
                    <i class="bx bx-wallet text-3xl text-blue-600" aria-hidden="true"></i>
                    <h3 class="mt-4 font-extrabold text-slate-950">Đổi sang ví chính</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Từ {{ $minimumConversion }}đ, xử lý ngay và dùng số dư để mua gói trên chính website này.</p>
                </article>
                <article class="client-card p-6">
                    <i class="bx bx-building-house text-3xl text-emerald-600" aria-hidden="true"></i>
                    <h3 class="mt-4 font-extrabold text-slate-950">Rút về ngân hàng</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Từ {{ $minimumWithdrawal }}đ, cần xác minh email, khai báo tài khoản nhận tiền và chờ admin duyệt.</p>
                </article>
            </div>
            <p class="mt-6 text-sm leading-6 text-slate-500">Việc tham gia đồng nghĩa bạn tuân thủ <a class="font-bold text-emerald-700 hover:text-emerald-800" href="{{ route('content.terms') }}">điều khoản sử dụng</a> và không tạo giao dịch gian lận hoặc tự giới thiệu sai mục đích.</p>
        </div>
    </section>

    <section class="bg-emerald-600">
        <div class="client-container flex flex-col justify-between gap-6 py-10 text-white sm:flex-row sm:items-center">
            <div>
                <h2 class="text-2xl font-extrabold">Sẵn sàng trở thành cộng tác viên?</h2>
                <p class="mt-2 text-sm text-emerald-50">Tạo tài khoản, lấy liên kết riêng và bắt đầu chia sẻ ngay hôm nay.</p>
            </div>
            <a class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[5px] bg-white px-6 font-bold text-emerald-800 shadow-sm transition hover:bg-emerald-50" href="{{ route('auth.register') }}">
                Tham gia miễn phí <i class="bx bx-right-arrow-alt text-xl" aria-hidden="true"></i>
            </a>
        </div>
    </section>
@endsection
