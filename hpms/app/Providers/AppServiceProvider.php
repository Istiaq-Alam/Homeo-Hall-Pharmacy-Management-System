<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (Schema::hasTable('settings')) {
            $settings = Setting::query()->pluck('value', 'key')->all();
            if (! empty($settings['timezone'])) {
                Config::set('app.timezone', $settings['timezone']);
                date_default_timezone_set($settings['timezone']);
            }
        }

        View::composer(['layouts.app', 'settings.index', 'medicines.form'], function ($view): void {
            if (! Schema::hasTable('settings')) {
                $view->with('appSettings', []);

                return;
            }

            $settings = Setting::query()->pluck('value', 'key')->all();
            if (! empty($settings['chamber_logo'])) {
                $settings['chamber_logo_url'] = Storage::disk('public')->url($settings['chamber_logo']);
            }
            $view->with('appSettings', $settings);
        });
    }

    public function register(): void
    {
        //
    }
}
