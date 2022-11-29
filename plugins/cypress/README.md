# Cypress

Provides a wrapper around Cypress to incorporate Cypress testing from within Check Pages. Allows passing of Check Pages variables to Cypress for passing of context.

## Configuration

```yaml
extras:
  cypress:
    cypress: /Users/aklump/Code/Projects/ContechServices/AuroraTimesheet/site/app/node_modules/.bin/cypress
    config_file: /Users/aklump/Code/Projects/ContechServices/AuroraTimesheet/site/app/cypress/config/dev.config.js
    spec_base: /Users/aklump/Code/Projects/ContechServices/AuroraTimesheet/site/app/cypress/e2e/

```

## _suite.yml_ Example

```yaml
-
  set: timesheet.id
  value: 240
-
  set: timesheet.worker.id
  value: 160
-
  why: Demonstrate the Cypress Plugin syntax.
  cypress: worker/3632.cy.js
  env:
    user: site_test.worker1
    visit: node/${timesheet.id}/approve/${timesheet.worker.id}

```

## Todo

- debug output
- update title correctly
- implement https://docs.cypress.io/guides/guides/command-line#Debugging-commands