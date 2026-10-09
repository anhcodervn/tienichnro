<dialog id="client-tools-modal" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-[10px] border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/55 backdrop:backdrop-blur-[2px]" aria-labelledby="client-tools-title" data-client-tools-modal>
    <header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
        <h2 id="client-tools-title" class="text-lg font-extrabold text-slate-950">Công cụ hỗ trợ game</h2>
        <button type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-[5px] border border-slate-300 bg-white text-xl text-slate-600 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-client-tools-close aria-label="Đóng danh sách công cụ" autofocus><i class="bx bx-x" aria-hidden="true"></i></button>
    </header>
    <div class="p-4 sm:p-5">
        <x-client.tool-list :tools="app(\App\Features\Admin\Setting\Services\ToolAvailabilityService::class)->all()" data-client-tools-list />
    </div>
</dialog>
