<?php

namespace App\Filament\GlobalSearch;

use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;

class ContextualGlobalSearchProvider implements GlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $resource = $this->resolveCurrentResource();

        if ($resource === null) {
            return app(DefaultGlobalSearchProvider::class)->getResults($query);
        }

        $results = GlobalSearchResults::make();

        if (! $resource::canGloballySearch()) {
            return $results;
        }

        $resourceResults = $resource::getGlobalSearchResults($query);

        if ($resourceResults->isNotEmpty()) {
            $results->category($resource::getPluralModelLabel(), $resourceResults);
        }

        return $results;
    }

    /**
     * Resolve the open Filament resource from the page URL. During a Livewire
     * search request, the original page URL is available through the referer.
     *
     * @return class-string<\Filament\Resources\Resource> | null
     */
    private function resolveCurrentResource(): ?string
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $paths = [request()->path()];
        $refererPath = parse_url((string) request()->headers->get('referer'), PHP_URL_PATH);

        if (is_string($refererPath) && $refererPath !== '') {
            $paths[] = trim($refererPath, '/');
        }

        $resourcePaths = collect(Filament::getResources())
            ->mapWithKeys(fn (string $resource): array => [
                $resource => trim($panel->getPath() . '/' . $resource::getSlug($panel), '/'),
            ])
            ->sortByDesc(fn (string $path): int => strlen($path));

        foreach ($paths as $path) {
            $path = trim($path, '/');

            foreach ($resourcePaths as $resource => $resourcePath) {
                if ($path === $resourcePath || str_starts_with($path, $resourcePath . '/')) {
                    return $resource;
                }
            }
        }

        return null;
    }
}
