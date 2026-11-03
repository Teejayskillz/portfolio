<?php

namespace Database\Seeders\Themes\Main;

use Botble\Gallery\Database\Traits\HasGallerySeeder;
use Botble\Theme\Database\Seeders\ThemeSeeder;

class GallerySeeder extends ThemeSeeder
{
    use HasGallerySeeder;

    public function run(): void
    {
        $this->uploadFiles('galleries', 'main');

        $galleries = [
            'Perfect',
            'New Day',
            'Happy Day',
            'Nature',
            'Morning',
            'Sunset',
            'Ocean Views',
            'Adventure Time',
        ];

        $this->createGalleries(
            collect($galleries)->map(function (string $item, int $index) {
                return ['name' => $item, 'image' => $this->filePath('galleries/' . ($index + 1) . '.jpg', 'main')];
            })->toArray(),
            array_map(function ($index) {
                return ['img' => $this->filePath('galleries/' . $index . '.jpg', 'main'), 'description' => ''];
            }, range(1, 8))
        );
    }
}
