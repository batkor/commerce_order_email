<?php

namespace Drupal\commerce_order_email;

use Drupal\commerce_order_email\EventSubscriber\CommerceOrderEmailReceiptSubscriber;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class CommerceOrderEmailServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    $container
      ->getDefinition('commerce_order.order_receipt_subscriber')
      ->setClass(CommerceOrderEmailReceiptSubscriber::class)
      ->setArguments([
          new Reference('entity_type.manager'),
          new Reference('language_manager'),
          new Reference('plugin.manager.mail'),
          new Reference('commerce_order.order_total_summary'),
          new Reference('renderer'),
        ]
      );
  }
}