<?php

use Botble\Base\Forms\FieldOptions\ColorFieldOption;
use Botble\Base\Forms\FieldOptions\CoreIconFieldOption;
use Botble\Base\Forms\FieldOptions\MediaImageFieldOption;
use Botble\Base\Forms\FieldOptions\NumberFieldOption;
use Botble\Base\Forms\FieldOptions\TextareaFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\ColorField;
use Botble\Base\Forms\Fields\CoreIconField;
use Botble\Base\Forms\Fields\MediaImageField;
use Botble\Base\Forms\Fields\NumberField;
use Botble\Base\Forms\Fields\TextareaField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Gallery\Models\Gallery;
use Botble\Shortcode\Compilers\Shortcode as ShortcodeCompiler;
use Botble\Shortcode\Facades\Shortcode;
use Botble\Shortcode\Forms\ShortcodeForm;
use Botble\Theme\Facades\Theme;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;

app()->booted(function () {
    if (! is_plugin_active('gallery')) {
        return;
    }

    Event::listen(RouteMatched::class, function (): void {
        Shortcode::register(
            'galleries',
            __('Galleries'),
            __('Display galleries in a grid layout'),
            function (ShortcodeCompiler $shortcode) {
                $limit = (int) $shortcode->limit ?: 6;

                $galleries = Gallery::query()
                    ->with('slugable')
                    ->wherePublished()
                    ->when($limit > 0, fn ($query) => $query->limit($limit))
                    ->orderBy('order')
                    ->orderByDesc('created_at')
                    ->get();

                if ($galleries->isEmpty()) {
                    return null;
                }

                return Theme::partial('shortcodes.galleries.index', compact('shortcode', 'galleries'));
            }
        );

        Shortcode::setAdminConfig('galleries', function (array $attributes) {
            return ShortcodeForm::createFromArray($attributes)
                ->add(
                    'title',
                    TextField::class,
                    TextFieldOption::make()
                        ->label(__('Title'))
                        ->toArray()
                )
                ->add(
                    'icon',
                    CoreIconField::class,
                    CoreIconFieldOption::make()
                        ->label(__('Icon'))
                )
                ->add(
                    'icon_image',
                    MediaImageField::class,
                    MediaImageFieldOption::make()
                        ->label(__('Icon image (It will override icon above if set)'))
                )
                ->add(
                    'subtitle',
                    TextField::class,
                    TextFieldOption::make()
                        ->label(__('Subtitle'))
                        ->toArray()
                )
                ->add(
                    'description',
                    TextareaField::class,
                    TextareaFieldOption::make()
                        ->label(__('Description'))
                        ->toArray()
                )
                ->add(
                    'limit',
                    NumberField::class,
                    NumberFieldOption::make()
                        ->label(__('Limit'))
                        ->defaultValue(6)
                        ->toArray()
                )
                ->add(
                    'margin_top',
                    NumberField::class,
                    NumberFieldOption::make()
                        ->label(__('Margin Top (px)'))
                        ->defaultValue(0)
                        ->toArray()
                )
                ->add(
                    'margin_bottom',
                    NumberField::class,
                    NumberFieldOption::make()
                        ->label(__('Margin Bottom (px)'))
                        ->defaultValue(0)
                        ->toArray()
                )
                ->add(
                    'background_color',
                    ColorField::class,
                    ColorFieldOption::make()
                        ->label(__('Background Color'))
                        ->defaultValue('')
                        ->toArray()
                );
        });
    });
});
