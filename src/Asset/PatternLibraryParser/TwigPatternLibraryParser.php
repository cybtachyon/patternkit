<?php

namespace Drupal\patternkit\Asset\PatternLibraryParser;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Asset\Exception\InvalidLibraryFileException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\patternkit\Entity\Pattern;
use Drupal\patternkit\Entity\PatternInterface;
use Drupal\patternkit\PatternEditorConfig;
use Drupal\patternkit\PatternLibrary;
use Drupal\patternkit\PatternLibraryJSONParserTrait;
use Drupal\patternkit\Asset\PatternLibraryParserBase;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;

/**
 * Parses a Twig pattern library collection into usable metadata.
 */
class TwigPatternLibraryParser extends PatternLibraryParserBase {
  use PatternLibraryJSONParserTrait;

  /**
   * TwigPatternLibraryParser constructor.
   *
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   Serializes and de-serializes data.
   * @param string $root
   *   The application root path.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Allows modules to alter library parsing.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   Allows themes to alter library parsing.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper
   *   The stream wrapper manager.
   */
  public function __construct(
    SerializationInterface $serializer,
    $root,
    ModuleHandlerInterface $module_handler,
    ThemeManagerInterface $theme_manager,
    StreamWrapperManagerInterface $stream_wrapper) {

    $this->serializer = $serializer;
    parent::__construct($root, $module_handler, $theme_manager, $stream_wrapper, null);
  }

  /**
   * Returns a new Patternkit Pattern.
   *
   * @param string $name
   *   The name of the pattern.
   * @param array|object $data
   *   The optional data for the pattern.
   *
   * @return \Drupal\patternkit\Entity\PatternInterface
   *   A fully-instantiated Patternkit Pattern.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createPattern($name, $data): PatternInterface {
    // Pattern schemas contain values needed for Pattern fields.
    $values = ['name' => $data['title'] ?? $name];
    return Pattern::create($values + $data);
  }

  /**
   * {@inheritDoc}
   */
  public function fetchPatternAssets(PatternInterface $pattern,
                                     PatternEditorConfig $config = NULL): array {
    // @todo Add support for twig lib attachments such as JS and images.
    $assets = parent::fetchPatternAssets($pattern, $config);
    $assets['template'] = $assets['twig'];
    $assets['schema'] = $assets['json'];
    // Replace any $ref links with relative paths.
    $schema = $this->serializer::decode($assets['schema']);
    if (!isset($schema['properties'])) {
      return $assets;
    }
    $schema['properties'] = static::schemaDereference(
      $schema['properties'],
      $pattern
    );
    $schema['definitions'] = static::schemaDereference(
      $schema['definitions'],
      $pattern
    );
    $assets['schema'] = $this->serializer::encode($schema);
    return $assets;
  }

  public function parsePattern() { }

  /**
   * Parses a given library file and allows modules and themes to alter it.
   *
   * This method sets the parsed information onto the library property.
   *
   * Library information is parsed from *.libraries.yml files; see
   * editor.libraries.yml for an example. Each entry starts with a machine name
   * and defines the following elements:
   * - patterns: A list of pattern libraries and subtypes to include. Each
   *   subtype is keyed by the subtype path.
   *
   * @code
   *   patterns:
   *     path/atoms: {type: twig, category: atoms}
   *     path/molecules: {type: twig, category: molecules}
   *     path/organisms: {}
   * @endcode
   * - dependencies: A list of libraries this library depends on.
   * - version: The library version. The string "VERSION" can be used to mean
   *   the current Drupal core version.
   * - header: By default, JavaScript files are included in the footer. If the
   *   script must be included in the header (along with all its dependencies),
   *   set this to true. Defaults to false.
   * - minified: If the file is already minified, set this to true to avoid
   *   minifying it again. Defaults to false.
   * - remote: If the library is a third-party script, this provides the
   *   repository URL for reference.
   * - license: If the remote property is set, the license information is
   *   required. It has 3 properties:
   *   - name: The human-readable name of the license.
   *   - url: The URL of the license file/information for the version of the
   *     library used.
   *   - gpl-compatible: A Boolean for whether this library is GPL compatible.
   *
   * See https://www.drupal.org/node/2274843#define-library for more
   * information.
   *
   * @param \Drupal\patternkit\PatternLibrary $library
   *   The data of the library that was registered.
   * @param string $path
   *   The relative path to the extension.
   *
   * @return array
   *   An array of parsed library data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function parsePatternLibraryInfo(PatternLibrary $library, $path): array {
    if (!file_exists($path)) {
      throw new InvalidLibraryFileException("Path $path does not exist.");
    }
    $metadata = [];
    $info = $library->getPatternInfo();
    foreach (self::discoverComponents($path, ['json', 'twig']) as $name => $data) {
      if (empty($data['twig']) || !file_exists($data['twig'])) {
        continue;
      }
      $pattern_path = trim(substr($data['twig'], strlen($path), -strlen('.twig')), '/\\');
      // If the component has a JSON file, create the pattern from it.
      $defaults = [
        'assets'          => ['twig' => $data['twig']],
        'category'        => $info['category'] ?? 'default',
        'library'         => $library->id(),
        'libraryPluginId' => 'twig',
        'name'            => $name,
        'path'            => $pattern_path,
        'version'         => $library->version ?? 'VERSION',
      ];
      if (!empty($data['json']) && $file_contents = file_get_contents($data['json'])) {
        $category_guess = $info['category'] ?? strstr($pattern_path, DIRECTORY_SEPARATOR, TRUE);
        $defaults['category'] = $category_guess ?? $defaults['category'];
        $defaults['assets']['json'] = $data['json'];
        $pattern = $this->createPattern($name, (array) $this->serializer::decode($file_contents) + $defaults);
      }
      // Otherwise create the pattern from defaults.
      // @todo Have this cleverly infer defaults from the template.
      else {
        $pattern = $this->createPattern($name, $defaults);
      }
      $metadata[$pattern_path] = $pattern->toArray();
    }
    return $metadata;
  }

}
