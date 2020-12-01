<?php


namespace AKlump\CheckPages;


interface RequestDriverInterface {

  /**
   * Set the url.
   *
   * @param string $url
   *
   * @return $this
   */
  public function setUrl(string $url): self;

  /**
   * Get the request response.
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function getResponse(): \Psr\Http\Message\ResponseInterface;

  /**
   * Return the final location of the page (after redirects, if any).
   *
   * @return string
   */
  public function getLocation(): string;

  /**
   * Return the initial redirect status code.
   *
   * @return int
   */
  public function getRedirectCode(): int;

}