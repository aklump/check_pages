<?php

namespace AKlump\CheckPages\Mixins\Drupal;

use AKlump\CheckPages\DataStructure\UserInterface;
use AKlump\CheckPages\Files\FilesProviderInterface;
use AKlump\CheckPages\HttpClient;

final class AuthenticateDrupal7 extends AuthenticateDrupal {

  /**
   * AuthenticateDrupal constructor.
   *
   * @param \AKlump\CheckPages\HttpClient $http_client
   * @param \AKlump\CheckPages\Files\FilesProviderInterface $log_files
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
    HttpClient $http_client,
    FilesProviderInterface $log_files,
    string $absolute_login_url,
    string $form_selector = 'form[action="/user/login"]',
    string $form_id = 'user_login'
  ) {
    parent::__construct($http_client, $log_files, $absolute_login_url, $form_selector, $form_id);
  }

  public function login(UserInterface $user) {
    parent::login($user);
    $this->populateUserId($user);
    $this->populateUserEmail($user);
  }

  public function getCsrfToken(): string {
    return '';
  }

  private function populateUserId(UserInterface $user) {
    if (!$user->id()) {
      $body = strval($this->getResponse()->getBody());
      if (preg_match('/"uid"\:"(\d+?)"/', $body, $matches)) {
        $user->setId(intval($matches[1]));
      }
    }
  }

  private function populateUserEmail(UserInterface $user) {
    if (!$user->getEmail()) {
      $this->requestUserEmail($user);
    }
  }

}
