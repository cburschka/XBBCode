<?php

namespace Drupal\xbbcode\Entity;


use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for custom tag entities.
 *
 * @package Drupal\xbbcode\Entity
 */
interface TagEntityInterface extends EntityInterface {
  /**
   * The tag description.
   *
   * @return string
   *   Tag description.
   */
  public function getDescription();

  /**
   * The default tag name.
   *
   * @return string
   *   Default tag name.
   */
  public function getName();

  /**
   * The sample code.
   *
   * @return string
   *   Tag sample code (using {{ name }} placeholders).
   */
  public function getSample();

  /**
   * An inline template.
   *
   * @return string
   *   The Twig template code.
   */
  public function getTemplateCode();

  /**
   * An external template file.
   *
   * This file must be registered with the theme registry via hook_theme().
   *
   * @return string
   *   A template file.
   */
  public function getTemplateFile();

  /**
   * Return the attachments for this tag.
   *
   * @return array
   *   A valid array to put into #attached.
   */
  public function getAttachments();

  /**
   * Whether the tag is editable.
   *
   * @return bool
   *   Tag is editable.
   */
  public function isEditable();
}
