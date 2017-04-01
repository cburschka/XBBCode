<?php

namespace Drupal\xbbcode\Plugin;

use Drupal\xbbcode\ElementInterface;

/**
 * This is a tag that delegates processing to a Twig template.
 */
abstract class TemplateTagPlugin extends TagPluginBase {

  /**
   * Get the tag template.
   *
   * @return \Twig_Template
   *   The compiled template that should render this tag.
   */
  abstract protected function getTemplate();

  /**
   * {@inheritdoc}
   */
  public function process(ElementInterface $tag) {
    return $this->getTemplate()->render([
      'settings' => $this->settings,
      'tag' => $tag,
    ]);
  }

}
