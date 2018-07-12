<?php

namespace Drupal\commerce_order_email\EventSubscriber;

use Drupal\commerce_order\EventSubscriber\OrderReceiptSubscriber;
use Drupal\Core\Render\RenderContext;
use Drupal\state_machine\Event\WorkflowTransitionEvent;

/**
 * Sends a receipt email when an order is placed.
 */
class CommerceOrderEmailReceiptSubscriber extends OrderReceiptSubscriber {

  /**
   * Sends an order receipt email.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event we subscribed to.
   */
  public function sendOrderReceipt(WorkflowTransitionEvent $event){
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $this->orderTypeStorage->load($order->bundle());
    if (!$order_type->shouldSendReceipt()) {
      return;
    }

    $commerce_order_email = $order_type->getThirdPartySetting('commerce_order_email', 'email');

    if(is_null($commerce_order_email)){
      return;
    }

    $to = $order->getEmail();
    if (!$to) {
      // The email should not be empty, unless the order is malformed.
      return;
    }

    $params = [
      'headers' => [
        'Content-Type' => 'text/html; charset=UTF-8;',
        'Content-Transfer-Encoding' => '8Bit',
      ],
      'from' => $order->getStore()->getEmail(),
      'subject' => $this->t('Order #@number confirmed', ['@number' => $order->getOrderNumber()]),
      'order' => $order,
    ];
    if ($receipt_bcc = $order_type->getReceiptBcc()) {
      $params['headers']['Bcc'] = $receipt_bcc;
    }

    // Replicated logic from EmailAction and contact's MailHandler.
    if ($customer = $order->getCustomer()) {
      $langcode = $customer->getPreferredLangcode();
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }

    $token = \Drupal::token();
    $token_alias = $commerce_order_email['value'];
    $data = [
      'commerce_order' => $order,
      'commerce_order_items' => $order->getItems(),
    ];

    $email_body = $token->replace($token_alias, $data, ['clear' => TRUE]);

    $build = [
      '#type' => 'processed_text',
      '#text' => $email_body,
      '#format' => $commerce_order_email['format'],
      '#langcode' => $langcode,
    ];

    $params['body'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($build) {
      return $this->renderer->render($build);
    });

    $this->mailManager->mail('commerce_order', 'receipt', $to, $langcode, $params);
  }
}