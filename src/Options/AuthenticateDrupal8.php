<?php

namespace AKlump\CheckPages\Options;

final class AuthenticateDrupal8 extends AuthenticateDrupalBase {

  /**
   * AuthenticateDrupal8 constructor.
   *
   * @param string $path_to_users_login_data
   *   The resolved path to the JSON or YAML file for the users.
   * @param string $absolute_login_url
   *   The absolute URL to the login form.
   * @param string $form_selector
   *   A CSS selector to find the login form on in the DOM of the
   *   $absolute_login_url.
   * @param string $form_id
   *   The value of the hidden input name=form_id.
   */
  public function __construct(
    string $path_to_users_login_data,
    string $absolute_login_url,
    string $form_selector = 'form.user-login-form',
    string $form_id = 'user_login_form'
  ) {
    parent::__construct($path_to_users_login_data, $absolute_login_url, $form_selector, $form_id);
  }

}
