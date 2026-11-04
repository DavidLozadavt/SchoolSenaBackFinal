<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        if (! $this->app->runningInConsole()) {
            $this->warnIfPhpUploadLimitBelow50Mb();
        }
    }

    private function warnIfPhpUploadLimitBelow50Mb(): void
    {
        static $warned = false;
        if ($warned) {
            return;
        }
        $warned = true;
        $minBytes = 50 * 1024 * 1024;
        $upload = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $post = $this->iniBytes((string) ini_get('post_max_size'));
        if ($upload >= $minBytes && $post >= 60 * 1024 * 1024) {
            return;
        }
        \Illuminate\Support\Facades\Log::warning('PHP upload limits below 50 MB app requirement', [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'hint' => 'Arranque el API con ./serve.sh o composer run serve para cargar php-conf.d/99-uploads.ini',
        ]);
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($value, -1));
        $num = (float) $value;
        return (int) match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
