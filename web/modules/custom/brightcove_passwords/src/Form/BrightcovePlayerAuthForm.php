<?php

namespace Drupal\brightcove_passwords\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Password\PasswordInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides login screen for password-protected Brightcove Videos.
 */
class BrightcovePlayerAuthForm extends FormBase {

  /**
   * Provides the password hashing service object.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * @inheritDoc
   */
  public function __construct(PasswordInterface $password) {
    $this->password = $password;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('password')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'brightcove_passwords_auth';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['password'] = [
      '#type' => 'password',
      '#title' => 'Password',
      '#attributes' => [
        'placeholder' => 'Password',
      ],
      '#title_display' => 'invisible',
      '#size' => 20,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getValue('password') != $form_state->get('password')) {
      $form_state->setErrorByName('password', 'Sorry, the password you\'ve entered is incorrect.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getValue('password') == $form_state->get('password')) {
      $session = \Drupal::request()->getSession();
      $session_auth = $session->set('brightcove.passwords.' . $form_state->get('videoid'), REQUEST_TIME);
    }
  }

}
