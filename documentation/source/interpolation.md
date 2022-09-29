# Interpolation

## Rules for Test Interpolation

* The timing of interpolation can be tricky.
* Since only plugins know what test keys they provide, they MUST handle interpolation on their own test keys.
* You should set variables on the test, which will persist only for that test. `\AKlump\CheckPages\Parts\Test::variables()`
* You should set variables on suite, which will persist across tests. `\AKlump\CheckPages\Parts\Suite::variables`
* You may also want to use a throw-away instance such as is done in `\AKlump\CheckPages\Plugin\LoopCurrentLoop::execute`
* When a plugin interpolates test config, it should probably set the config so that the interpolated values are passed down the execution chain, e.g. taken from the Request plugin:
  ```php
  function (TestEventInterface $event) {
    $test = $event->getTest();
    $this->config = $test->getConfig();
    $test->interpolate($this->config['request']);
    $test->setConfig($this->config);
  }  
  ```

### Example Implementations

* `\AKlump\CheckPages\Plugin\Value`
* `\AKlump\CheckPages\Plugin\Request`