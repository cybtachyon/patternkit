<?php

namespace Drupal\patternkit\Asset\PatternLibraryParser;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Asset\Exception\InvalidLibraryFileException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\patternkit\PatternLibrary;
use Drupal\patternkit\PatternLibraryJSONParserTrait;
use Drupal\patternkit\Asset\PatternLibraryParserBase;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;

/**
 * Parses a Twig pattern library collection into usable metadata.
 */
class JSONPatternLibraryParser extends PatternLibraryParserBase {
  use PatternLibraryJSONParserTrait;

  /**
   * Attaches required services.
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
  public function parsePatternLibraryInfo(PatternLibrary $library, $path): array {
    if (!file_exists($path)) {
      throw new InvalidLibraryFileException("Path $path does not exist.");
    }
    $metadata = [];
    $info = $library->getPatternInfo();
    foreach (self::discoverComponents($path, ['json']) as $name => $data) {
      if (empty($data['json']) || !file_exists($data['json'])) {
        continue;
      }
      $category = $info['category'] ?? 'default';
      $library_defaults = [
        '$schema' => 'http://json-schema.org/schema#',
        'assets' => ['schema' => $data['json']],
        'category' => $category,
        'title' => $name,
        'type' => 'object',
        'format' => 'grid',
        'library' => $library->id(),
        'libraryPluginId' => $info['plugin'],
        'license' => $library->license ?? [],
        'name' => $name,
        'properties' => (object) [],
        'required' => [],
        'version' => $library->version ?? '',
      ];
      if (!empty($data['json']) && $file_contents = file_get_contents($data['json'])) {
        $pattern = $this->createPattern($name, (array) $this->serializer::decode($file_contents) + $library_defaults);
        $pattern_path = trim(substr($data['json'], strlen($path), -strlen('.json')), '/\\');
        $category_guess = $library->category ?? strstr($pattern_path, DIRECTORY_SEPARATOR, TRUE);
        $pattern->category = $pattern->get('category')
            ->getValue() ?? $category_guess;
      }
      else {
        // Create the pattern from defaults.
        // @todo Have this cleverly infer defaults from the template.
        $pattern = $this->createPattern($name, $library_defaults);
      }
      $pattern->filename = trim(substr($data['json'], strlen($path)), '/\\');
      $pattern->path = substr($pattern->filename, 0, -strlen('.json'));
      // @todo Set $pattern->url to the actual URL of the pattern.
      // @todo Add default of library version fallback to extension version.
      $pattern->version = $pattern->version ?? 'VERSION';
      $metadata['@' . $library->id() . '/' . $pattern->getPath()] = $pattern;
    }

    foreach ($metadata as $pattern_type => $pattern) {
      // Replace any $ref links with relative paths.
      $schema = json_decode($pattern->getSchema(), TRUE);
      if (isset($schema['properties'])) {
        $schema['properties'] = static::schemaDereference(
          $schema['properties'],
          $pattern
        );
      }
      if (isset($schema['definitions'])) {
        $schema['definitions'] = static::schemaDereference(
          $schema['definitions'],
          $pattern
        );
      }
      $pattern->setSchema($schema);
      $metadata[$pattern_type] = $pattern->toArray();
    }
    return $metadata;
  }

}
