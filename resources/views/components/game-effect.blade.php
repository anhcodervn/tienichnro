@props(['effect', 'name' => 'Hiệu ứng game', 'fallback' => null, 'fallbackAlternative' => null])
<div {{ $attributes->class(['grid shrink-0 justify-items-center gap-2']) }} data-game-effect="{{ json_encode($effect, JSON_THROW_ON_ERROR) }}">
    @if($fallback)<img src="{{ $fallback }}" @if($fallbackAlternative) data-game-data-fallback="{{ $fallbackAlternative }}" @endif alt="{{ $name }}" data-game-data-image data-effect-fallback loading="lazy" decoding="async" class="max-h-48 max-w-full object-contain [image-rendering:pixelated]">@endif
    <canvas hidden width="{{ $effect['width'] ?? 146 }}" height="{{ $effect['height'] ?? 197 }}" role="img" aria-label="{{ $name }}" class="h-auto max-w-full [image-rendering:pixelated]">{{ $name }}</canvas>
    <p data-effect-status role="status" class="text-center text-xs text-slate-500">{{ isset($effect['dataUrl']) ? 'Đang tải hiệu ứng…' : 'Chưa có dữ liệu hiệu ứng.' }}</p>
</div>
