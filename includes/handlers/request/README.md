# Making Requests Other Than GET

## Automatic Body Encoding

When the body is not a scalar or null, it will be encoded based on the content type. The assumed content type is `application/octet-stream`

## Multiple Verbs

Use `methods` to request against the same endpoint using the same configuration, varying on method. This can be handly for testing REST APIs against 403 responses.

Notice how you can use `${request.method}` to interpolate.

## Custom Request Timeout This Test Only

You can set a custom timeout for this test only, which overrides `request_timeout` from the runner configuration.

```yml
-
  why: Demonstrate custom request timeout for this test only.
  visit: /test_subject.php
  request:
    timeout: 33
```
