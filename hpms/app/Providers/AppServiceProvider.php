<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\BrandAssets;
use App\Support\MediaStorage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::defaultView('pagination.hpms');
        Paginator::defaultSimpleView('pagination.hpms');

        if ($this->hasChamberSettings()) {
            $settings = Setting::query()->pluck('value', 'key')->all();
            if (! empty($settings['timezone'])) {
                Config::set('app.timezone', $settings['timezone']);
                date_default_timezone_set($settings['timezone']);
            }
        }

        View::composer(['layouts.app', 'settings.index', 'medicines.form'], function ($view): void {
            if (! $this->hasChamberSettings()) {
                $view->with('appSettings', []);

                return;
            }

            $settings = Setting::query()->pluck('value', 'key')->all();
            if (! empty($settings['chamber_logo'])) {
                $settings['chamber_logo_url'] = MediaStorage::url($settings['chamber_logo']);
            }
            if (! empty($settings['chamber_brand_assets'])) {
                $assets = json_decode((string) $settings['chamber_brand_assets'], true);
                if (is_array($assets)) {
                    $settings['brand_assets'] = BrandAssets::urls($assets);
                }
            }
            $view->with('appSettings', $settings);
        });
    }

    public function register(): void
    {
        //
    }

    /**
     * The settings table is read during boot; a database outage must not turn
     * health checks into 500s. Missing settings simply fall back to defaults.
     */
    private function hasChamberSettings(): bool
    {
        try {
            return Schema::hasTable('settings') && Schema::hasColumn('settings', 'chamber_id');
        } catch (Throwable) {
            return false;
        }
    }
}
