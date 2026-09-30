@props(['status'])

@php
    $status = (string) $status;
    [$label, $statusClass] = match ($status) {
        'pending' => ['Chờ duyệt', 'border-amber-200 bg-amber-50 text-amber-700'],
        'processing', 'review' => ['Đang thực hiện', 'border-blue-200 bg-blue-50 text-blue-700'],
        'completed' => ['Hoàn thành', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
        'failed', 'cancelled' => ['Trả về/hoàn tiền', 'border-rose-200 bg-rose-50 text-rose-700'],
        default => ['Đang cập nhật', 'border-slate-200 bg-slate-50 text-slate-600'],
    };
@endphp

<span {{ $attributes->class(['inline-flex w-fit rounded-[5px] border px-2.5 py-1 text-xs font-bold', $statusClass]) }}>{{ $label }}</span>
