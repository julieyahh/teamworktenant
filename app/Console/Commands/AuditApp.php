<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * php artisan app:audit
 *
 * A cheap health check for the whole app. It catches the class of bug that only
 * shows up as a 500 when a user happens to click the right link:
 *
 *   1. A route pointing at a controller class that doesn't exist.
 *   2. A route pointing at a method that doesn't exist on that controller.
 *   3. A controller calling view(...) where the named Blade file is missing.
 *   4. Two routes sharing a name (this makes `php artisan route:cache` fail).
 *   5. Two routes sharing METHOD + URI (the later one silently wins).
 *
 * Run it after touching routes or renaming anything. Exit code is 1 when
 * problems are found, so it can go in a deploy script.
 */
class AuditApp extends Command
{
    protected $signature = 'app:audit {--views : Also scan Blade files for view() and @include}';

    protected $description = 'Check routes, controller methods and view names for dangling references';

    private int $problems = 0;

    public function handle(): int
    {
        $this->auditRouteTargets();
        $this->auditDuplicateNames();
        $this->auditDuplicateUris();
        $this->auditViewReferences();

        $this->newLine();

        if ($this->problems === 0) {
            $this->info('✔  No problems found.');

            return self::SUCCESS;
        }

        $this->error("✘  {$this->problems} problem(s) found.");

        return self::FAILURE;
    }

    /* ---------------------------------------------------------------- */

    private function auditRouteTargets(): void
    {
        $this->components->info('Routes → controller methods');

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction('uses');

            // Closure-based routes have nothing to check.
            if (! is_string($action) || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);

            if (! class_exists($class)) {
                $this->report($route->uri(), "controller class {$class} does not exist");

                continue;
            }

            if (! method_exists($class, $method)) {
                $this->report($route->uri(), "method {$method}() does not exist on {$class}");
            }
        }
    }

    private function auditDuplicateNames(): void
    {
        $this->components->info('Duplicate route names');

        $seen = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name) {
                continue;
            }

            if (isset($seen[$name])) {
                $this->report($route->uri(), "route name '{$name}' is already used by {$seen[$name]} — route:cache will fail");
            }

            $seen[$name] = $route->uri();
        }
    }

    private function auditDuplicateUris(): void
    {
        $this->components->info('Duplicate METHOD + URI');

        $seen = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $verb) {
                if (in_array($verb, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $key = $verb . ' /' . $route->uri();

                if (isset($seen[$key])) {
                    $this->report($key, 'registered more than once — only the last registration is live');
                }

                $seen[$key] = true;
            }
        }
    }

    /**
     * Look for view(...) / View::make(...) calls in PHP, and optionally
     * @extends / @include in Blade, then check the file actually exists.
     */
    private function auditViewReferences(): void
    {
        $this->components->info('view() names');

        $paths = [app_path()];

        if ($this->option('views')) {
            $paths[] = resource_path('views');
        }

        foreach ($this->phpAndBladeFiles($paths) as $file) {
            $contents = file_get_contents($file);

            preg_match_all(
                "/(?:view|View::make)\(\s*'([a-zA-Z0-9_.\-\/]+)'|@(?:extends|include|includeIf)\(\s*'([a-zA-Z0-9_.\-\/]+)'/",
                $contents,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $name = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

                if ($name === '' || str_contains($name, '/')) {
                    continue; // skip paths and anything built dynamically
                }

                if (! View::exists($name)) {
                    $this->report(
                        str_replace(base_path() . '/', '', $file),
                        "view '{$name}' does not exist"
                    );
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $paths
     * @return iterable<int, string>
     */
    private function phpAndBladeFiles(array $paths): iterable
    {
        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function report(string $where, string $what): void
    {
        $this->problems++;
        $this->line("  <fg=red>✘</> <options=bold>{$where}</> — {$what}");
    }
}
