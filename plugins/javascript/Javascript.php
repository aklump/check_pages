<?php

namespace AKlump\CheckPages\Plugin;

use AKlump\CheckPages\Browser\HeadlessBrowserInterface;
use AKlump\CheckPages\Event\AssertEventInterface;
use AKlump\CheckPages\Event\DriverEventInterface;
use AKlump\CheckPages\Event\TestEventInterface;
use AKlump\CheckPages\Assert;

/**
 * Implements the javascript plugin.
 */
final class Javascript extends LegacyPlugin {

  const SEARCH_TYPE = 'javascript';

  /**
   * Captures the test config to share across methods.
   *
   * @var array
   */
  private $assertions;

  /**
   * {@inheritdoc}
   */
  public function onBeforeDriver(TestEventInterface $event) {
    $config = $event->getTest()->getConfig();
    if (empty($config['find'])) {
      return;
    }
    foreach ($config['find'] as $assertion) {
      if (is_array($assertion) && array_key_exists(self::SEARCH_TYPE, $assertion)) {
        $config['js'] = TRUE;
        $event->getTest()->setConfig($config);
        $this->assertions[] = $assertion;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onBeforeRequest(DriverEventInterface $event) {
    $driver = $event->getDriver();
    if (!$driver instanceof HeadlessBrowserInterface) {
      return;
    }
    if (!empty($this->assertions)) {
      foreach ($this->assertions as $assertion) {
        if (!empty($assertion[self::SEARCH_TYPE])) {
          $driver->addJavascriptEval($assertion[self::SEARCH_TYPE]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onBeforeAssert(AssertEventInterface $event) {
    $assert = $event->getAssert();
    $response = $event->getDriver()->getResponse();
    $search_value = $assert->{self::SEARCH_TYPE};
    $assert->setSearch(self::SEARCH_TYPE, $search_value);

    $haystack = json_decode($response->getHeader('X-Javascript-Evals')[0] ?? '{}', TRUE);
    $haystack = array_reduce($haystack, function ($result, $item) use ($search_value) {
      if ($search_value === $item['eval']) {
        $result .= $item['result'];
      }

      return $result;
    });

    // If we are simply running JS, then the assertion is not necessary and
    // we'll make the check the haystack so it will always pass.
    list($assertion_type) = $assert->getAssertion();
    if (is_null($assertion_type)) {
      $assert->setAssertion(Assert::ASSERT_EQUALS, $haystack);
    }

    $assert->setHaystack([$haystack]);
  }

}
