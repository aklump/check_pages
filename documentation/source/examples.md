# Examples

## Assert a DOM element is visible

This example checks the CSS for both display and opacity properties to determine
if the modal is visible based on CSS.

```yaml
find:
  -
    dom: .modal
    count: 1
  -
    style: .modal
    property: display
    matches: /^(?!none).+$/
  -
    style: .modal
    property: opacity
    is: 1
```

## Assert the URL Hash Matches RegEx Pattern

```yaml
find:
    -
      javascript: location.hash
      matches: /^#foo=bar&alpha=bravo$/
```
