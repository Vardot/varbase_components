<?php

namespace Drupal\varbase_components\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Finder\Finder;

class ProviderReplaceCommand extends DrushCommands {

  /**
   * Replace old provider name with new provider in the new theme's files.
   *
   * @command provider:replace
   * @aliases pr
   *
   * @param string $old_provider The old provider name.
   * @param string $new_provider The new provider name.
   *
   * @usage drush provider:replace old_theme new_theme
   */
  public function replaceProvider($old_provider, $new_provider) {
    // Get the path of the new theme.
    $theme_path = \Drupal::service('extension.list.theme')->getPath($new_provider);

    if (!$theme_path) {
      $this->output()->writeln("<error>New provider (theme) '{$new_provider}' not found.</error>");
      return;
    }

    $this->output()->writeln("Replacing '{$old_provider}' with '{$new_provider}' in {$theme_path}...");

    // Find all files in the theme directory.
    $finder = new Finder();
    $finder->files()->in($theme_path);

    foreach ($finder as $file) {
      $file_path = $file->getRealPath();
      $content = file_get_contents($file_path);

      if (strpos($content, $old_provider) !== false) {
        $new_content = str_replace($old_provider, $new_provider, $content);
        file_put_contents($file_path, $new_content);
      }
    }

    $this->output()->writeln("<info>Replacement completed.</info>");
  }
}
