<?php

namespace AKlump\CheckPages\Service;

use AKlump\CheckPages\CheckPages;
use AKlump\CheckPages\Plugin\HandlersManager;
use AKlump\LoftLib\Code\Strings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;

final class AppCompiler {

  /**
   * @var string
   */
  private $masterSchemaPath;

  /**
   * @var \AKlump\CheckPages\Plugin\HandlersManager
   */
  private $pluginsManager;

  /**
   * @var array
   */
  private $schema;

  /**
   * @var string
   */
  private $examplesPath;

  /**
   * @var string
   */
  private $generatedSchemaPath;

  /**
   * @var string
   */
  private $masterComposerJsonPath;

  /**
   * @var string
   */
  private $generatedComposerJsonPath;


  /**
   * AppCompiler constructor.
   *
   * @param \AKlump\CheckPages\Plugin\HandlersManager $plugins_manager
   * @param string $master_schema_path
   * @param string $generated_schema_path
   * @param string $master_services_path
   * @param string $generated_services_path
   * @param string $examples_path
   */
  public function __construct(
    HandlersManager $plugins_manager,
    string $master_schema_path,
    string $generated_schema_path,
    string $master_services_path,
    string $generated_services_path,
    string $examples_path
  ) {
    $this->pluginsManager = $plugins_manager;

    $this->masterServicesPath = $master_services_path;
    $this->generatedServicesPath = $generated_services_path;
    $this->services = Yaml::parseFile($this->masterServicesPath);

    $this->masterSchemaPath = $master_schema_path;
    $this->schema = ['readyOnly' => TRUE] + $this->loadJson($master_schema_path);
    $description = $this->schema['description'] ?? '';
    $description .= '  DO NOT EDIT THIS FILE.  AUTO-GENERATED FROM ' . basename($master_schema_path);
    $this->schema = array_filter([
        '$schema' => $this->schema['$schema'] ?? NULL,
        'title' => $this->schema['title'] ?? NULL,
        'description' => trim($description),
      ]) + $this->schema;

    $this->generatedSchemaPath = $generated_schema_path;
    $this->examplesPath = $examples_path;
  }

  /**
   * Compile all handlers into the app.
   */
  public function compile() {
    $handlers = $this->pluginsManager->getAllHandlers();
    foreach (array_keys($handlers) as $key) {
      $this->compileHandler($handlers[$key]);
    }

    $this->createTestRunnerPhpFile($handlers);
    $this->generateServicesFile();

    // This has to come last so handlers may affect the compile files.
    foreach ($handlers as $handler) {
      $special_compiler_path = $handler['path'] . '/compile.php';
      if (file_exists($special_compiler_path)) {
        $output_base_dir = $this->examplesPath;
        $result = require $special_compiler_path;
        if (!$result) {
          throw new \RuntimeException(sprintf("Compilation failed in %s.", basename($handler['path']) . '/compile.php'));
        }
      }
    }
  }

  private function createTestRunnerPhpFile($handlers) {
    $runner_code = [
      '<?php',
      "/** AUTOGENERATED DO NOT EDIT */",
      "add_directory(__DIR__);",
      "load_config('config/local');",
      "add_mixin('http_request_files');",
    ];

    foreach ($handlers as $handler) {
      if ($handler['has_tests']) {
        $runner_code[] = sprintf('run_suite("%s/%s");', CheckPages::DIR_HANDLERS, $handler['id']);
      }
    }
    $runner_file_path = $this->examplesPath . '/tests/handlers_runner.php';
    $result = file_put_contents($runner_file_path, implode(PHP_EOL, $runner_code));
    if (!$result) {
      throw new \RuntimeException(sprintf('Cannot create runner file: %s', $runner_file_path));
    }
  }

  /**
   * Compile the plugin schema file.
   *
   * @param array &$handler
   *   The handle info array.
   */
  private function compileHandler(array &$handler) {
    $this->compilePluginSchema($handler['id'], $handler['path']);
    $handler['has_tests'] = $this->handleTests($handler['id'], $handler['path']);

    // Create a service if an event subscriber.
    $instance = $this->pluginsManager->getHandlerInstance($handler['id']);
    if ($instance instanceof EventSubscriberInterface) {
      $handler_services = [
        'services' => [
          $handler['id'] . '.handler' => [
            'class' => '\\' . ltrim($handler['classname'], '\\'),
            'tags' => [['name' => 'event_subscriber']],
          ],
        ],
      ];
      $this->services = array_merge_recursive($this->services, $handler_services);
    }
  }

  private function ensureDir($filepath) {
    if (!is_dir($filepath)) {
      mkdir($filepath, 0755, TRUE);
    }
  }

  /**
   * Compile the plugin schema file.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $handler_path
   *   The filepath to the plugin definition directory.
   */
  private function handleTests(string $id, string $handler_path) {
    $this->ensureDir(sprintf('%s/tests/%s', $this->examplesPath, CheckPages::DIR_HANDLERS));
    $this->ensureDir(sprintf('%s/web/%s', $this->examplesPath, CheckPages::DIR_HANDLERS));

    // Locate the file with CheckPages::FILENAME_HANDLERS_TEST_SUBJECT.
    $original_url = array_values(array_filter(scandir($handler_path), function ($path) {
      return pathinfo($path, PATHINFO_FILENAME) === CheckPages::FILENAME_HANDLERS_TEST_SUBJECT;
    }))[0] ?? NULL;

    $has_own_test_subject_file = TRUE;
    if ($original_url) {
      $compiled_url = CheckPages::DIR_HANDLERS . "/$id." . pathinfo($original_url, PATHINFO_EXTENSION);
    }
    else {
      $original_url = CheckPages::FILENAME_HANDLERS_TEST_SUBJECT;
      $compiled_url = 'index';
      $has_own_test_subject_file = FALSE;
    }

    // Process path changes in test_subject and copy to web server.
    if ($has_own_test_subject_file) {
      $contents = file_get_contents("$handler_path/$original_url");
      $contents = str_replace($original_url, $compiled_url, $contents);
      file_put_contents($this->examplesPath . "/web/$compiled_url", $contents);
    }

    $handler_provides_tests = file_exists($handler_path . '/suite.yml');

    // Process path changes YAML suite file and copy.
    if ($handler_provides_tests) {
      $suite_raw_contents = file_get_contents("$handler_path/suite.yml");
      if ($suite_raw_contents) {
        $suite_raw_contents = str_replace($original_url, $compiled_url, $suite_raw_contents);
      }
      $handler_suite = Yaml::parse($suite_raw_contents);
      $compiled_path = $this->examplesPath . "/tests/handlers/$id.yml";
      file_put_contents($compiled_path, Yaml::dump($handler_suite, 6));
    }

    return $handler_provides_tests;
  }

  /**
   * Compile the handler schema files.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $path_to_handler
   *   The filepath to the plugin definition directory.
   */
  private function compilePluginSchema(string $id, string $path_to_handler) {
    if (isset($this->schema['definitions'][$id])) {
      throw new \RuntimeException(sprintf('Plugin ID conflict; %s.definitions.%s already exists', basename($this->masterSchemaPath), $id));
    }

    $before = md5(json_encode($this->schema));

    // The handler MAY provide new scheme definitions.
    $definitions_schema = $path_to_handler . '/schema.definitions.json';
    if (file_exists($definitions_schema)) {
      $definitions_schema = $this->loadJson($definitions_schema);
      $conflicting_keys = array_intersect_key($this->schema['definitions'], $definitions_schema);
      if ($conflicting_keys) {
        throw new \RuntimeException(sprintf('The following definitions exist in the master schema and cannot be added by the handler %s: %s', $id, implode(', ', $conflicting_keys)));
      }
      $this->schema['definitions'] += $definitions_schema;
    }

    // The handler MAY provide test-level schema.
    $test_schema = $path_to_handler . '/schema.test.json';
    if (file_exists($test_schema)) {
      $test_schema = $this->loadJson($test_schema);
      $test_schema = $this->handleGlobalSchemaProperties($test_schema);
      $this->schema["items"]["anyOf"][] = $test_schema;
    }

    // The handler must provide the assertion-level schema.
    $assertion_schema = $path_to_handler . '/schema.assertion.json';
    if (file_exists($assertion_schema)) {
      $this->schema['definitions'][$id] = [
        'title' => Strings::title("$id Plugin Assertion"),
      ];
      $assertion_schema = $this->loadJson($assertion_schema);
      $assertion_schema = $this->handleGlobalSchemaProperties($assertion_schema);
      $this->schema['definitions'][$id] += $assertion_schema;
      $this->schema['definitions']['find']['oneOf'][1]['items']['oneOf'][] = [
        '$ref' => "#/definitions/$id",
      ];
    }

    if ($before !== md5(json_encode($this->schema))) {
      $this->saveJson($this->generatedSchemaPath, $this->schema);
    }
  }

  /**
   * Add global vars to a handler's schema.
   *
   * @param array $schema
   *   The test or assertion schema defined by the handler.
   *
   * @return array
   *   The schema with globals set.
   *
   * @throws \RuntimeException If the handler provides any globals.
   */
  private function handleGlobalSchemaProperties(array $schema) {
    if (empty($schema['properties'])) {
      return $schema;
    }

    $globals = [
      'why' => '#/definitions/why',
      'extras' => '#/definitions/extras',
      'expected outcome' => '#/definitions/expected_outcome',
    ];

    $properties = [];
    foreach ($globals as $property => $ref) {
      if (isset($schema[$property])) {
        throw new \RuntimeException(sprintf('Your handler schema must not include "%s".', $property));
      }
      $properties[$property] = [
        '$ref' => $ref,
      ];
    }

    // Move globals to the top.
    $schema['properties'] = $properties + $schema['properties'];

    return $schema;
  }

  private function loadJson(string $filepath): array {
    $data = json_decode(file_get_contents($filepath), TRUE);
    if (is_null($data)) {
      throw new \RuntimeException(sprintf('Invalid JSON in %s', $filepath));
    }

    return $data;
  }

  private function saveJson($filepath, $data) {
    $result = file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if (!$result) {
      throw new \RuntimeException(sprintf('Could not save %s', $filepath));
    }

    return $this;
  }

  private function generateServicesFile() {
    $result = file_put_contents($this->generatedServicesPath, Yaml::dump($this->services, 4));
    if (!$result) {
      throw new \RuntimeException(sprintf('Could not save %s', $this->generatedServicesPath));
    }

    return $this;
  }

}
