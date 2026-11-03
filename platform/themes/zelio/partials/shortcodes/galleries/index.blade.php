<div class="sidebar-widget widget-instagram widget-gallery {{ $shortcode->background_color ? '' : 'bg-white' }}"
    @if($shortcode->background_color || $shortcode->margin_top || $shortcode->margin_bottom)
        style="
            {{ $shortcode->background_color ? 'background-color: ' . $shortcode->background_color . ';' : '' }}
            {{ $shortcode->margin_top ? 'margin-top: ' . $shortcode->margin_top . 'px;' : '' }}
            {{ $shortcode->margin_bottom ? 'margin-bottom: ' . $shortcode->margin_bottom . 'px;' : '' }}
        "
    @endif
>
    <div class="widget-header-2 font-primary mt-5 mb-5 text-center">
        @if ($subtitle = $shortcode->subtitle)
            <div class="d-flex align-center justify-content-center pt-5">
                @if ($iconImage = $shortcode->icon_image)
                    {{ RvMedia::image($iconImage, 'icon', attributes: ['class' => 'icon']) }}
                @elseif ($icon = $shortcode->icon)
                    <x-core::icon class="mr-5" :name="$icon" />
                @endif

                <span class="widget-subtitle position-relative text-primary">
                    {!! BaseHelper::clean($subtitle) !!}
                </span>
            </div>
        @endif

        @if ($title = $shortcode->title)
            <h3 class="widget-title mt-5 mb-0">
                {!! BaseHelper::clean($title) !!}
            </h3>
        @endif

        @if ($description = $shortcode->description)
            <span class="font-small text-muted">
                {!! BaseHelper::clean($description) !!}
            </span>
        @endif
    </div>
    @if ($galleries->isNotEmpty())
        <ul class="alith-row alith-gap-0 overflow-hidden">
            @foreach($galleries as $gallery)
                <li class="alith-col alith-col-6">
                    <a href="{{ $gallery->url }}">
                        {{ RvMedia::image($gallery->image, $gallery->name, 'medium-thumb') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
