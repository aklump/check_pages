<?php

namespace AKlump\CheckPages\Parts;

use AKlump\CheckPages\Assert;
use AKlump\CheckPages\Browser\RequestDriverInterface;
use AKlump\CheckPages\Event;
use AKlump\CheckPages\Event\AssertEvent;
use AKlump\CheckPages\Output\Feedback;
use AKlump\CheckPages\Output\Message;
use AKlump\CheckPages\Output\Verbosity;
use AKlump\CheckPages\Traits\HasTestTrait;
use AKlump\Messaging\MessageType;

class AssertRunner {

  use HasTestTrait;

  /**
   * @var \AKlump\CheckPages\Browser\RequestDriverInterface
   */
  protected $driver;

  public function __construct(RequestDriverInterface $driver) {
    $this->driver = $driver;
  }

  /**
   * Run an assert instance.
   *
   * @param \AKlump\CheckPages\Assert $assert
   *
   * @return \AKlump\CheckPages\Assert
   *    The same instance passed in.  The result can be read from getPassed or getFailed.
   */
  public function run(Assert $assert): Assert {
    $assert
      ->setSearch(Assert::SEARCH_ALL)
      ->setHaystack([
        strval($this->driver->getResponse()->getBody()),
      ]);

    $config = $assert->getConfig();
    if (!empty($config[Assert::MODIFIER_ATTRIBUTE])) {
      $assert->setModifer(Assert::MODIFIER_ATTRIBUTE, $config[Assert::MODIFIER_ATTRIBUTE]);
    }

    if ($assert->has(Assert::ASSERT_SETTER)) {
      // This may be overridden below if there is more going on than just `set`,
      // and that's totally fine and the way it should be.  However if only
      // setting, we need to know that later own in the flow.
      $assert->setAssertion(Assert::ASSERT_SETTER, NULL);
    }

    $assertions = array_map(function ($help) {
      return $help->code();
    }, $assert->getAssertionsInfo());
    foreach ($assertions as $code) {
      if (array_key_exists($code, $config)) {
        $assert->setAssertion($code, $config[$code]);
        break;
      }
    }

    $test = $assert->getTest();
    $test->getRunner()->getDispatcher()
      ->dispatch(new AssertEvent($assert, $test, $this->driver), Event::ASSERT_CREATED);

    // Note: if passed or failed that will be checked inside
    // of the method \AKlump\CheckPages\Assert::run
    $assert->run();

    $test->getRunner()->getDispatcher()
      ->dispatch(new AssertEvent($assert, $test, $this->driver), Event::ASSERT_FINISHED);

    $why = strval($assert);
    if ($assert->hasFailed()) {
      if (!empty($config['why'])) {
        $why = "{$config['why']} $why";
      }
      $test->addMessage(new Message(
          [Feedback::FAILED_PREFIX . $why],
          MessageType::ERROR,
          Verbosity::VERBOSE
        )
      );

      $reason = $assert->getReason();
      if ($reason) {
        $test->addMessage(new Message(
            [$reason],
            MessageType::ERROR,
            Verbosity::VERBOSE
          )
        );
      }
    }
    elseif ($assert->hasPassed()) {
      $why = $config['why'] ?? $why;
      if ($why) {
        $test->addMessage(new Message(
            [$why],
            MessageType::SUCCESS,
            Verbosity::VERBOSE
          )
        );
      }
    }

    $test->getRunner()->totalAssertions++;
    if ($assert->hasFailed()) {
      $test->getRunner()->failedAssertionCount++;
    }

    return $assert;
  }

}
