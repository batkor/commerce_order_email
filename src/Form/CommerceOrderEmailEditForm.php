<?php

namespace Drupal\commerce_order_email\Form;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderType;
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
    return [];
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

    $form_state->set('commerce_order_type', $commerce_order_type);

    if ($commerce_order_type->shouldSendReceipt()) {
      $email = $commerce_order_type->getThirdPartySetting('commerce_order_email', 'email');
      $form['email'] = [
        '#type' => 'text_format',
        '#title' => 'E-mail',
        '#required' => TRUE,
        '#default_value' => isset($email['value']) ? $email['value'] : NULL,
        '#format' => isset($email['format']) ? $email['format'] : NULL,
      ];
      $form['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['commerce_order', 'site', 'commerce_order_item_table'],
        '#show_restricted' => FALSE,
        '#global_types' => FALSE,
        '#prefix' => '<div>',
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
    //$token = \Drupal::token();
    //$token_alias = $form_state->getValue('email')['value'];
    //$order = Order::load('89');
    //$data = [
    //  'commerce_order' => $order,
    //  'commerce_order_items' => $order->getItems()
    //];
    //$new_name = $token->replace($token_alias, $data, ['clear' => TRUE]);
  }

}
