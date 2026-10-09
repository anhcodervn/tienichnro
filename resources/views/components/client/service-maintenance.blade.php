@props(['message'])
<div role="status" class="rounded-[10px] border border-amber-300 bg-amber-50 p-6 text-amber-950" data-service-maintenance>
    <p class="flex items-center gap-2 text-lg font-bold"><i class="bx bx-wrench" aria-hidden="true"></i>Dịch vụ đang bảo trì</p>
    <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6">{{ $message }}</p>
    <a href="{{ url()->current() }}" class="mt-4 inline-flex min-h-11 items-center rounded-[10px] border border-amber-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600">Kiểm tra lại</a>
</div>
