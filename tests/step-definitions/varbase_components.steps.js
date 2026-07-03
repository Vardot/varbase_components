'use strict';

/**
 * @file
 * Custom step definitions for the Varbase Core test suite.
 *
 * Most of the suite reuses the step definitions that ship with webship-js
 * (navigation, web-first assertions, accessibility). Only a few module-specific
 * helpers live here: logging in as a named user from cucumber.js
 * worldParameters.users, dropping back to an anonymous session, and creating a
 * Basic page through the node-add form.
 */

const { Given, When } = require('@cucumber/cucumber');
const {
  friendly,
  gotoUrl,
  waitForPageLoad,
} = require('webship-js/tests/step-definitions/webship');

/**
 * Run a step body and rethrow any failure as a tester-friendly error.
 *
 * @param {Function} body
 *   Async function performing the step.
 * @param {string} message
 *   Human-readable description for failures.
 */
async function attempt(body, message) {
  try {
    await body();
  }
  catch (err) {
    throw friendly(message, err);
  }
}

/**
 * Log in as a named test user defined in cucumber.js worldParameters.users.
 *
 * Example: Given I am a logged in user with the "Webmaster" user
 */
Given(/^I am a logged in user with( the)*( username)* "([^"]*)?"( user)?$/, async function (theCase, usernameCase, key, userCase) {
  const users = this.parameters.users || {};
  if (!(key in users)) {
    throw new Error(`No user named "${key}" in cucumber.js worldParameters.users`);
  }
  const { username, password } = users[key];
  if (!username || !password) {
    throw new Error(`User "${key}" is missing username or password in worldParameters.users`);
  }
  await attempt(async () => {
    let loggedIn = false;
    for (let i = 0; i < 3 && !loggedIn; i++) {
      await this.context.clearCookies();
      await gotoUrl(this.page, `${this.parameters.launchUrl}/user/login`);
      await this.page.locator('#edit-name').fill(username);
      await this.page.locator('#edit-pass').fill(password);
      await Promise.all([
        this.page.waitForURL((url) => !/\/user\/login/.test(String(url)), { timeout: 30000 }).catch(() => {}),
        this.page.locator('input[value="Log in"]').click(),
      ]);
      await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
      // Confirm the session by loading the account page.
      await gotoUrl(this.page, `${this.parameters.launchUrl}/user`);
      await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
      const denied = await this.page.locator('h1:has-text("Access denied")').count();
      loggedIn = denied === 0;
    }
    if (!loggedIn) {
      throw new Error(`Login did not establish a session for "${key}"`);
    }
  }, `Could not log in as "${key}"`);
});

/**
 * Drop back to an anonymous session by clearing every cookie.
 *
 * Example: Given I am an anonymous visitor
 */
Given(/^(?:I |we )?am an anonymous visitor$/, async function () {
  await attempt(async () => {
    await this.context.clearCookies();
  }, 'Could not clear the session to become anonymous');
});


/**
 * Open an administration page and assert it is reachable.
 *
 * Uses the webship-js smart-wait helpers (gotoUrl + waitForPageLoad) so heavy
 * Varbase admin pages are fully settled before the assertion, and reports any
 * access-denied / not-found / fatal-error page with a tester-friendly message.
 *
 * Example: When I open the administration page "/admin/config"
 */
When(/^I open the administration page "([^"]*)"$/, async function (path) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}${path}`);
    await waitForPageLoad(this.page, (this.minWaitTime && this.minWaitTime.page) || 10000);
    const bad = await this.page.locator(
      'h1:has-text("Access denied"), h1:has-text("Page not found"), h1:has-text("The website encountered an unexpected error")'
    ).count();
    if (bad > 0) {
      throw new Error(`The page "${path}" returned an access-denied, not-found or error response`);
    }
  }, `Could not open the administration page "${path}"`);
});
