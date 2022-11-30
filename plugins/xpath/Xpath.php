<?php

namespace AKlump\CheckPages\Plugin;

use AKlump\CheckPages\Event;
use AKlump\CheckPages\Event\AssertEventInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Implements the Xpath plugin.
 */
final class Xpath implements EventSubscriberInterface {

  /**
   * @var string
   */
  const SEARCH_TYPE = 'xpath';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      Event::ASSERT_CREATED => [
        function (AssertEventInterface $event) {
          $should_apply = boolval($event->getAssert()->{self::SEARCH_TYPE});
          if ($should_apply) {
            $assert = $event->getAssert();
            $search_value = $assert->{self::SEARCH_TYPE};
            $assert->setSearch(self::SEARCH_TYPE, $search_value);
            $haystack = $assert->getHaystack();
            if (!$haystack instanceof Crawler) {
              $haystack = new Crawler($haystack);
            }
            $haystack = $haystack->filterXPath($search_value);
            $assert->setHaystack($haystack);
          }
        },
      ],
    ];
  }

}
