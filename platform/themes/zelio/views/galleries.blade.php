@php
    Theme::set('pageTitle', __('Galleries'));

    Gallery::registerAssets();
@endphp

<section @class(['section-details pb-100', 'pt-80' => get_header_style() != 2, 'pt-160' => get_header_style() == 2])>
    <div class="container">
        <div class="row">
            <div class="col-xl-8 mx-lg-auto mb-8">
                <div class="text-center">
                    <h3 class="ds-3 mt-3 text-dark">{{ __('Galleries') }}</h3>
                </div>
            </div>
        </div>
        <div class="page-content">
            <article class="post post--single">
                <div class="post__content">
                    @if (isset($galleries) && $galleries->isNotEmpty())
                        <div class="gallery-wrap">
                            @foreach ($galleries as $gallery)
                                <div class="gallery-item">
                                    <div class="img-wrap">
                                        <a href="{{ $gallery->url }}">
                                            {{ RvMedia::image($gallery->image, $gallery->name, 'medium') }}
                                        </a>
                                    </div>
                                    <div class="gallery-detail">
                                        <div class="gallery-title">
                                            <a href="{{ $gallery->url }}">{{ $gallery->name }}</a>
                                        </div>
                                        @if (trim($gallery->user->name))
                                            <div class="gallery-author">{{ __('By :name', ['name' => $gallery->user->name]) }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
            <div class="clearfix"></div>
        </div>
    </div>
</section>
