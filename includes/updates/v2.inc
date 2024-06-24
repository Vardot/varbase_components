<?php

/**
 * @file
 * Contains varbase_components_update_20###(s) hook updates.
 */

/**
 * Issue #3456578: Update Drupal Core to ~10.3.0 and ~11.0.0.
 *
 * Uninstall the the SDC experimental module as it is now merged into core.
 */
function varbase_components_update_200001() {
  if (\Drupal::moduleHandler()->moduleExists('sdc')) {
    \Drupal::service('module_installer')->uninstall(['sdc'], FALSE);
  }
}
