<?php

namespace AKlump\Messaging;

/**
 * A class that represents a normal (not verbose) message.
 */
abstract class MessageBase implements MessageInterface {

  /** @var string */
  private $messageType;

  /** @var array */
  private $message;

  public function __construct(array $message, string $message_type = NULL) {
    $message_type = $message_type ?? MessageType::INFO;
    if (!in_array($message_type, [
      MessageType::ERROR,
      MessageType::SUCCESS,
      MessageType::INFO,
      MessageType::DEBUG,
    ])) {
      throw new \InvalidArgumentException(sprintf('Invalid message type: %s', $message_type));
    }
    $this->messageType = $message_type;
    $this->message = $message;
  }

  /**
   * @return string
   *
   * @see \AKlump\Messaging\MessageType
   */
  public function getMessageType(): string {
    return $this->messageType;
  }

  /**
   * @return array
   */
  public function getMessage(): array {
    return $this->message;
  }

  public function __toString(): string {
    return implode(PHP_EOL, $this->message);
  }

}
