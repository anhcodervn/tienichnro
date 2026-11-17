@props(['title', 'description', 'seo' => null])

<section class="client-container py-6 sm:py-8" data-tool-page>
    <div class="grid w-full min-w-0 grid-cols-1 gap-6 sm:gap-8">
        <x-client.tool-hero :title="$title" :description="$description" :seo="$seo">{{ $actions ?? '' }}</x-client.tool-hero>
        <div class="grid w-full min-w-0 grid-cols-1 gap-6" data-tool-workspace>{{ $slot }}</div>
        <x-client.tool-seo :seo="$seo" :embedded="true" />
        @if($seo && ($seo['show_article'] ?? true))
            <x-client.seo-faq :items="$seo['faq']" id="tool-seo-faq" class="mt-4" />
        @endif
    </div>
</section>
