<div class="sidebar-widget widget-instagram widget-gallery bg-white">
    <div class="widget-header-2 font-primary mt-5 mb-5 text-center">
        @if ($subtitle = Arr::get($config, 'subtitle'))
            <div class="d-flex align-center justify-content-center">
                @if ($iconImage = Arr::get($config, 'icon_image'))
                    {{ RvMedia::image($iconImage, 'icon', attributes: ['class' => 'icon']) }}
                @elseif ($icon = Arr::get($config, 'icon'))
                    <x-core::icon class="mr-5" :name="$icon" />
                @endif

                <span class="widget-subtitle position-relative text-primary">{!! BaseHelper::clean($subtitle) !!}</span>
            </div>
        @endif

        @if ($title = Arr::get($config, 'title'))
            <h3 class="widget-title mt-5 mb-0">{!! BaseHelper::clean($title) !!}</h3>
        @endif

        @if ($description = Arr::get($config, 'description'))
            <span class="font-small text-muted">{!! BaseHelper::clean($description) !!}</span>
        @endif
    </div>
    @if ($galleries->isNotEmpty())
        <ul class="alith-row alith-gap-0 overflow-hidden">
            @foreach($galleries as $gallery)
                <li class="alith-col alith-col-6">
                    <a target="_blank" href="{{ $gallery->url }}">
                        {{ RvMedia::image($gallery->image, $gallery->name, 'medium-thumb') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
