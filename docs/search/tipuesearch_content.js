var tipuesearch = {"pages":[{"title":"Changelog","text":"  All notable changes to this project will be documented in this file.  The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.  [Unreleased]   Add login and logout in includes\/drupal to be able to run suites as authenticated users.   [0.5.1] - 2021-01-14  Added   The alias visit: may be used instead of url: Examples now show using visit:, though url: still works.   [0.5] - 2021-12-30  Added   --filter parameter to limit runner to a single suite from the CLI.   [0.4] - 2020-12-01  Added   Javascript support with Chrome.   Changed   The way the CSS selector works has changed fundamentally and may break your tests. Refer to the following test YAML. Prior to 0.4 that would only choose the first .card__title on the page and assert it's text matched the expected. Starting in 0.4, all .card__titles found on the page will be matched and the assert will pass if any of them have matching text.  -   visit: \/foo\/bar   find:     -       dom: .card__title       text: The Top of the Mountain  If you need the earlier functionality you should use the xpath selector as shown here to indicate just the first element with that class.  -  visit: \/foo\/bar  find:    -      xpath: '(\/\/*[contains(@class, \"card__title\")])[1]'      text: The Top of the Mountain    [0.3] - 2020-08-15  Added   Added the --quiet flag   Changed   The default output is now how it was when adding the --debug flag, use the --quiet flag for less verbosity. Visual layout to make reading results easier and more clear.   Removed   The --debug flag  ","tags":"","url":"CHANGELOG.html"},{"title":"Check Pages","text":"  Very Simple QA for Websites    Summary  This project intends to provide a process of QA testing of a website, which is very fast to implement and simple to maintain.  You write your tests using YAML and they can look as simple as this:  # Check the homepage to make sure it returns 200. - visit: \/  # Make sure the `\/admin` path returns 403 forbidden when not logged in. - visit: \/admin   expect: 403   In a third test we can assert there is one logo image on the homepage, like so:  - visit: \/   find:     - dom: '#logo img'       count: 1   For more code examples explore the \/examples directory.  Visit https:\/\/aklump.github.io\/check_pages for full documentation.  Terms Used   Test Runner - A very simple PHP file that defines the configuration and what test suites to run, and in what order.  @see includes\/runner.php. Test Suite - A YAML file that includes one or more checks against URLs. @see includes\/suite.yml. Test - A single URL check within a suite. Assertion - A single find action against the response body of a test, or a validation that the HTTP response code matches an expected value.   Requirements   You must install with Composer. Tests suites are written in YAML. Little to no experience with PHP is necessary.  Copy and paste will suffice.   Install  The following creates a stand-alone project in a folder named check-pages.  See also Install In Another Composer Project.  $ composer create-project aklump\/check-pages   Quick Start  Run the example tests with the following commands.  Then open up the files in the example\/tests directory and study them to see how they work.1   Open a new shell window which will run the PHP server for our example test pages.  $ .\/bin\/test_server.sh  Open a second shell window to execute the tests.  $ .\/bin\/test.sh    Some failing tests are also available to explore:  $ .\/check_pages failing_tests_runner.php   1 If you see no tests directory then create one and copy the contents of examples into tests.  The example tests directory will only be created if you use create-project as the installation method.  Writing Your First Test Suite  When you are ready you should delete the contents of the tests folder and write your own tests there.  Don't worry, the original example files are located in the examples directory.  (If you have used the alternate installation method you will need to write your tests in another folder of your choosing not located in this project.  But for these examples, we'll assume a create-project installation.)  You will need a bare minimum file structure resembling:  . \u2514\u2500\u2500 tests     \u2514\u2500\u2500 config.yml     \u251c\u2500\u2500 suite.yml     \u2514\u2500\u2500 runner.php   Multiple Configuration Files  The project is designed to be able to run the same tests using different configurations.  You can create multiple configuration files so that you are able to run the same test on live and then on dev, which have different base URLs.  . \u2514\u2500\u2500 tests     \u251c\u2500\u2500 config.dev.yml     \u251c\u2500\u2500 config.live.yml     \u251c\u2500\u2500 suite.yml     \u2514\u2500\u2500 runner.php   In runner.php use the following line to specify the default config file:  load_config('config.dev');   When you're ready to run this using the live config add the config filename to the CLI command, e.g.,  $ .\/check_pages runner.php --config=config.live   Test functions  The test functions for your PHP test files are found in includes\/runner_functions.inc.  Is JS Supported?  Yes, not by default, but you are able to indicate that given tests requires Javascript be run.  Read on...  Javascript Testing Requirements   Your testing machine must have Chrome installed.   Javascript Testing Setup  To support JS testing, you must indicate where your Chrome binary is located in your runner configuration file, like so:  chrome: \/Applications\/Google Chrome.app\/Contents\/MacOS\/Google Chrome   Enable Javascript per Test  Unless you enable it, javascript is not run during testing.  If you need to assert that an element exists, which was created from Javascript (or otherwise need javascript to run on the page), you will need to indicate the following in your test, namely js: true.  - visit: \/foo   js: true   find:     - dom: .js-created-page-title       text: Javascript added me to the DOM!   Javascript Testing Related Links   Learn more https:\/\/github.com\/GoogleChrome\/chrome-launcher https:\/\/peter.sh\/experiments\/chromium-command-line-switches\/ https:\/\/raw.githubusercontent.com\/GoogleChrome\/chrome-launcher\/v0.8.0\/scripts\/download-chrome.sh   Quiet Mode  To make the output much simpler, use the --quite flag.  This will hide the assertions and reduce the output to simply pass\/fail.  .\/check_pages failing_tests_runner.php --quiet   Filter  Use the --filter parameter combined with a suite name to limit the runner to a single suite.  This is faster than editing your runner file.  .\/check_pages runner.php --filter=page_header   Troubleshooting  Try using the --show-source to see the response source code as well.  .\/check_pages failing_tests_runner.php --show-source     Install In Another Composer Project  If you want to share dependencies with another project, like Drupal 8 for example, then use the alternative installation method.  The --dev flag is shown here, but use your own discretion.  Run the following from your Drupal app root directory.  $ composer require aklump\/check-pages --dev   Usage  In this case, since the project will be buried in your vendor directory, you will need to provide the directory path to your test files, when you run the test script, like this:  .\/vendor\/bin\/check_pages runner.php --dir=.\/tests_check_pages   This example assumes a file structure like this:  . \u251c\u2500\u2500 tests_check_pages \u2502\u00a0\u00a0 \u2514\u2500\u2500 runner.php \u2514\u2500\u2500 vendor     \u2514\u2500\u2500 bin         \u2514\u2500\u2500 check_pages     Contributing  If you find this project useful... please consider making a donation. ","tags":"","url":"README.html"},{"title":"Quick Reference: Test Writing","text":"  Test Cheatsheet       operation   property   find property   default       Load page   visit|url      -     Javascript   js      false     Status Code   expect      200     Redirect   location      -     Content   find      -     Selectors      dom|xpath   -     Assertions      contains|count|exact|match|text   contains     Check Page Loads  The most simple test involves checking that a page loads.  - visit: \/foo   Check with Javascript Enabled  By default the test will not run with Javascript.  Use js: true to run the test with Javascript enabled.  Learn more.  Check Status Code  By saying that the \"page loads\", we mean that it returns a status code of 200. The following is exactly the same in function as the previous example.  You can check for any code by changing the value of expect.  - visit: \/foo   expect: 200   Check Redirect  For pages that redirect you can check for both the status code and the final location:  -   visit: \/moved.php   expect: 301   location: \/location.html   Check Content  Once loaded you can also look for things on the page with find.  The most simple find assertion looks for a substring of text anywhere on the page.  The following two examples are identical assertions.  - visit: \/foo   find:     - Upcoming Events Calendar   - visit: \/foo   find:     -       contains: Upcoming Events Calendar   Selectors  Selectors reduce the entire page content to one or more sections.   dom: Select from the DOM using CSS selectors.  find: -    dom: p.summary   find: -    dom: main   find: -    dom: .story__title  xpath: Select from the DOM using XPath selectors.  find: -    xpath: (\/\/*[contains(@class, \"block-title\")])[3]    Assertions  Assertions provide different ways to test the page or selected section(s).  In the case where there are multiple sections, such as multiple DOM elements, then the assertion is applied against all selections and only one must pass.   contains: Pass if the value is found in the selection.  find: -    dom: p.summary   contains: foo  count: Pass if equal to the number of items in the selection.  find: -    dom: p.summary   count: 2  exact: Pass if the selection's markup matches exactly.  find: -    dom: p.summary   exact: &lt;em&gt;lorem &lt;strong&gt;ipsum dolar&lt;\/strong&gt; sit amet.&lt;\/em&gt;  match: Applies a REGEX expression against the selection.  find: -    dom: p.summary   match: \/copyright\\s+20\\d{2}$\/  text: Pass if the selection's text value (all markup removed) matches exactly.  find: -    dom: p.summary   text: lorem ipsum dolar sit amet.   ","tags":"","url":"cheatsheet.html"},{"title":"Testing with Javascript","text":"  Javascript Testing Requirements   Your testing machine must have Chrome installed.   Javascript Testing Setup  To support JS testing, you must indicate where your Chrome binary is located in your runner configuration file, like so:  chrome: \/Applications\/Google Chrome.app\/Contents\/MacOS\/Google Chrome   Enable Javascript per Test  Unless you enable it, javascript is not run during testing.  If you need to assert that an element exists, which was created from Javascript (or otherwise need javascript to run on the page), you will need to indicate the following in your test, namely js: true.  - visit: \/foo   js: true   find:     - dom: .js-created-page-title       text: Javascript added me to the DOM!   Javascript Testing Related Links   Learn more https:\/\/github.com\/GoogleChrome\/chrome-launcher https:\/\/peter.sh\/experiments\/chromium-command-line-switches\/ https:\/\/raw.githubusercontent.com\/GoogleChrome\/chrome-launcher\/v0.8.0\/scripts\/download-chrome.sh  ","tags":"","url":"javascript.html"},{"title":"Search Results","text":" ","tags":"","url":"search--results.html"}]};
