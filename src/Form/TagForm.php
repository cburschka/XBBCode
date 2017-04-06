<?php

namespace Drupal\xbbcode\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\xbbcode\TagPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for custom tags.
 *
 * @property \Drupal\xbbcode\Entity\TagInterface entity
 */
class TagForm extends EntityForm {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * @var \Drupal\xbbcode\TagPluginManager
   */
  protected $manager;

  /**
   * Constructs a new FilterFormatFormBase.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param \Drupal\xbbcode\TagPluginManager $manager
   */
  public function __construct(EntityStorageInterface $storage, TagPluginManager $manager) {
    $this->storage = $storage;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('xbbcode_tag'),
      $container->get('plugin.manager.xbbcode')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->entity->label(),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#weight' => -30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#maxlength' => 255,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$this->entity->isNew(),
      '#weight' => -20,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->getDescription(),
      '#description' => $this->t('Describe this tag. This will be shown in the filter tips and on administration pages.'),
      '#required' => TRUE,
      '#rows' => max(5, substr_count($this->entity->getDescription(), "\n")),
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default name'),
      '#default_value' => $this->entity->getName(),
      '#description' => $this->t('The default code name of this tag. It must contain only lowercase letters, numbers and underscores.'),
      '#field_prefix' => '[',
      '#field_suffix' => ']',
      '#maxlength' => 32,
      '#size' => 16,
      '#required' => TRUE,
    ];

    $form['sample'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sample code'),
      '#attributes' => ['style' => 'font-family:monospace'],
      '#default_value' => $this->entity->getSample(),
      '#description' => $this->t('Give an example of how this tag should be used. Use "<code>{{ name }}</code>" in place of the tag name.'),
      '#required' => TRUE,
      '#rows' => max(5, substr_count($this->entity->getSample(), "\n")),
    ];

    $form['editable'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];

    $form['template_code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Template code'),
      '#attributes' => ['style' => 'font-family:monospace'],
      '#default_value' => $this->entity->getTemplateCode(),
      '#description' => $this->t('The template for rendering this tag.'),
      '#required' => TRUE,
      '#rows' => max(15, substr_count($this->entity->getTemplateCode(), "\n")),
    ];

    $form['help'] = [
      '#type' => 'inline_template',
      '#title' => $this->t('Coding help'),
      '#template' => '<p>{{ header }}</p>
        <dl>
          {% for var, description in vars %}
          <dt><code>{{ "{{ " ~ var ~ " }}" }}</code></dt>
          <dd>{{ description }}</dd>
          {% endfor %}
        </dl>',
      '#context' => [
        'header' => $this->t('The above field should be filled with <a href="http://twig.sensiolabs.org/documentation">Twig</a> template code. The following variables are available for use:'),
        'vars' => [
          'tag.content' => $this->t('The text between opening and closing tags. Example: <code>[url=http://www.drupal.org]<strong>Drupal</strong>[/url]</code>'),
          'tag.option' => $this->t('The single tag attribute, if one is entered. Example: <code>[url=<strong>http://www.drupal.org</strong>]Drupal[/url]</code>.'),
          'tag.attr.*' => $this->t('A named tag attribute. Example: <code>{{ tag.attr.by }}</code> for <code>[quote by=<strong>Author</strong> date=2008]Text[/quote]</code>.'),
          'tag.source' => $this->t('The original text content of the tag, before any filters are applied. Example: <code>[code]<strong>&lt;strong&gt;[i]...[/i]&lt;/strong&gt;</strong>[/code]</code>.'),
          'tag.outerSource' => $this->t('The content of the tag, wrapped in the original opening and closing elements. Example: <code><strong>[url=http://www.drupal.org]Drupal[/url]</strong></code>.<br/>
            This can be printed to render the tag as if it had not been processed.'),
        ],
      ],
    ];

    $form['warning'] = [
      '#type' => 'item',
      '#markup' => $this->t("<strong>Warning: Do not print these variables using <code>raw</code>.</strong> The attribute and source variables bypass the text format's other filters, and contain unsafe user input."),
    ];

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $name = &$form_state->getValue('name');
    if (!preg_match('/^[a-z0-9_]+$/', $name)) {
      $form_state->setErrorByName('name', $this->t('The name [%name] must consist of lower-case letters, numbers and underscores.', ['%name' => $name]));
    }
  }

  /**
   * Determines if the tag already exists.
   *
   * @param string $tag_id
   *   The tag ID.
   *
   * @return bool
   *   TRUE if the tag exists, FALSE otherwise.
   */
  public function exists($tag_id) {
    return (bool) $this->storage->getQuery()
      ->condition('id', $tag_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->manager->clearCachedDefinitions();
    $form_state->setRedirect('entity.xbbcode_tag.collection');
  }

}
