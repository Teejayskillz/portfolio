@php
    Theme::set('pageTitle', $gallery->name);
@endphp

<section @class(['section-details pb-100', 'pt-80' => get_header_style() != 2, 'pt-160' => get_header_style() == 2])>
    <div class="container">
        <div class="row">
            <div class="col-xl-8 mx-lg-auto mb-8">
                <div class="text-center">
                    <h3 class="ds-3 mt-3 text-dark">{!! BaseHelper::clean($gallery->name) !!}</h3>
                </div>
            </div>
        </div>
        <div class="page-content">
            <article class="post post--single">
                <div class="post__content">
                    <div class="ck-content">
                        {!! BaseHelper::clean($gallery->description) !!}
                    </div>
                    <br>
                    <div id="list-photo">
                        @foreach (gallery_meta_data($gallery) as $image)
                            @continue(! $image)

                            <div
                                class="item"
                                data-src="{{ RvMedia::getImageUrl($imageUrl = Arr::get($image, 'img')) }}"
                                data-sub-html="{{ $description = BaseHelper::clean(Arr::get($image, 'description')) }}"
                            >
                                <div class="photo-item">
                                    <div class="thumb">
                                        <a href="{{ $description }}">
                                            {{ RvMedia::image($imageUrl, $description) }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <br>
                    {!! apply_filters(BASE_FILTER_PUBLIC_COMMENT_AREA, null, $gallery) !!}
                </div>
            </article>
        </div>
    </div>
</section>
