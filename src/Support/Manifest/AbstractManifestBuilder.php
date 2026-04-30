<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

use Illuminate\Support\Str;
use Sloth\Core\Application;

/**
 * Base class for manifest-based file loading.
 *
 * Orchestrates the discover → write → include lifecycle. This class is
 * intentionally minimal — it only owns the build lifecycle and the hooks
 * that subclasses must implement.
 *
 * ## Lifecycle
 *
 * 1. **init()** — primary entry point. Checks if the manifest exists and is
 *    current. If not, calls build() to regenerate it.
 * 2. **Build** — the finder discovers files, `entries()` computes registration
 *    arguments, and ManifestWriter writes the manifest file.
 * 3. **Require** — the manifest is included via `require`, which loads all
 *    files and returns the cached entry data. Opcache handles caching from
 *    here.
 *
 * ## Subclass responsibilities
 *
 * Concrete builders must implement these abstract methods:
 *
 * - **finder()** — return the appropriate FinderInterface implementation
 *   (ClassMapFinder for classes, FileFinder for loose files, ComposerFinder
 *   for vendor packages).
 * - **manifestName()** — the filename for the cached manifest (e.g.
 *   `'models.manifest.php'`).
 *
 * Optional overrides:
 *
 * - **extraLines()** — PHP code lines to embed after each file's require_once.
 *   Used for Layotter class definitions which must exist as named classes.
 * - **entries()** — structured data computed at build time and returned by
 *   the manifest. Consumed by Registrars to perform WordPress registrations
 *   without recomputing arguments at runtime.
 * - **directories()** — override to customize which directories are scanned.
 *   Returns null by default; subclasses that need path-based discovery should
 *   extend PathBasedManifestBuilder instead.
 *
 * ## Manifest format
 *
 * ```php
 * <?php
 * require_once '/abs/path/NewsModel.php';
 * class TeaserModule extends \Sloth\Module\LayotterElement { ... }
 * \Layotter::register_element('teaser-module', 'TeaserModule');
 *
 * return [
 *     '\\App\\Model\\NewsModel' => [
 *         'postType' => 'news',
 *         'args'     => [...],
 *         'names'    => [...],
 *     ],
 * ];
 * ```
 *
 * ## Design notes
 *
 * - The `entries()` hook separates discovery/build from registration. The
 *   expensive work (reflection, argument computation) happens once at build
 *   time. Registrars read the pre-computed data at runtime.
 * - `extraLines()` exists for cases where PHP class definitions must be
 *   generated (Layotter). Most builders don't need this.
 * - Write failures are silently swallowed — manifests are an optimisation.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\ManifestWriter      For manifest file generation
 * @see \Sloth\Support\Manifest\ClassMapFinder      For class-based discovery
 * @see \Sloth\Support\Manifest\FileFinder          For file-based discovery
 * @see \Sloth\Support\Manifest\ComposerFinder      For vendor package discovery
 */
abstract class AbstractManifestBuilder
{
    /**
     * Entry data from the last init() or build() call.
     *
     * Populated either by reading the cached manifest file (init) or by
     * computing fresh data (build). Consumed by Registrars via getEntries().
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    protected array $entries = [];

    /**
     * Creates a new manifest builder instance.
     *
     * @param Application $app The application container, used for path resolution
     *                         and filesystem access.
     * @since 1.0.0
     */
    public function __construct(protected Application $app) {}

    /**
     * Run discovery, build the manifest if needed, and load it.
     *
     * This is the primary entry point, called from service providers on the
     * WordPress `init` hook. The manifest is rebuilt when:
     *
     * - The application is in local environment (force regeneration).
     * - The manifest file does not exist (first run or cleared).
     *
     * After loading, entry data is available via getEntries() for Registrars.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        $manifest = app()->path('cache') . '/Manifest/' . $this->manifestName();

        if (app()->isLocal() || !app('files')->exists($manifest)) {
            $this->build($manifest);
        }

        $this->entries = require $manifest;
    }

    /**
     * Run discovery, compute entries, and write the manifest file.
     *
     * Called internally by init() when the manifest needs to be rebuilt.
     * Subclasses should not call this directly — use init() instead.
     *
     * @param string $manifest Absolute path where the manifest file will be written.
     * @since 1.0.0
     */
    protected function build(string $manifest): void
    {
        $directories = $this->directories();
        $map = $this->finder()->find($directories ?? []);

        $extraLines = collect($map)
            ->mapWithKeys(fn($file, $identifier) => [
                $identifier => $this->extraLines($identifier, $file),
            ])
            ->filter(fn($lines) => !empty($lines))
            ->all();

        $entries = $this->entries($map);

        (new ManifestWriter(app('files')))->write(
            path: $manifest,
            map: $map,
            extraLines: $extraLines,
            entries: $entries,
            require: $this->requireFiles(),
        );

        $this->entries = $entries;
    }

    /**
     * Directories to scan for discovery.
     *
     * Returns null by default. Subclasses that scan app/ and theme/ directories
     * should extend PathBasedManifestBuilder, which provides a default
     * implementation that returns `[app/path(), theme/path()]`.
     *
     * Override this method if you need non-standard paths (e.g. scanning
     * additional directories or a single source).
     *
     * @return list<string>|null Absolute directory paths to scan, or null
     *                           when the finder does not require directory input.
     * @since 1.0.0
     */
    protected function directories(): ?array
    {
        return null;
    }

    /**
     * Whether to emit require_once statements in the manifest.
     *
     * Defaults to true. Override and return false when Composer handles
     * autoloading (e.g. vendor package discovery).
     *
     * @return bool True to emit require_once statements, false to skip them.
     * @since 1.0.0
     */
    protected function requireFiles(): bool
    {
        return true;
    }

    /**
     * Get the entry data from the last init() call.
     *
     * Registrars call this method to access the pre-computed registration
     * arguments without triggering discovery or recomputation.
     *
     * @return array<string, mixed> Entry data keyed by class identifier.
     * @since 1.0.0
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Return the finder implementation for this builder.
     *
     * Use ClassMapFinder to discover classes extending a specific base class,
     * FileFinder to discover all PHP files in a directory, or ComposerFinder
     * to discover vendor packages from installed.json.
     *
     * @return FinderInterface The configured finder instance.
     * @since 1.0.0
     */
    abstract protected function finder(): FinderInterface;

    /**
     * Return the filename for the cached manifest file.
     *
     * Derived from the builder's fully qualified class name with a short hash
     * suffix to prevent collisions between builders with the same class name
     * but different namespaces.
     *
     * Example: `ModelManifestBuilder` in `Sloth\Model\Manifest` →
     * `model-a1b2c3.manifest.php`.
     *
     * Override if you need a non-standard name.
     *
     * @return string The manifest filename.
     * @since 1.0.0
     */
    protected function manifestName(): string
    {
        $name = class_basename(static::class);
        $name = str_replace('ManifestBuilder', '', $name);
        $hash = substr(md5(static::class), 0, 6);
        return Str::kebab($name) . '-' . $hash . '.manifest.php';
    }

    /**
     * Extra PHP lines to embed into the manifest after each file's require_once.
     *
     * Used primarily for Layotter element class definitions which must exist
     * as named PHP classes at runtime. Most builders return an empty array.
     *
     * The returned lines are inserted directly into the generated manifest
     * file — they must be valid, complete PHP statements.
     *
     * Example:
     * ```php
     * return [
     *     'class TeaserModule extends \Sloth\Module\LayotterElement { ... }',
     *     '\Layotter::register_element("teaser-module", "TeaserModule");',
     * ];
     * ```
     *
     * @param string $identifier Fully qualified class name or file path,
     *                           depending on the finder implementation.
     * @param string $file       Absolute path to the discovered file.
     * @return list<string>      PHP code lines to embed. Empty array for none.
     * @since 1.0.0
     */
    protected function extraLines(string $identifier, string $file): array
    {
        return [];
    }

    /**
     * Entry data to return from the manifest, consumed by Registrars.
     *
     * Compute registration arguments here. This method runs once at build time
     * and the result is cached in the manifest file via `var_export()`.
     * Registrars read this data at runtime without recomputation.
     *
     * The returned array is keyed by identifier (class name). Each value
     * should contain all the information a Registrar needs to perform its
     * WordPress registrations.
     *
     * Example:
     * ```php
     * return [
     *     '\\App\\Model\\NewsModel' => [
     *         'postType' => 'news',
     *         'args'     => ['public' => true, ...],
     *         'names'    => ['singular' => 'News', 'plural' => 'News'],
     *     ],
     * ];
     * ```
     *
     * @param array<string, string> $map Identifier => absolute file path map
     *                                   from the finder.
     * @return array<string, mixed>      Entry data keyed by identifier.
     * @since 1.0.0
     */
    protected function entries(array $map): array
    {
        return [];
    }
}
