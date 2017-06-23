<?php

namespace Drupal\apidae_drupal_module\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ApidaeSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['system.apidae'];
  }

  public function getFormId() {
    return 'system_apidae_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $apidaeConfig = \Drupal::config('system.apidae');
    // apidae_api and selections were identifiants / apidae_cron was maj
    $form['apidae_api'] = array(
      '#type' => 'fieldset',
      '#title' => t('Informations de connexion'),
      '#description' => t('Ces informations sont disponibles dans votre fiche projet sur https://base.apidae-tourisme.com')
    );
    $form['apidae_api']['url'] = array(
      '#type' => 'textfield',
      '#maxlength' => 200,
      '#title' => t('Adresse web (URL)'),
      '#required' => TRUE,
      '#default_value' => $apidaeConfig->get('api.url')
    );
    $form['apidae_api']['key'] = array(
      '#type' => 'textfield',
      '#title' => t('Clé d\'API'),
      '#required' => TRUE,
      '#default_value' => $apidaeConfig->get('api.key')
    );
    $form['apidae_api']['project'] = array(
      '#type' => 'textfield',
      '#title' => t('Identifiant'),
      '#required' => TRUE,
      '#default_value' => $apidaeConfig->get('api.project')
    );
    $form['apidae_cron'] = array(
      '#type' => 'fieldset',
      '#title' => t('Mise à jour automatique des données'),
      '#description' => t('Activez ou non l\'actualisation automatique des données via le Cron de drupal')
    );
    $form['apidae_cron']['active'] = array(
      '#type' => 'checkbox',
      '#title' => t('Activer la mise à jour automatique des données'),
      '#default_value' => $apidaeConfig->get('cron.active')
    );
    $options = array(
      'new_content_and_updates' => t('Activer la récupération de nouveaux contenus ainsi que la mise à jour des données déjà récupérées <strong>(Ceci écrasera les éventuels changements que vous avez apporté au contenu.)</strong>'),
      'new_content_only' => t('Activer <strong>Uniquement</strong> la récupération de nouveaux contenus.')
    );
    $form['apidae_cron']['type'] = array(
      '#type' => 'radios',
      '#title' => t('Quel type de mise à jour souhaitez-vous ?'),
      '#options' => $options,
      '#description' => t('Valable uniquement si les mises à jour sont activées.'),
      '#default_value' => $apidaeConfig->get('cron.type')
    );
    $form['apidae_cron']['frequency'] = array(
      '#type' => 'textfield',
      '#title' => t('Fréquence (en jours) des mises à jour'),
      '#description' => t('Requis si les mises à jours automatiques sont activées. Valeur en nombre de jours. Pour une MAJ toutes les semaines, entrez 7'),
      '#default_value' => $apidaeConfig->get('cron.frequency')
    );
    $form['apidae_data'] = array(
      '#type' => 'fieldset',
      '#title' => t('Données Apidae à importer'),
      '#description' => t('Sélectionnez les critères des objets touristiques à récupérer')
    );
    $form['apidae_data']['selections'] = array(
      '#type' => 'textfield',
      '#maxlength' => 200,
      '#title' => t('Sélections Apidae pré-calculées'),
      '#description' => t('Si vous avez plusieurs sélections, séparez-les par une virgule'),
      '#required' => FALSE,
      '#default_value' => $apidaeConfig->get('data.selections')
    );
    $form['apidae_data']['types'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Sélection des types d\'objets touristiques : '),
      '#options' => array('ACTIVITE' => t('Activité'), 'COMMERCE_ET_SERVICE' => t('Commerce et service'), 'DEGUSTATION' => t('Producteur'),
        'DOMAINE_SKIABLE' => t('Domaine skiable'), 'EQUIPEMENT' => t('Equipement'), 'FETE_ET_MANIFESTATION' => t('Fête et manifestation'),
        'HEBERGEMENT_COLLECTIF' => t('Hébergement collectif'), 'HEBERGEMENT_LOCATIF' => t('Hébergement locatif'), 'HOTELLERIE' => t('Hôtellerie'),
        'HOTELLERIE_PLEIN_AIR' => t('Hôtellerie de plein air'), 'PATRIMOINE_CULTUREL' => t('Patrimoine culturel'),
        'PATRIMOINE_NATUREL' => t('Patrimoine naturel'), 'RESTAURATION' => t('Restauration'), 'SEJOUR_PACKAGE' => t('Séjour packagé'),
        'TERRITOIRE' => t('Territoire')),
      '#default_value' => array('ACTIVITE', 'COMMERCE_ET_SERVICE', 'DEGUSTATION', 'DOMAINE_SKIABLE',
        'EQUIPEMENT', 'FETE_ET_MANIFESTATION', 'HEBERGEMENT_COLLECTIF', 'HEBERGEMENT_LOCATIF', 'HOTELLERIE', 'HOTELLERIE_PLEIN_AIR', 'PATRIMOINE_CULTUREL',
        'PATRIMOINE_NATUREL', 'RESTAURATION', 'SEJOUR_PACKAGE', 'TERRITOIRE')
    );

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('system.apidae')
      ->set('api.url', $form_state->getValue('url'))
      ->set('api.key', $form_state->getValue('key'))
      ->set('api.project', $form_state->getValue('project'))
      ->set('cron.active', $form_state->getValue('active'))
      ->set('cron.type', $form_state->getValue('type'))
      ->set('cron.frequency', $form_state->getValue('frequency'))
      ->set('data.selections', $form_state->getValue('selections'))
      ->set('data.types', $form_state->getValue('types'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}