<?php

namespace AKlump\CheckPages;

/**
 * Manages test variables.
 */
final class Variables implements \Countable {

  private $values = [];

  public function setItem(string $key, $value): self {
    $this->values[$key] = $value;

    return $this;
  }

  public function getItem(string $key) {
    return $this->values[$key] ?? NULL;
  }

  public function removeItem(string $key): self {
    unset($this->values[$key]);

    return $this;
  }

  /**
   * Recursively interpolate using instance variables.
   *
   * @param string|array $value
   *   A string or array of strings to be interpolated.  Interpolation will only
   *   occur if the variable has been set.
   *
   * @return mixed
   *   $value with any possible interpolated values.
   */
  public function interpolate($value, &$context = NULL) {
    $self_method = __FUNCTION__;

    if (!is_array($value)) {

      // Create a per-recursion set of values.  This should not be a class
      // variable because it should only persist for the duration of a single
      // call, and it's child recursions.
      if (!isset($context['find'])) {
        $context['find'] = array_map(function ($key) {
          return "\\\$\{$key\}";
        }, array_keys($this->values));
      }
      foreach ($this->values as $k => $v) {
        $value = str_replace('${' . $k . '}', $v, $value);
      }
      return $value;
    }
    foreach ($value as &$v) {
      $v = $this->{$self_method}($v, $context);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return count($this->values);
  }
}