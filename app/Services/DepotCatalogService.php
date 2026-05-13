<?php

namespace App\Services;

use App\Models\Cargo;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DepotCatalogService
{
    /**
     * @return array{source_url: string, categories: array<int, array<string, string>>, items: array<int, array<string, mixed>>, installed: array<string, int>}
     */
    public function payload(): array
    {
        $items = collect($this->items())->map(
            fn (array $item): array => [
                'key' => (string) $item['key'],
                'category' => (string) $item['category'],
                'icon' => (string) ($item['icon'] ?? 'box'),
                'name' => (string) $item['name'],
                'author' => (string) $item['author'],
                'description' => (string) $item['description'],
                'slug' => Str::slug((string) $item['name']),
                'docker_image_count' => count((array) ($item['docker_images'] ?? [])),
                'variable_count' => count((array) ($item['variables'] ?? [])),
            ],
        )->values()->all();

        $installed = Cargo::query()
            ->whereIn('slug', collect($items)->pluck('slug')->all())
            ->pluck('id', 'slug')
            ->all();

        return [
            'source_url' => (string) config('depot.source_url'),
            'categories' => array_values((array) config('depot.categories', [])),
            'items' => $items,
            'installed' => $installed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrFail(string $key): array
    {
        $item = collect($this->items())->firstWhere('key', $key);

        if (! is_array($item)) {
            throw new InvalidArgumentException(
                'That depot entry does not exist.',
            );
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function buildDefinition(array $item): array
    {
        return [
            '_comment' => 'Skyport depot import',
            'meta' => [
                'version' => CargoDefinitionService::VERSION,
                'source' => 'depot',
                'source_format' => 'native',
                'update_url' => Arr::get($item, 'update_url'),
            ],
            'exported_at' => now()->toIso8601String(),
            'name' => (string) $item['name'],
            'author' => (string) $item['author'],
            'description' => (string) ($item['description'] ?? ''),
            'features' => array_values((array) ($item['features'] ?? [])),
            'docker_images' => (array) ($item['docker_images'] ?? []),
            'file_denylist' => array_values((array) ($item['file_denylist'] ?? [])),
            'file_hidden_list' => array_values((array) ($item['file_hidden_list'] ?? [])),
            'startup' => (string) $item['startup'],
            'config' => [
                'files' => (string) ($item['config_files'] ?? '{}'),
                'startup' => (string) ($item['config_startup'] ?? '{}'),
                'logs' => (string) ($item['config_logs'] ?? '{}'),
                'stop' => (string) ($item['config_stop'] ?? 'stop'),
            ],
            'scripts' => [
                'installation' => [
                    'script' => (string) ($item['install_script'] ?? ''),
                    'container' => (string) ($item['install_container'] ?? ''),
                    'entrypoint' => (string) ($item['install_entrypoint'] ?? 'bash'),
                    'requires_privileged' => (bool) ($item['requires_privileged'] ?? false),
                ],
            ],
            'variables' => array_values((array) ($item['variables'] ?? [])),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        return (array) config('depot.items', []);
    }
}
