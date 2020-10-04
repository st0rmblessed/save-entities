<?php

namespace Drupal\Tests\save_entities\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test module.
 *
 * @group multiple_select
 */
class CrudFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'save_entities',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'seven';

  /**
   * Test access to configuration page.
   */
  public function testCanAccessConfigPage() {
    $account = $this->drupalCreateUser([
      'access node config page',
      'access media config page',
      'access content',
    ]);

    $this->drupalLogin($account);
    $this->drupalGet('/admin/config/content/save-media');
    $this->assertText('Save Media Helper');
    $this->drupalGet('/admin/config/content/save-nodes');
    $this->assertText('Save Nodes Helper');
  }

}
