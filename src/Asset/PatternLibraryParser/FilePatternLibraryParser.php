<?php

namespace Drupal\patternkit\Asset\PatternLibraryParser;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Asset\Exception\InvalidLibraryFileException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\patternkit\Entity\PatternInterface;
use Drupal\patternkit\PatternEditorConfig;
use Drupal\patternkit\PatternLibrary;
use Drupal\patternkit\PatternLibraryJSONParserTrait;
use Drupal\patternkit\Asset\PatternLibraryParserBase;

/**
 * Parses a File pattern library collection into usable metadata.
 */
class FilePatternLibraryParser extends PatternLibraryParserBase {
  use PatternLibraryJSONParserTrait;

  /**
   * FilePatternLibraryParser constructor.
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
    parent::__construct($root, $module_handler, $theme_manager, $stream_wrapper);
  }

  /**
   * {@inheritDoc}
   */
  public function fetchPatternAssets(PatternInterface $pattern,
    PatternEditorConfig $config = NULL): array {
    // @todo Add support for File lib attachments such as JS and images.
    return [];
  }

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
   * @code
   *   patterns:
   *     path/atoms: {type: File, category: atoms}
   *     path/molecules: {type: File, category: molecules}
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
   * @throws \Drupal\Core\Asset\Exception\InvalidLibraryFileException
   *   Thrown when a parser exception was thrown.
   */
  public function parsePatternLibraryInfo(PatternLibrary $library, $path): array {
    if (!file_exists($path)) {
      throw new InvalidLibraryFileException("Path $path does not exist.");
    }
    $metadata = [];
    // @todo Grab the extension from the plugin.
    if (!isset($library->toArray()['plugin'])) {
      return [];
    }
    $type = strtok($library->toArray()['plugin'], '.');
    if ($type !== 'file') {
      return [];
    }
    $ext = strtok($type, '.');
    foreach (self::discoverComponents($path, [$ext[1]]) as $name => $data) {
      if (empty($data[$ext]) || !file_exists($data[$ext])) {
        continue;
      }
      // If the component has a json file, create the pattern from it.
      $category = $library['category'] ?? 'default';
      $library_defaults = [
        '$schema'    => NULL,
        'category'   => $category,
        'title'      => $name,
        'type'       => 'object',
        'format'     => 'grid',
        'license'    => $library['license'] ?? [],
        'name'       => $name,
        'properties' => (object) [],
        'required'   => [],
        'version'    => $library['version'] ?? '',
      ];
      // Create the pattern from defaults.
      // @todo Have this cleverly infer defaults from the template.
      $pattern = $this->createPattern($name, $library_defaults);
      $pattern->filename = trim(substr($data[$ext], strlen($path)), '/\\');
      $pattern->path = substr($pattern->filename, 0, -strlen('.' . $ext));
      $pattern->template = file_get_contents($data[$ext]);
      // URL is redundant for the File based components, so we use it to
      // store namespace.
      $pattern->url = $library['name'];
      // @todo add default of library version fallback to extension version.
      $pattern->version = $pattern->version ?? 'VERSION';
      $metadata['@' . $library['name'] . '/' . $pattern->path] = $pattern;
    }

    foreach ($metadata as $pattern_type => $pattern) {
      // Replace any $ref links with relative paths.
      if (!isset($pattern->schema['properties'])) {
        continue;
      }
      $pattern->schema['properties'] = static::schemaDereference(
        $pattern->schema['properties'],
        $pattern
      );
      $pattern->schema['definitions'] = static::schemaDereference(
        $pattern->schema['definitions'],
        $pattern
      );
      $metadata[$pattern_type] = $pattern;
    }
    return $metadata;
  }

}
