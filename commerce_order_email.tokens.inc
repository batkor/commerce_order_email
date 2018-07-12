<?php

/**
 * Implements hook_token_info().
 */
function commerce_order_email_token_info() {
  $types['commerce_order_item_table'] = [
    'name' => t('Table order items'),
    'description' => t('Render table products data from order item'),
  ];

  $token['label'] = [
    'name' => t('Field name'),
    'description' => t('Replace "label" by you field name'),
  ];

  $token_shipping['shipping_information:label'] = [
    'name' => t('Show label shipping metod'),
  ];

  $token_shipping['shipping_information:price'] = [
    'name' => t('Show price sipping method'),
  ];

  $token_shipping['shipping_information:date_delivery'] = [
    'name' => t('Show date delivery shipping method'),
  ];

  return [
    'types' => $types,
    'tokens' => [
      'commerce_order_item_table' => $token,
      'commerce_order' => $token_shipping,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function commerce_order_email_tokens($type, $tokens, array $data, array $options, \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata) {
  $replacements = array();

  if ($type == 'commerce_order_item_table') {
    foreach ($tokens as $name => $original) {
      $replacements[$original] = commerce_order_email_render_order_item_table($data['commerce_order_items'], $name);
    }
  }

  if ($type == 'commerce_order') {
    foreach ($tokens as $name => $original) {
      if (strripos($name, 'shipping_information') !== FALSE) {
        $replacements[$original] = commerce_order_email_render_shipping_information($data['commerce_order'], $name);
      }
    }
  }

  return $replacements;
}

function commerce_order_email_render_order_item_table($order_items, $name) {

  $items = [];
  /** @var \Drupal\commerce_order\Entity\OrderItem $order_item */
  foreach ($order_items as $order_item) {
    $items[$order_item->id()] = [
      'qty' => $order_item->getQuantity(),
      'total_price' => $order_item->getTotalPrice(),
      'label' => $order_item->getTitle(),
    ];
    if ($name !== 'label') {
      /** @var \Drupal\commerce_product\Entity\ProductVariation $pvs */
      $pvs = $order_item->getPurchasedEntity();
      /** @var \Drupal\commerce_product\Entity\Product $ps */
      $ps = $pvs->getProduct();
      if ($ps->hasField($name)) {
        $items[$order_item->id()]['label'] = $ps->get($name)->value;
      }

    }
  }
  $renderer = \Drupal::service('renderer');
  $renderable = [
    '#theme' => 'commerce_order_items_table',
    '#order_items' => $items,
  ];

  $output = $renderer->render($renderable);

  return $output;
}

/**
 * @param \Drupal\commerce_order\Entity\Order $order
 * @return string
 */
function commerce_order_email_render_shipping_information($order, $name) {
  $output = '';
  $names = explode(':', $name);
  if ($names[0] == 'shipping_information' && isset($names[1]) && !empty($names[1])) {
    $shippings = $order->get('shipments')->referencedEntities();
    if (!empty($shippings)) {
      /** @var \Drupal\commerce_shipping\Entity\Shipment $shipping */
      foreach ($shippings as $shipping) {
        $shipping_metod = $shipping->getShippingMethod();
        $config = $shipping_metod->getPlugin()->getConfiguration();
        $output = $config[$names[1]];

        if ($names[1] == 'price') {
          $price = $config[$names[1]];
          $renderer = \Drupal::service('renderer');
          $renderable = [
            '#type' => 'inline_template',
            '#template' => '{{ price|commerce_price_format }}',
            '#context' => [
              'price' => new \Drupal\commerce_price\Price($price['number'], $price['currency_code']),
            ],
          ];

          $output = $renderer->render($renderable);
        }
      }
    }
  }

  return $output;
}