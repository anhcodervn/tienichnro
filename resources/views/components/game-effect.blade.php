@props(['effect', 'name' => 'Hiệu ứng game'])
<div {{ $attributes->class(['grid shrink-0 justify-items-center gap-2']) }} data-game-effect="{{ json_encode($effect, JSON_THROW_ON_ERROR) }}">
    <canvas hidden width="{{ $effect['width'] ?? 146 }}" height="{{ $effect['height'] ?? 197 }}" role="img" aria-label="{{ $name }}" class="h-auto max-w-full [image-rendering:pixelated]">{{ $name }}</canvas>
    <p data-effect-status role="status" class="text-center text-xs text-slate-500">{{ isset($effect['dataUrl']) ? 'Đang tải hiệu ứng…' : 'Chưa có dữ liệu hiệu ứng.' }}</p>
</div>
