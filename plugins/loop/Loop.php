<?php

namespace AKlump\CheckPages\Plugin;

use AKlump\CheckPages\Event;
use AKlump\CheckPages\Event\SuiteEventInterface;
use AKlump\CheckPages\Exceptions\BadSyntaxException;
use AKlump\CheckPages\Variables;
use AKlump\CheckPages\Parts\Test;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Implements the Sleep plugin.
 */
final class Loop implements EventSubscriberInterface {

  /**
   * @var \AKlump\CheckPages\Parts\Test[]
   */
  private $tests = [];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // It's important that loop runs last over others because others may need
      // to do things first, e.g., the "import" plugin.
      Event::SUITE_LOADED => [[self::class, 'expandLoops'], -100],
    ];
  }

  /**
   * Event handler to expand loops.
   *
   * @param \AKlump\CheckPages\Event\SuiteEventInterface $event
   *
   * @return void
   * @throws \AKlump\CheckPages\Exceptions\StopRunnerException
   */
  public static function expandLoops(SuiteEventInterface $event) {
    $suite = $event->getSuite();
    $current_loop = NULL;
    foreach ($suite->getTests() as $test) {
      $test_config = $test->getConfig();
      if (array_key_exists('loop', $test_config)) {
        if ($current_loop) {
          throw new BadSyntaxException('Loops may not be nested; you must end the first loop before starting a new one.', $suite);
        }
        try {
          $current_loop = new CurrentLoop($test_config['loop']);
        }
        catch (BadSyntaxException $exception) {

          // Catch and throw with the test context.
          $message = str_replace(BadSyntaxException::PREFIX, '', $exception->getMessage());
          throw new BadSyntaxException($message, $test);
        }
        $suite->removeTest($test);
      }
      elseif (array_key_exists('end loop', $test_config)) {
        if (!$current_loop) {
          throw new BadSyntaxException('Invalid `end loop` found; no `loop` was found.', $suite);
        }
        $loop_tests = $current_loop->execute();
        $suite->replaceTestWithMultiple($test, $loop_tests);
        $current_loop = NULL;
      }
      elseif ($current_loop) {
        $current_loop->addTest($test);
        $suite->removeTest($test);
      }
    }
  }
}

/**
 * An object to contain a single loop instance.
 */
final class CurrentLoop {

  /**
   * @var array|object
   */
  private $definition;

  /**
   * @var int
   */
  private $count;

  public function __construct($definition) {

    // Expand string shorthand.
    if (is_string($definition)) {
      if (preg_match('/^(\d+)\.\.\.(\d+)$/', $definition, $matches)) {
        $definition = range($matches[1], $matches[2]);
      }
      elseif (preg_match('/^(\d+)x$/i', $definition, $matches)) {
        $definition = range(1, $matches[1]);
      }
      else {
        throw new BadSyntaxException(sprintf('Invalid loop expression: %s', $definition));
      }
      $this->count = count($definition);
      $this->definition = $definition;
    }
    else {
      $this->count = count($definition);

      // Sort out indexed arrays, and relative arrays as objects.
      $this->definition = json_decode(json_encode($definition));
    }

    // Convert arrays to 1-based indexes.
    if (is_array($this->definition)) {
      $this->definition = array_combine(range(1, $this->count), $this->definition);
    }
  }

  /**
   * Add a test to the loop instance.
   *
   * @param \AKlump\CheckPages\Parts\Test $test
   *
   * @return void
   */
  public function addTest(Test $test) {
    $this->tests[] = $test;
  }

  /**
   * Execute the loop and return the test configurations.
   *
   * @return array
   *   An array of Test configurations spawned during the loop execution.
   *
   * @see \AKlump\CheckPages\Parts\Suite::replaceTestWithMultiple()
   */
  public function execute(): array {
    /** @var \AKlump\CheckPages\Variables $variables */
    $key_key = is_object($this->definition) ? 'property' : 'index';

    $iterations = [];
    $counter = 1;
    foreach ($this->definition as $key => $value) {
      $variables = new Variables();
      $variables->setItem('loop.length', $this->count);
      $variables->setItem('loop.' . $key_key, $key);
      if (is_array($this->definition)) {
        $variables->setItem('loop.index0', $key - 1);
      }
      $variables->setItem('loop.value', $value);
      $variables->setItem('loop.last', $counter++ === $this->count);

      foreach ($this->tests as $test) {
        $iterations[] = $test->reinterpolate()->getConfig();
      }
    }

    return $iterations;
  }

}
