<?php

namespace AKlump\CheckPages\Handlers;

use AKlump\CheckPages\Traits\SetTrait;

/**
 * Implements the Bash handler.
 */
final class Bash extends CommandLineTestHandlerBase {

  use SetTrait;

  /**
   * {@inheritdoc}
   */
  public static function getId(): string {
    return 'bash';
  }

  protected function prepareCommandForCLI(string $command): string {
    return $command;
  }
}
