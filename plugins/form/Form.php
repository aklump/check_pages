<?php

namespace AKlump\CheckPages\Plugin;

use AKlump\CheckPages\Event;
use AKlump\CheckPages\Event\SuiteEventInterface;
use AKlump\CheckPages\Exceptions\TestFailedException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Implements the Form plugin.
 */
final class Form implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      Event::SUITE_LOADED => [

        /**
         * Look for "form" implementations and modify the suite.
         *
         * For all tests that use the form plugin, we will inject a test just
         * after, that will use the request plugin to submit to the form.
         */
        function (SuiteEventInterface $event) {
          foreach ($event->getSuite()->getTests() as $test) {
            if ($test->getConfig()['form'] ?? NULL) {
              $test_configs = [];

              // It's important to copy the original, for reasons such as the
              // original may have extra configuration we can't know about, for
              // example the user plugin and authentication.
              $first = $test->getConfig();
              $second = $test->getConfig();

              // The find will be pushed to the secondary test.
              unset($first['find']);
              $first['why'] = sprintf('Load and analyze form (%s)', $first['form']['dom']);
              $test_configs[] = $first;

              // The form should not appear in this test, only the first.
              unset($second['form']);
              $second['url'] = '${formAction}';
              $second['request'] = [
                'method' => '${formMethod}',
                'headers' => [
                  'content-type' => 'application/x-www-form-urlencoded',
                ],
                'body' => '${formBody}',
              ];
              $test_configs[] = $second;

              $event->getSuite()->replaceTestWithMultiple($test, $test_configs);
            }
          }
        },
      ],
      Event::REQUEST_FINISHED => [

        /**
         * Analyze the form and import action, method, values, etc.
         */
        function (Event\DriverEventInterface $event) {
          $test = $event->getTest();
          $config = $test->getConfig();
          if (empty($config['form'])) {
            return;
          }

          $body = strval($event->getDriver()->getResponse()->getBody());
          $crawler = new Crawler($body);
          $form = $crawler->filter($config['form']['dom']);
          if (!$form->count()) {
            throw new TestFailedException($config, new \Exception(sprintf('Cannot find form using DOM selector: %s', $config['form']['dom'])));
          }
          $variables = $test->getSuite()->variables();

          // The form action may or may not be the same URL.
          $action = $form->getNode(0)->getAttribute('action');
          $action = $action ?: $config['url'];
          $variables->setItem('formAction', $action);

          // The form method.
          $method = $form->getNode(0)->getAttribute('method');
          $variables->setItem('formMethod', $method ?: 'post');

          // We will allow imports on form.input.
          if (isset($config['form']['input']) && is_array($config['form']['input'])) {
            $importer = new Importer($test->getRunner());
            $importer->resolveImports($config['form']['input']);
          }

          $test_provided = [];
          $form_values = $config['form']['input'] ?? [];
          if ($form_values) {
            $test->interpolate($form_values);
            // Give an key/name index for later lookup.
            $test_provided = array_combine(array_map(function ($item) {
              return $item['name'];
            }, $form_values), $form_values);
          }

          $determine_value = function (\DOMElement $node) use (&$form_values, $test_provided) {
            $name = $node->getAttribute('name');
            if ($name && isset($test_provided[$name]) || !array_key_exists($name, $form_values)) {
              $value = self::getElementValue($node, $test_provided[$name] ?? []);
              $form_values[$name] = $value;
            }
          };

          // ...then add any non-existent values, pulling from the form inputs.
          $submit_selector = $config['form']['submit'] ?? '[type="submit"]';
          $submit = $form->filter($submit_selector)->getNode(0);
          if ($submit) {
            $determine_value($submit);
          }

          // Iterate over all supported DOM elements in the form and add user
          // values or default values as appropriate.
          foreach ($form->filter('input,select') as $el) {
            if ('submit' !== $el->getAttribute('type')) {
              $determine_value($el);
            }
          }

          // The last step is to add any test-provided values that did not match
          // up to the form.  They must be included because the test says so.
          // This is actually reasonable in the case of dynamic, ajax-forms that
          // may not be fully loaded when it gets analyzed.  There is a
          // limitation here, because only the "value" key can be used when the
          // form element cannot be analyzed; in other words "option" will not
          // work without a DomElement.
          $missing_values = array_filter(array_map(function ($item) {
            return $item['value'] ?? NULL;
          }, $test_provided));
          $form_values += $missing_values;

          $variables->setItem('formBody', http_build_query($form_values));
        },
      ],
    ];
  }

  private static function getElementValue(\DOMElement $el, array $context = []) {
    if (array_key_exists('value', $context)) {
      return $context['value'];
    }

    switch ($el->tagName) {
      case 'select':
        $crawler = new Crawler($el);
        if (array_key_exists('option', $context)) {
          // Lookup the option value based on the option label passed in $context.
          $options = $crawler->filter('option')->extract(['_text', 'value']);
          foreach ($options as $item) {
            if ($context['option'] === $item[0]) {
              return $item[1];
            }
          }
        }

        return static::getElementDefaultValue($el);

      default:
        return static::getElementDefaultValue($el);
    }
  }

  private static function getElementDefaultValue(\DOMElement $el) {
    switch ($el->tagName) {
      case 'select':
        $crawler = new Crawler($el);
        $selected = $crawler->filter('option[selected]');
        if (!$selected->count()) {
          $selected = $crawler->filter('option');
        }

        return $selected->getNode(0)->getAttribute('value');

      default:
        return $el->getAttribute('value');
    }
  }

}

