<?php

namespace AKlump\CheckPages\Handlers;

use AKlump\CheckPages\Event;
use AKlump\CheckPages\Event\SuiteEventInterface;
use AKlump\CheckPages\Exceptions\BadSyntaxException;
use AKlump\CheckPages\Exceptions\SuiteFailedException;
use AKlump\CheckPages\Parts\Test;

/**
 * Implements the Sleep handler.
 */
final class Loop implements HandlerInterface {

  /**
   * @var \AKlump\CheckPages\Handlers\LoopCurrentLoop
   */
  private $currentLoop;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {

    return [
      Event::SUITE_STARTED => [
        function (SuiteEventInterface $event) {
          $suite_has_loops = FALSE;
          foreach ($event->getSuite()->getTests() as $test) {
            if ($test->has('loop') || $test->has('end loop')) {
              $suite_has_loops = TRUE;
              break;
            }
          }
          if (!$suite_has_loops) {
            return;
          }
          try {
            $loop = new self();
            $loop->expandLoops($event);
          }
          catch (\Exception $e) {
            throw new SuiteFailedException($event->getSuite(), $e);
          }
        },
        // It's important that loop runs last over others because others may need
        // to do things first, e.g., the "import" handler.
        -100,
      ],
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
  public function expandLoops(SuiteEventInterface $event) {
    $suite = $event->getSuite();
    $this->currentLoop = NULL;
    foreach ($suite->getTests() as $test) {
      $this->beHelpful($test);
      if ($test->has('loop')) {
        if ($this->currentLoop) {
          throw new BadSyntaxException('Loops may not be nested; you must end the first loop before starting a new one.', $suite);
        }
        try {
          $this->currentLoop = new LoopCurrentLoop($test->get('loop'));
        }
        catch (BadSyntaxException $exception) {

          // Catch and throw with the test context.
          $message = str_replace(BadSyntaxException::PREFIX, '', $exception->getMessage());
          throw new BadSyntaxException($message, $test);
        }
        $suite->removeTest($test);
      }
      elseif ($test->has('end loop')) {
        if (!$this->currentLoop) {
          throw new BadSyntaxException('Invalid `end loop` found; no `loop` was found.', $suite);
        }
        $loop_tests = $this->currentLoop->execute();
        $suite->replaceTestWithMultiple($test, $loop_tests);
        $this->currentLoop = NULL;
      }
      elseif ($this->currentLoop) {
        $this->currentLoop->addTest($test);
        $suite->removeTest($test);
      }
    }
  }

  /**
   * Analyze a test and try to make suggestions if the dev has made a mistake.
   *
   * @param \AKlump\CheckPages\Parts\Test $test
   *
   * @return void
   */
  private function beHelpful(Test $test) {
    $config = $test->getConfig();
    // Watch for "endloop" (a mistake) instead of "end loop" as a closure.
    if (array_key_exists('endloop', $config) && count($config) === 1 && is_null($config['endloop']) && $this->currentLoop) {
      throw new BadSyntaxException('Found "endloop", did you mean "end loop"?');
    }
  }

  public static function getId(): string {
    return 'loop';
  }

}