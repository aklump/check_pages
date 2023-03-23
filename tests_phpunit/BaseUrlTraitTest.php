<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers BaseUrlTrait
 */
class BaseUrlTraitTest extends TestCase {

  public function testWithForeignUrl() {
    $obj = new BaseUrlTraitTestable();
    $obj->setBaseUrl('https://www.foo.com/');
    $this->assertSame('http://github.com', $obj->withBaseUrl('http://github.com'));
    $this->assertSame('http://github.com', $obj->withoutBaseUrl('http://github.com'));
  }

  public function testProtocolMismatchThrows() {
    $this->expectException(\InvalidArgumentException::class);
    $obj = new BaseUrlTraitTestable();
    $obj->setBaseUrl('https://www.foo.com/');
    $obj->withoutBaseUrl('http://www.foo.com/foo.php');
  }

  public function testCreateAbsoluteUrl() {
    $obj = new BaseUrlTraitTestable();
    $obj->setBaseUrl('https://www.foo.com/');
    $this->assertSame('https://www.foo.com/foo.php', $obj->withBaseUrl('/foo.php'));
    $this->assertSame('https://www.foo.com/foo.php', $obj->withBaseUrl('https://www.foo.com/foo.php'));
  }

  public function testWithEmptyBaseUrl() {
    $obj = new BaseUrlTraitTestable();
    $this->assertSame('/foo.php', $obj->withoutBaseUrl('/foo.php'));
    $this->assertSame('/foo.php', $obj->withBaseUrl('/foo.php'));
  }

  public function testCreateLocalUrl() {
    $obj = new BaseUrlTraitTestable();
    $obj->setBaseUrl('https://www.foo.com/');
    $this->assertSame('/foo.php', $obj->withoutBaseUrl('/foo.php'));
    $this->assertSame('/foo.php', $obj->withoutBaseUrl('https://www.foo.com/foo.php'));
  }

  public function testGetBaseUrlStripsTrailingSlash() {
    $obj = new BaseUrlTraitTestable();
    $obj->setBaseUrl('https://www.foo.com/');
    $this->assertSame('https://www.foo.com', $obj->getBaseUrl());
  }

}

class BaseUrlTraitTestable {

  use \AKlump\CheckPages\Traits\BaseUrlTrait;
}