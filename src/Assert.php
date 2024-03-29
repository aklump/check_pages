<?php

namespace AKlump\CheckPages;

use AKlump\CheckPages\AssertType\Contains;
use AKlump\CheckPages\AssertType\Equals;
use AKlump\CheckPages\AssertType\Matches;
use AKlump\CheckPages\AssertType\NotContains;
use AKlump\CheckPages\AssertType\NotEquals;
use AKlump\CheckPages\AssertType\NotMatches;
use AKlump\CheckPages\AssertType\NotText;
use AKlump\CheckPages\AssertType\Text;
use AKlump\CheckPages\Exceptions\TestFailedException;
use AKlump\CheckPages\Helpers\CrawlerToArray;
use AKlump\CheckPages\Interfaces\HasConfigInterface;
use AKlump\CheckPages\Parts\Test;
use AKlump\CheckPages\Traits\HasConfigTrait;
use AKlump\CheckPages\Traits\PassFailTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Handle the search and asserts.
 */
final class Assert implements HasConfigInterface {

  use PassFailTrait;
  use HasConfigTrait;

  /**
   * @var string
   */
  const SEARCH_ALL = 'page';

  /**
   * @var string
   */
  const MODIFIER_ATTRIBUTE = 'attribute';

  /**
   * @var string
   */
  const MODIFIER_PROPERTY = 'style';

  /**
   * @var string
   */
  const ASSERT_SETTER = 'set';

  /**
   * @var string
   */
  const ASSERT_EQUALS = 'is';

  /**
   * @var string
   */
  const ASSERT_NOT_EQUALS = 'is not';

  /**
   * @var string
   */
  const ASSERT_TEXT = 'text';

  const ASSERT_NOT_TEXT = 'not text';

  /**
   * @var string
   */
  const ASSERT_MATCHES = 'matches';

  /**
   * @var string
   */
  const ASSERT_NOT_MATCHES = 'not matches';

  /**
   * @var string
   */
  const ASSERT_CONTAINS = 'contains';

  /**
   * @var string
   */
  const ASSERT_NOT_CONTAINS = 'not contains';

  /**
   * @var string
   *
   * Indicates the assert will use a callback defined as the assert value.
   */
  const ASSERT_CALLABLE = 'callable';

  protected $needle;

  /**
   * @var string
   */
  private $searchType;

  /**
   * @var mixed
   */
  private $searchValue;

  /**
   * Plugins may provide a callback for casting this to a string.
   *
   * @var callable
   */
  private $toStringOverride;

  /**
   * The string value of this object.
   *
   * If not manually set, it will be calculated.
   *
   * @var string
   */
  private $label = '';

  /**
   * @var string
   */
  private $assertType;

  /**
   * @var mixed
   */
  private $assertValue;

  /**
   * One of the self::MODIFIER_* values.
   *
   * @var string
   */
  private $modifierType;

  /**
   * @var string
   */
  private $modifierValue;

  /**
   * That which is to be searched.
   *
   * @var array
   */
  private $haystack = [];

  /**
   * @var string
   */
  private $reason = '';

  public function setReason(string $reason): self {
    $this->reason = $reason;

    return $this;
  }

  /**
   * @var string
   */
  private $id;

  /**
   * @var \AKlump\CheckPages\Parts\Test
   */
  private $test;

  /**
   * Assert constructor.
   *
   * @param string $id
   * @param array $config
   *   The raw assert key/value array.
   * @param string $id
   *   An arbitrary value to track this assert by outside consumers.
   */
  public function __construct(string $id, array $config, Test $test) {
    $this->setConfig($config);
    $this->id = $id;
    $this->test = $test;
  }

  public function getTest(): Test {
    return $this->test;
  }

  /**
   * @return string
   */
  public function getLabel(): string {
    return $this->label ?: strval($this);
  }

  /**
   * @param string $label
   *
   * @return
   *   Self for chaining.
   */
  public function setLabel(string $label): self {
    $this->label = $label;

    return $this;
  }

  public function id(): string {
    return $this->id;
  }

  /**
   * Allow direct access to configuration properties, e.g. $this->style
   *
   * @param $key
   *
   * @return mixed|null
   *
   * @deprecated
   */
  public function __get($key) {
    return $this->getConfig()[$key] ?? NULL;
  }

  public function __isset($key) {
    return array_key_exists($key, $this->getConfig());
  }

  /**
   * Set the assert modifier.
   *
   * @param string $modifierValue
   *
   * @return \AKlump\CheckPages\Assert
   *   Self for chaining.
   */
  public function setModifer(string $type, $value): self {
    if (!in_array($type, [
      self::MODIFIER_ATTRIBUTE,
      self::MODIFIER_PROPERTY,
    ])) {
      throw new \InvalidArgumentException("Unrecognized modifier: {$type}");
    }
    $this->modifierType = $type;
    $this->modifierValue = $value;

    return $this;
  }

  /**
   * Return the modifier for this assertion.
   *
   * @return array
   *   - 0 The modifier type.
   *   - 1 The modifier value.
   */
  public function getModifer(): array {
    return [$this->modifierType, $this->modifierValue];
  }

  /**
   * Overwrite the haystack.
   *
   * @param array|Crawler $haystack
   *   The haystack to search.  If the value is a string then simply wrap it as the only element of an indexed array, e.g., `[$string]`.
   *
   * @return $this
   *   Self for chaining.
   *
   * @throws \InvalidArgumentException
   *   If $haystack is invalid.
   */
  public function setHaystack($haystack): self {
    if (!is_array($haystack) && !$haystack instanceof Crawler) {
      throw new \InvalidArgumentException(sprintf('$haystack must be an array or an instance of %s; not an %s', Crawler::class, gettype($haystack)));
    }
    $this->haystack = $haystack;

    return $this;
  }

  /**
   * @return array|Crawler
   */
  public function getHaystack() {
    return $this->haystack;
  }

  /**
   * Set the type of search to perform.
   *
   * @param int $type
   *   The search type.
   * @param null $value
   *   The search value, if needed.
   *
   * @return $this
   *   Self for chaining.
   */
  public function setSearch(string $type, $value = NULL): self {
    $this->searchType = $type;
    $this->searchValue = $value;

    return $this;
  }

  /**
   * Return the search for this assertion.
   *
   * @return array
   *   - 0 The search type.
   *   - 1 The search value.
   */
  public function getSearch(): array {
    return [$this->searchType, $this->searchValue];
  }

  /**
   * Set the type of assertion to execute.
   *
   * @param int $type
   *   The assert type.
   * @param mixed $expected
   *   The expected value.
   *
   * @return $this
   *   Self for chaining.
   */
  public function setAssertion(string $type, $expected): self {
    $this->assertType = $type;
    $this->assertValue = $expected;

    return $this;
  }

  public function getAssertion(): array {
    return [$this->assertType, $this->assertValue];
  }

  /**
   * Run the test assertion.
   *
   * @see \AKlump\CheckPages\Assert::hasPassed()
   * @see \AKlump\CheckPages\Assert::hasFailed()
   * @see \AKlump\CheckPages\Assert::getReason()
   */
  public function run() {

    // Do not allow this to run a second time.  It may have already been run in
    // an event listener.
    if ($this->hasPassed() || $this->hasFailed()) {
      return;
    }

    // We have to start with passing true and let the individual asserts fail an
    // assertion.  This is important because of the count assertion logic, for
    // one.
    $pass = TRUE;

    $haystack = $this->haystack;

    // The asserts run against an array, so if $haystack is a Crawler, it must
    // be converted to an array before the asserts are tested.
    if ($haystack instanceof Crawler) {
      $haystack = (new CrawlerToArray())($this, $haystack, $pass);
    }

    // In order that count works correctly, all of these cases must fill
    // $countable in whatever length is appropriate to that type of assert, as
    // $countable is the subject of the count assertion.  For example when nodes
    // match by text, then only those matches should be included in countable.
    $countable = [];

    switch ($this->assertType) {

      case self::ASSERT_SETTER:
        $countable = $haystack;
        $pass = TRUE;
        $this->setNeedleIfNotSet($haystack[0] ?? NULL);
        break;

      case self::ASSERT_CALLABLE:
        $countable = $haystack;
        try {
          $callback = $this->assertValue;
          $pass = $callback($this);
          if (TRUE !== $pass) {
            trigger_error(sprintf('The assert type "%s" must return TRUE or throw \AKlump\CheckPages\Exceptions\TestFailedException if the assertion failed.', $this->assertType));
            $pass = FALSE;
          }
        }
        catch (TestFailedException $exception) {
          $pass = FALSE;
          $this->reason = $exception->getMessage();
        }
        break;

      case self::ASSERT_TEXT:
        $pass = (new Text())($this, $haystack, $countable);
        break;

      case self::ASSERT_NOT_TEXT:
        $pass = (new NotText())($this, $haystack, $countable);
        break;

      case self::ASSERT_CONTAINS:
        $pass = (new Contains())($this, $haystack, $countable);
        break;

      case self::ASSERT_NOT_CONTAINS:
        $pass = (new NotContains())($this, $haystack, $countable);
        break;

      case self::ASSERT_EQUALS:
        $pass = (new Equals())($this, $haystack, $countable);
        break;

      case self::ASSERT_NOT_EQUALS:
        $pass = (new NotEquals())($this, $haystack, $countable);
        break;

      case self::ASSERT_MATCHES:
        $pass = (new Matches())($this, $haystack, $countable);
        break;

      case self::ASSERT_NOT_MATCHES:
        $pass = (new NotMatches())($this, $haystack, $countable);
        break;

      default:
        // TODO Should we throw if no assert type is defined?
        $countable = $haystack;
        $pass = FALSE;
        break;
    }

    $this->setHaystack($countable);

    if ($pass) {
      $this->setPassed();
    }
    else {
      $this->setFailed();
    }
  }

  /**
   * Set the needle value only if it's null.
   *
   * @param $value
   *   The value of the needle.
   *
   * @return $this
   *   Self for chaining.
   */
  public function setNeedleIfNotSet($value): self {
    if (is_null($this->getNeedle())) {
      $this->setNeedle($value);
    }

    return $this;
  }

  /**
   * Set the needle value regardless of current value.
   *
   * @param $value
   *   The new needle value.
   *
   * @return $this
   *   Self for chaining.
   */
  public function setNeedle($value): self {
    $this->needle = $value;

    return $this;
  }

  /**
   * Return the needle in the haystack used to determine the result.
   *
   * @return mixed
   */
  public function getNeedle() {
    return $this->needle;
  }

  /**
   * Get the failure reason.
   *
   * @return string
   *   The reason for failure.
   */
  public function getReason(): string {
    return $this->reason;
  }

  /**
   * If this is set the assert run() will be bypassed.
   *
   * @param bool $result
   *   Override the assertion with a set value.
   * @param string $reason
   *   Optional.  Give the reason for overriding the assert.
   *
   * @return \AKlump\CheckPages\Assert
   *   Self for chaining.
   */
  public function setResult(bool $result, string $reason = ''): self {
    if ($result) {
      $this->setPassed();
    }
    else {
      $this->setFailed();
    }
    $this->reason = $reason ?: sprintf('The result was set using %s', __METHOD__);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    if ($this->label) {
      return $this->label;
    }
    $prefix = '';
    $suffix = '';
    $modifier = '';
    switch ($this->modifierType) {
      case static::MODIFIER_ATTRIBUTE:
        $modifier = "attribute \"{$this->modifierValue}\" ";
        break;

      case static::MODIFIER_PROPERTY:
        $modifier = "style property \"{$this->modifierValue}\" ";
        break;
    }

    switch ($this->assertType) {
      case static::ASSERT_MATCHES:
        $prefix = sprintf('Match %sagainst RegExp "%s"', $modifier, $this->assertValue);
        break;

      case static::ASSERT_NOT_MATCHES:
        $prefix = sprintf('Do not match %s against RegExp "%s"', $modifier, $this->assertValue);
        break;

      case static::ASSERT_TEXT:
      case static::ASSERT_NOT_TEXT:
        $prefix = sprintf('Convert %sto plaintext and compare to "%s"', $modifier, $this->assertValue);
        break;

      case static::ASSERT_EQUALS:
      case static::ASSERT_NOT_EQUALS:
        $modifier = $modifier ? "of $modifier" : '';
        $prefix = sprintf('Compare value %sto "%s"', $modifier, $this->assertValue);
        break;

      case static::ASSERT_NOT_CONTAINS:
        $prefix = sprintf('"%s" was not found', $this->assertValue);
        break;

      case static::ASSERT_CONTAINS:
        $modifier = $modifier ? ' in ' . trim($modifier) : ' in value';
        $prefix = sprintf('Find "%s"%s', $this->assertValue, $modifier);
        break;
    }

    switch ($this->searchType) {
      //      case Style::SEARCH_TYPE:
      case 'style':
        $suffix = sprintf('for the element "%s"', $this->searchValue);
        break;

      case static::SEARCH_ALL:
        $suffix = 'on the page';
        break;

      // TODO This won't work until autoloader is fixed for plugins.
      //      case Xpath::SEARCH_TYPE:
      case 'xpath':
        if ($prefix) {
          $suffix = sprintf('after filtering by XPath "%s"', $this->searchValue);
        }
        else {
          $suffix = sprintf('Filter by XPath "%s"', $this->searchValue);
        }
        break;

      // TODO This won't work until autoloader is fixed for plugins.
      //      case static::SEARCH_DOM:
      case 'dom':
        if ($prefix) {
          $suffix = sprintf('after selecting with "%s"', $this->searchValue);
        }
        else {
          $suffix = sprintf('Select with "%s"', $this->searchValue);
        }
        break;

      case 'javascript':
        // TODO This won't work until autoloader is fixed for plugins.
        //      case Javascript::SEARCH_TYPE:

        if ($prefix) {
          $suffix = sprintf('after JS evaluation of "%s"', $this->searchValue);
        }
        else {
          $suffix = sprintf('Evaluate JS code "%s"', $this->searchValue);
        }
        break;
    }

    $string = ltrim(implode(' ', array_filter([$prefix, $suffix])) . '.', '.');

    // Allow others to modify the string output.
    if (is_callable($this->toStringOverride)) {
      $string = call_user_func($this->toStringOverride, $string, $this);
    }

    return $string ?: sprintf('Assert: %s', json_encode($this->getConfig()));
  }

  /**
   * Return the search and assert intersections for a modifier.
   *
   * @param string $modifier
   *   One of the self::MODIFIER_* values.
   *
   * @return array|\string[][]
   *   With keys:
   *   - search array
   *   - assert array
   */
  public function getIntersectionsByModifier(string $modifier): array {
    $intersections = [];
    $intersections[self::MODIFIER_PROPERTY] = [
      'search' => [Style::SEARCH_TYPE],
      'assert' => [
        self::ASSERT_CONTAINS,
        self::ASSERT_EQUALS,
        self::ASSERT_NOT_EQUALS,
        self::ASSERT_MATCHES,
        self::ASSERT_NOT_MATCHES,
      ],
    ];
    $intersections[self::MODIFIER_ATTRIBUTE] = [
      'search' => [self::SEARCH_DOM, Xpath::SEARCH_TYPE],
      'assert' => [
        self::ASSERT_CONTAINS,
        self::ASSERT_EQUALS,
        self::ASSERT_NOT_EQUALS,
        self::ASSERT_MATCHES,
        self::ASSERT_NOT_MATCHES,
      ],
    ];

    return $intersections[$modifier] ?? [];
  }

  /**
   * Return information about available selectors.
   *
   * @return \AKlump\CheckPages\Help[]
   */
  public function getSelectorsInfo(): array {
    return [
      //      new Help(Dom::SEARCH_TYPE, "Select from the DOM using CSS selectors.", [
      //        'p.summary',
      //        'main',
      //        '.story__title',
      //        '\'#edit-submit[value="Create new account"]\'',
      //      ]),
      //            new Help(Style::SEARCH_TYPE, "Select computed styles for an element using CSS selectors.", [
      //        'p.summary',
      //        'main',
      //        '.story__title',
      //        '\'#edit-submit[value="Create new account"]\'',
      //      ]),
      //      new Help(Xpath::SEARCH_TYPE, "Select from the DOM using XPath selectors.", ['(//*[contains(@class, "block-title")])[3]']),
      //            new Help(Javascript::SEARCH_TYPE, "Select the result of a javascript expression", ['location.hash']),
    ];
  }

  /**
   * Return information about modifiers.
   *
   * @return \AKlump\CheckPages\Help[]
   */
  public function getModifiersInfo() {
    return [
      new Help(self::MODIFIER_ATTRIBUTE, "Modify the assert to act against an element's DOM attribute.  Must be combined with `dom` or `xpath`.  Does not work with all assertions.", [
        'id',
        'data-foo',
        'class',
      ]),
      new Help(self::MODIFIER_PROPERTY, "Indicate which style property to assert against.  Used for style searches.", [
        'id',
        'data-foo',
        'class',
      ]),
    ];
  }

  /**
   * Return information about available assertions.
   *
   * @return \AKlump\CheckPages\Help[]
   */
  public function getAssertionsInfo(): array {
    return [
      new Help(self::ASSERT_NOT_CONTAINS, 'Pass if the value is not found in the selection.', ['[token:123]']),
      new Help(self::ASSERT_CONTAINS, 'Pass if the value is found in the selection. Works with `attribute`.', ['foo']),
      new Help(self::ASSERT_EQUALS, "Pass if the selection's markup is equal.  All numeric values, regardless of type are considered equal.  Works with `attribute`.", ['<em>lorem <strong>ipsum dolar</strong> sit amet.</em>']),
      new Help(self::ASSERT_NOT_EQUALS, "Pass if the selection's markup is not equal to the search value exactly.  All numeric values, regardless of type are considered an exact match.  Works with `attribute`.", ['<em>lorem <strong>ipsum dolar</strong> sit amet.</em>']),
      new Help(self::ASSERT_MATCHES, 'Applies a REGEX expression against the selection. Works with `attribute`.', ['/copyright\s+20\d{2}$/']),
      new Help(self::ASSERT_NOT_MATCHES, 'Do not match REGEX expression against the selection. Works with `attribute`.', ['/copyright\s+20\d{2}$/']),
      new Help(self::ASSERT_TEXT, "Pass if the selection's text value (all markup removed) matches exactly.", ['lorem ipsum dolar sit amet.']),
      new Help(self::ASSERT_NOT_TEXT, "Pass if the selection's text value (all markup removed) does not match exactly.", ['lorem ipsum dolar sit amet.']),
    ];
  }

  /**
   * Register a callback to use for altering the stringification of this.
   *
   * @param callable $callback
   *   A callback with signature (string, Assert): string.
   *
   * @return $this
   *   Self for chaining.
   */
  public function setToStringOverride(callable $callback): Assert {
    $this->toStringOverride = $callback;

    return $this;
  }

}
