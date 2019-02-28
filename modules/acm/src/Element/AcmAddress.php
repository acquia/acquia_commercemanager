<?php

namespace Drupal\acm\Element;

use Drupal\acm\Connector\RouteException;
use Drupal\address\LabelHelper;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an ACM Address form element.
 *
 * ACM Address form elements contain a group of sub-elements for each address
 * components.
 *
 * @FormElement("acm_address")
 */
class AcmAddress extends FormElement {

  /**
   * The default address values.
   *
   * @var array
   */
  private static $addressDefaults = [
    'telephone' => '',
    'address_id' => 0,
    'title' => '',
    'firstname' => '',
    'lastname' => '',
    'street' => '',
    'street2' => '',
    'city' => '',
    'region' => '',
    'postcode' => '',
    'country_id' => 'US',
    'default_billing' => 0,
    'default_shipping' => 0,
  ];

  /**
   * Calculate dynamic address parts per country.
   *
   * @param string $country
   *   Country code.
   *
   * @return array
   *   Calculated dynamic form parts.
   */
  public static function calculateDynamicParts($country) {
    $dynamic_parts = [];
    $addressFormatRepository = \Drupal::service('address.address_format_repository');
    $address_format = $addressFormatRepository->get($country);
    $subdivisionRepository = \Drupal::service('address.subdivision_repository');
    $options = $subdivisionRepository->getList([$country]);
    $labels = LabelHelper::getFieldLabels($address_format);

    // Update region options based on country.
    $dynamic_parts['region']['#options'] = $options;
    $dynamic_parts['region']['#required'] = TRUE;
    $dynamic_parts['region']['#access'] = TRUE;

    // Update labels.
    $cityLabel = $labels['locality'];
    $postcodeLabel = $labels['postalCode'];
    $regionLabel = $labels['administrativeArea'];
    $dynamic_parts['region']['#title'] = $regionLabel;
    $dynamic_parts['postcode']['#title'] = $postcodeLabel;
    $dynamic_parts['city']['#title'] = $cityLabel;

    if (empty($cityLabel)) {
      $dynamic_parts['city']['#required'] = FALSE;
      $dynamic_parts['city']['#access'] = FALSE;
    }

    if (empty($postcodeLabel)) {
      $dynamic_parts['postcode']['#required'] = FALSE;
      $dynamic_parts['postcode']['#access'] = FALSE;
    }

    if (empty($regionLabel) || empty($options)) {
      $dynamic_parts['region']['#required'] = FALSE;
      $dynamic_parts['region']['#access'] = FALSE;
    }

    return $dynamic_parts;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#markup' => '',
      '#process' => [
        [$class, 'processAcmAddress'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#display_telephone' => FALSE,
      '#display_billing' => FALSE,
      '#display_shipping' => FALSE,
      '#display_title' => FALSE,
      '#display_firstname' => FALSE,
      '#display_lastname' => FALSE,
      '#include_address_id' => FALSE,
      '#validate_address' => FALSE,
      '#address_review_text' => '',
      '#address_failed_text' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE || empty($input)) {
      $element += ['#default_value' => []];
      return $element['#default_value'];
    }

    $address_input = [];

    // Flatted the input.
    array_walk_recursive($input, function ($value, $key) use (&$address_input) {
      $address_input[$key] = $value;
    });

    // Throw out all invalid array keys.
    $value = [];
    foreach (static::$addressDefaults as $allowed_key => $default) {
      // These should be strings, but allow other scalars since they might be
      // valid input in programmatic form submissions. Any nested array values
      // are ignored.
      if (isset($address_input[$allowed_key]) && is_scalar($address_input[$allowed_key])) {
        $value[$allowed_key] = (string) $address_input[$allowed_key];
      }
    }

    return $value;
  }

  /**
   * Expand a acm_address field into multiple inputs.
   */
  public static function processAcmAddress(&$element, FormStateInterface $form_state, &$complete_form) {
    $default_address = (array) $element['#default_value'];
    $address = $default_address + static::$addressDefaults;
    $country = !empty($address['country_id']) ? $address['country_id'] : 'US';
    $countryRepository = \Drupal::service('address.country_repository');
    $subdivisionRepository = \Drupal::service('address.subdivision_repository');
    $addressFormatRepository = \Drupal::service('address.address_format_repository');
    $address_format = $addressFormatRepository->get($country);
    $labels = LabelHelper::getFieldLabels($address_format);

    if (!empty($element['#include_address_id'])) {
      $element['address_id'] = [
        '#type' => 'hidden',
        '#default_value' => $address['address_id'],
      ];
    }

    if (!empty($element['#display_title'])) {
      $element['title'] = [
        '#type' => 'acm_title_select',
        '#title' => t('Title'),
        '#default_value' => empty($address['title']) ? NULL : $address['title'],
        '#required' => TRUE,
        '#placeholder' => t('Title*'),
      ];
    }

    if (!empty($element['#display_firstname'])) {
      $element['firstname'] = [
        '#type' => 'textfield',
        '#title' => t('First Name'),
        '#default_value' => $address['firstname'],
        '#required' => TRUE,
        '#placeholder' => t('First Name*'),
      ];
    }

    if (!empty($element['#display_lastname'])) {
      $element['lastname'] = [
        '#type' => 'textfield',
        '#title' => t('Last Name'),
        '#default_value' => $address['lastname'],
        '#required' => TRUE,
        '#placeholder' => t('Last Name*'),
      ];
    }

    if (!empty($element['#display_telephone'])) {
      $element['telephone'] = [
        '#type' => 'textfield',
        '#title' => t('Telephone'),
        '#default_value' => $address['telephone'],
        '#required' => TRUE,
        '#placeholder' => t('Telephone*'),
      ];
    }

    $element['street'] = [
      '#type' => 'textfield',
      '#title' => t('Address Line 1'),
      '#default_value' => $address['street'],
      '#required' => TRUE,
      '#placeholder' => t('Address Line 1*'),
    ];

    $element['street2'] = [
      '#type' => 'textfield',
      '#title' => t('Address Line 2'),
      '#default_value' => $address['street2'],
      '#required' => FALSE,
      '#placeholder' => t('Address Line 2'),
    ];

    $element['dynamic_parts'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['dynamic_parts'],
      ],
      '#parents' => $element['#parents'],
    ];

    $element['dynamic_parts']['city'] = [
      '#type' => 'textfield',
      '#title' => $labels['locality'],
      '#default_value' => $address['city'],
      '#required' => TRUE,
      '#placeholder' => $labels['locality'],
    ];

    $dynamic_parts = self::calculateDynamicParts($country);
    $regions = $subdivisionRepository->getList([$country]);

    $possiblyMatchingRegion = "";
    // If address has region and this country has regions to choose from
    // then try to fix the region mess, otherwise set region to "".
    if ($address['region'] && $dynamic_parts['region']['#access']) {
      // Some e-commerce back-ends send back region as an abbreviation and some
      // don't. If we don't have an abbreviation we'll need to flip the regions
      // array in order to find the default value to use.
      $possiblyMatchingRegion = AcmAddress::fixRegionMess($address['region'], $regions);
    }
    $default_region = $possiblyMatchingRegion;

    $element['dynamic_parts']['region'] = [
      '#type' => 'select',
      '#title' => $labels['administrativeArea'],
      '#options' => $regions,
      '#default_value' => $default_region,
      '#empty_option' => '- ' . $labels['administrativeArea'] . ' -',
      '#required' => TRUE,
      '#validated' => TRUE,
    ];

    $element['dynamic_parts']['postcode'] = [
      '#type' => 'textfield',
      '#title' => $labels['postalCode'],
      '#default_value' => $address['postcode'],
      '#required' => TRUE,
      '#placeholder' => $labels['postalCode'],
    ];

    $element['dynamic_parts']['country_id'] = [
      '#type' => 'select',
      '#title' => t('Country'),
      '#options' => $countryRepository->getList(),
      '#default_value' => $country,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_called_class(), 'addressAjaxCallback'],
        'wrapper' => 'dynamic_parts',
        'options' => [
          'query' => [
            'element_parents' => implode('/', $element['#array_parents']),
          ],
        ],
      ],
    ];

    // We created the dynamic parts as we may want them,
    // But now we calculate what is hidden or showing or required
    // and what labels are translated based on the country (not the locale...)
    $element['dynamic_parts'] = array_replace_recursive($element['dynamic_parts'], $dynamic_parts);

    if (!empty($element['#display_billing'])) {
      $element['default_billing'] = [
        '#type' => 'checkbox',
        '#title' => t('Default billing address'),
        '#default_value' => $address['default_billing'],
      ];
    }

    if (!empty($element['#display_shipping'])) {
      $element['default_shipping'] = [
        '#type' => 'checkbox',
        '#title' => t('Default shipping address'),
        '#default_value' => $address['default_shipping'],
      ];
    }

    // TODO (Malachy): Consider adding 'save address to address book' here.
    if (!empty($element['#validate_address'])) {
      $element['#element_validate'] = [[get_called_class(), 'validateAddress']];
      if (!isset($element['#address_review_text'])) {
        $element['#address_review_text'] = t('Address validation suggested a different address.');
      }
      if (!isset($element['#address_failed_text'])) {
        $element['#address_failed_text'] = t('Address validation failed.');
      }
    }

    $element['#tree'] = TRUE;

    return $element;
  }

  /**
   * Validates an acm_address element.
   */
  public static function validateAddress(&$element, FormStateInterface $form_state, &$complete_form) {
    $address = $element['#value'];
    $address_review_text = $element['#address_review_text'];
    $address_failed_text = $element['#address_failed_text'];

    // Make sure these fields have values before trying to validate the address.
    $required_fields = [
      'street',
      'city',
      'region',
      'postcode',
    ];

    $skip = FALSE;

    foreach ($required_fields as $required_field) {
      if (empty($address[$required_field])) {
        $skip = TRUE;
        break;
      }
    }

    // Skip validation if not all fields are filled out yet.
    if ($skip) {
      return $element;
    }

    try {
      $response = \Drupal::service('acm.api')
        ->validateCustomerAddress($address);

      // Address is valid and no suggestion came back.
      if (isset($response['result']['valid']) && empty($response['result']['suggested'])) {
        drupal_set_message($address_review_text, 'status');
      }
      // Address is in review and there's a suggestion that we use to pre-fill
      // the address fields.
      elseif (isset($response['result']['suggested']) && !empty($response['result']['suggested'])) {
        $suggested_address = reset($response['result']['suggested']);
        foreach ($suggested_address as $field => $value) {
          if (!empty($value)) {
            if (isset($element[$field])) {
              $form_state->setValueForElement($element[$field], $value);
            }
            elseif (isset($element['dynamic_parts'][$field])) {
              $form_state->setValueForElement($element['dynamic_parts'][$field], $value);
            }
          }
        }

        drupal_set_message($address_review_text, 'status');
      }
      // Address failed validation.
      else {
        $form_state->setError($element, $address_failed_text);
      }
    }
    catch (RouteException $e) {
      $form_state->setError($element, $address_failed_text);
    }

    return $element;
  }

  /**
   * Ajax handler for country selector.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response of the ajax upload.
   */
  public static function addressAjaxCallback(array $form, FormStateInterface $form_state, Request $request) {
    $form_parents = explode('/', $request->query->get('element_parents'));

    // Retrieve the element to be rendered.
    $form = NestedArray::getValue($form, $form_parents);

    $values = $form_state->getValue($form['#parents']);
    $country = $values['country_id'];

    $dynamic_parts = self::calculateDynamicParts($country);
    return array_replace_recursive($form['dynamic_parts'], $dynamic_parts);
  }

  /**
   * Function fixRegionMess().
   *
   * Some ecommerce back-ends send back region as an abbreviation and some
   * don't. If we don't have an abbreviation we'll need to flip the regions
   * array in order to find the default value to use.
   *
   * @param string $region
   *   The region to fix.
   * @param array $regions
   *   The regions to fix against (array of strings expected).
   *
   * @return null|string
   *   The fixed region string.
   */
  public static function fixRegionMess(string $region, array $regions) {

    if ($region) {
      if (!preg_match('/\b([A-Z]{2})\b/', $region)) {
        $flipped_regions = array_flip($regions);
        $region = isset($flipped_regions[$region]) ? $flipped_regions[$region] : "";
      }
    }
    return $region;
  }

}
