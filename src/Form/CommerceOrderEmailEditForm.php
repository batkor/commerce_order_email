<?php

namespace Drupal\commerce_order_email\Form;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CommerceOrderEmailEditForm.
 */
class CommerceOrderEmailEditForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_order_email.configform'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_order_email_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, OrderType $commerce_order_type = NULL) {
    $config = $this->config('commerce_order_email.configform');

    $form_state->set('commerce_order_type', $commerce_order_type);

    if ($commerce_order_type->shouldSendReceipt()) {
      $email = $commerce_order_type->getThirdPartySetting('commerce_order_email', 'email');
      $form['main'] = [
        '#type' => 'container',
        '#prefix' => '<div class="clearfix">',
        '#suffix' => '</div>',
      ];
      $form['main']['left'] = [
        '#type' => 'container',
        '#prefix' => '<div class="layout-column layout-column--half">',
        '#suffix' => '</div>',
      ];
      $form['main']['left']['editor_details'] = [
        '#type' => 'container',
        '#prefix' => '<div class="panel"><h3 class="panel__title">' . $this->t('Email editor') . '</h3>',
        '#suffix' => '</div>',
      ];
      $form['main']['left']['editor_details']['email'] = [
        '#type' => 'textarea',
        '#required' => TRUE,
        '#default_value' => $email ?: NULL,
        '#description' => $config->get('description'),
        '#rows' => 30,
      ];

      $moduleHandler = \Drupal::service('module_handler');
      if ($moduleHandler->moduleExists('codemirror_editor')) {
        $form['main']['left']['editor_details']['email']['#codemirror']['mode'] = 'html_twig';
      }

      $form['main']['right'] = [
        '#type' => 'container',
        '#prefix' => '<div class="layout-column layout-column--half">',
        '#suffix' => '</div>',
      ];

      $form['main']['right']['preview_details'] = [
        '#type' => 'container',
        '#prefix' => '<div class="panel"><h3 class="panel__title">' . $this->t('Preview') . '</h3>',
        '#suffix' => '</div>',
      ];

      $form['main']['right']['preview_details']['preview_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Preview'),
        '#ajax' => [
          'callback' => [$this, 'previewEmailCallback'],
        ],
        '#attached' => ['library' => ['commerce_order_email/editor_variation']],
      ];

      $form['main']['right']['preview_details']['preview_wrapper'] = [
        '#prefix' => '<div id="preview_wrapper">',
        '#suffix' => '</div>',
      ];

    }
    else {
      $this->messenger()
        ->addWarning($this->t('Please enable "Email the customer a receipt" option.'));
    }


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $output = $this->preRenderEmail($form_state->getValue('email'));

    if (!$output['state']) {
      $this->messenger()
        ->addWarning($output['context']);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var OrderType $commerce_order_type */
    if ($commerce_order_type = $form_state->get('commerce_order_type')) {
      $commerce_order_type->setThirdPartySetting('commerce_order_email', 'email', $form_state->getValue('email'));
      $commerce_order_type->save();
    }
  }

  public function previewEmailCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $output = $this->preRenderEmail($form_state->getValue('email'));

    $response->addCommand(new HtmlCommand('#preview_wrapper', $output['context']));
    return $response;
  }

  protected function preRenderEmail($data) {
    $output = [
      'state' => FALSE,
      'context' => $this->t('Warning. Template don\'t validate'),
    ];

    $query = \Drupal::entityQuery('commerce_order')
      ->range(0, 1);
    $oids = $query->execute();
    if (!empty($oids)) {
      $order = Order::load(reset($oids));
      /** @var \Drupal\commerce_order\OrderTotalSummaryInterface $order_total_summary */
      $order_total_summary = \Drupal::service('commerce_order.order_total_summary');
      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = \Drupal::service('entity_type.manager');
      $shippings = $order->get('shipments')->referencedEntities();
      $shipping_config = [];
      if (!empty($shippings)) {
        /** @var \Drupal\commerce_shipping\Entity\Shipment $shipping */
        foreach ($shippings as $shipping) {
          $shipping_metod = $shipping->getShippingMethod();
          $shipping_config = $shipping_metod->getPlugin()->getConfiguration();
        }
      }
      $payment_gateways = $order->get('payment_gateway')->referencedEntities();
      $payment_gateway_config = [];
      if (!empty($payment_gateways)) {
        /** @var \Drupal\commerce_payment\Entity\PaymentGateway $payment_gateway */
        foreach ($payment_gateways as $payment_gateway) {
          $payment_gateway_config = $payment_gateway->getPlugin()
            ->getConfiguration();
        }
      }

      $build = [
        '#type' => 'inline_template',
        '#template' => $data,
        '#context' => [
          'order_entity' => $order,
          'billing_information' => $entity_type_manager->getViewBuilder('profile')
            ->view($order->getBillingProfile()),
          'totals' => $order_total_summary->buildTotals($order),
          'shipping_information' => $shipping_config,
          'payment_method' => $payment_gateway_config,
        ],
      ];

      try {
        $output = [
          'state' => TRUE,
          'context' => \Drupal::service('renderer')->renderPlain($build)
        ];
      } catch (\Exception $exception) {
        $output['context'] = $exception->getMessage();
      }
    }

    return $output;
  }

}
