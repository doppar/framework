<?php

namespace Phaseolies\Support;

use RuntimeException;

class ViteManager
{
    /**
     * Resolve a public-facing asset URL from the Vite manifest or dev server.
     *
     * @param string $asset
     * @param string $buildDirectory
     * @return string
     */
    public function asset(string $asset, string $buildDirectory = 'build'): string
    {
        $asset = $this->normalizeAsset($asset);

        if ($this->isHot()) {
            return $this->hotAssetUrl($asset);
        }

        $manifest = $this->manifest($buildDirectory);

        if (!isset($manifest[$asset]['file'])) {
            throw new RuntimeException("Unable to locate Vite asset [{$asset}] in manifest.");
        }

        return enqueue(trim($buildDirectory, '/') . '/' . ltrim($manifest[$asset]['file'], '/'));
    }

    /**
     * Render the HTML tags for one or more Vite entrypoints.
     *
     * @param string|array $entrypoints
     * @param string $buildDirectory
     * @return string
     */
    public function tags(string|array $entrypoints, string $buildDirectory = 'build'): string
    {
        $entrypoints = array_values(array_unique(array_map(
            [$this, 'normalizeAsset'],
            is_array($entrypoints) ? $entrypoints : [$entrypoints]
        )));

        if ($this->isHot()) {
            return $this->hotTags($entrypoints);
        }

        $manifest = $this->manifest($buildDirectory);
        $tags = [];
        $preloads = [];
        $styles = [];

        foreach ($entrypoints as $entrypoint) {
            if (!isset($manifest[$entrypoint])) {
                throw new RuntimeException("Unable to locate Vite entry [{$entrypoint}] in manifest.");
            }

            $chunk = $manifest[$entrypoint];
            $this->collectChunkImports($manifest, $chunk, $buildDirectory, $preloads, $styles);
            $this->collectChunkStyles($chunk, $buildDirectory, $styles);

            if (!empty($chunk['file'])) {
                $tags[] = $this->scriptTag(
                    enqueue(trim($buildDirectory, '/') . '/' . ltrim($chunk['file'], '/'))
                );
            }
        }

        return implode("\n", array_merge(array_values($preloads), array_values($styles), $tags));
    }

    /**
     * Determine whether the Vite dev server is active.
     *
     * @return bool
     */
    public function isHot(): bool
    {
        return is_file($this->hotFile());
    }

    /**
     * Get the hot file path used to communicate the dev-server URL.
     *
     * @return string
     */
    public function hotFile(): string
    {
        return storage_path('framework/vite.hot');
    }

    /**
     * Read the current Vite dev-server URL from the hot file.
     *
     * @return string
     */
    protected function hotUrl(): string
    {
        $url = is_file($this->hotFile()) ? trim((string) file_get_contents($this->hotFile())) : '';

        if ($url === '') {
            throw new RuntimeException('Vite hot file is empty.');
        }

        return rtrim($url, '/');
    }

    /**
     * Get all tags needed while the Vite dev server is running.
     *
     * @param array<int, string> $entrypoints
     * @return string
     */
    protected function hotTags(array $entrypoints): string
    {
        $tags = [];

        if ($this->needsReactRefreshPreamble($entrypoints)) {
            $tags[] = $this->reactRefreshPreambleTag();
        }

        $tags[] = $this->scriptTag($this->hotUrl() . '/@vite/client');

        foreach ($entrypoints as $entrypoint) {
            $url = $this->hotAssetUrl($entrypoint);

            if ($this->isStyleAsset($entrypoint)) {
                $tags[] = $this->styleTag($url);
            } else {
                $tags[] = $this->scriptTag($url);
            }
        }

        return implode("\n", array_values(array_unique($tags)));
    }

    /**
     * Determine whether the current hot entrypoints need the React refresh preamble.
     *
     * @param array<int, string> $entrypoints
     * @return bool
     */
    protected function needsReactRefreshPreamble(array $entrypoints): bool
    {
        foreach ($entrypoints as $entrypoint) {
            if ((bool) preg_match('/\.(jsx|tsx)$/i', $entrypoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the React Fast Refresh preamble required by @vitejs/plugin-react in dev mode.
     *
     * @return string
     */
    protected function reactRefreshPreambleTag(): string
    {
        $refreshUrl = $this->hotUrl() . '/@react-refresh';

        return <<<HTML
<script type="module">
import RefreshRuntime from '{$refreshUrl}';
RefreshRuntime.injectIntoGlobalHook(window);
window.\$RefreshReg\$ = () => {};
window.\$RefreshSig\$ = () => (type) => type;
window.__vite_plugin_react_preamble_installed__ = true;
</script>
HTML;
    }

    /**
     * Resolve a dev-server URL for the given asset.
     *
     * @param string $asset
     * @return string
     */
    protected function hotAssetUrl(string $asset): string
    {
        return $this->hotUrl() . '/' . ltrim($asset, '/');
    }

    /**
     * Get the decoded Vite manifest.
     *
     * @param string $buildDirectory
     * @return array<string, array<string, mixed>>
     */
    protected function manifest(string $buildDirectory): array
    {
        $manifestPath = $this->manifestPath($buildDirectory);

        if (!is_file($manifestPath)) {
            throw new RuntimeException("Vite manifest not found at [{$manifestPath}].");
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            throw new RuntimeException("Invalid Vite manifest at [{$manifestPath}].");
        }

        return $manifest;
    }

    /**
     * Resolve the current manifest path.
     *
     * Supports both the explicit legacy location and the Vite 7 default
     * ".vite/manifest.json" output directory.
     *
     * @param string $buildDirectory
     * @return string
     */
    protected function manifestPath(string $buildDirectory): string
    {
        $buildDirectory = $this->normalizeFilesystemSegment($buildDirectory);
        $preferred = public_path($buildDirectory . DIRECTORY_SEPARATOR . 'manifest.json');

        if (is_file($preferred)) {
            return $preferred;
        }

        $viteDefault = public_path($buildDirectory . DIRECTORY_SEPARATOR . '.vite' . DIRECTORY_SEPARATOR . 'manifest.json');

        return is_file($viteDefault) ? $viteDefault : $preferred;
    }

    /**
     * Normalize a filesystem path fragment so it works across Unix and Windows.
     *
     * @param string $path
     * @return string
     */
    protected function normalizeFilesystemSegment(string $path): string
    {
        return trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    /**
     * Collect imported chunks recursively for preload and CSS output.
     *
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, mixed> $chunk
     * @param string $buildDirectory
     * @param array<string, string> $preloads
     * @param array<string, string> $styles
     * @return void
     */
    protected function collectChunkImports(
        array $manifest,
        array $chunk,
        string $buildDirectory,
        array &$preloads,
        array &$styles
    ): void {
        foreach ($chunk['imports'] ?? [] as $import) {
            if (!isset($manifest[$import])) {
                continue;
            }

            $importedChunk = $manifest[$import];

            if (!empty($importedChunk['file'])) {
                $url = enqueue(trim($buildDirectory, '/') . '/' . ltrim($importedChunk['file'], '/'));
                $preloads[$url] = '<link rel="modulepreload" href="' . $url . '">';
            }

            $this->collectChunkStyles($importedChunk, $buildDirectory, $styles);
            $this->collectChunkImports($manifest, $importedChunk, $buildDirectory, $preloads, $styles);
        }
    }

    /**
     * Collect CSS files referenced by a chunk.
     *
     * @param array<string, mixed> $chunk
     * @param string $buildDirectory
     * @param array<string, string> $styles
     * @return void
     */
    protected function collectChunkStyles(array $chunk, string $buildDirectory, array &$styles): void
    {
        foreach ($chunk['css'] ?? [] as $cssFile) {
            $url = enqueue(trim($buildDirectory, '/') . '/' . ltrim($cssFile, '/'));
            $styles[$url] = $this->styleTag($url);
        }
    }

    /**
     * Normalize entrypoint and asset paths to manifest-style keys.
     *
     * @param string $asset
     * @return string
     */
    protected function normalizeAsset(string $asset): string
    {
        return ltrim(str_replace('\\', '/', $asset), '/');
    }

    /**
     * Determine whether the asset is a stylesheet entry.
     *
     * @param string $asset
     * @return bool
     */
    protected function isStyleAsset(string $asset): bool
    {
        return (bool) preg_match('/\.(css|scss|sass|less|styl)$/i', $asset);
    }

    /**
     * Build a module script tag.
     *
     * @param string $url
     * @return string
     */
    protected function scriptTag(string $url): string
    {
        return '<script type="module" src="' . $url . '"></script>';
    }

    /**
     * Build a stylesheet link tag.
     *
     * @param string $url
     * @return string
     */
    protected function styleTag(string $url): string
    {
        return '<link rel="stylesheet" href="' . $url . '">';
    }
}
