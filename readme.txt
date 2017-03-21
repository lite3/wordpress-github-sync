=== Writing On GitHub ===
Contributors: lite3
Tags: github, git, version control, content, collaboration, publishing
Donate link: https://www.paypal.me/litefeel
Requires at least: 3.9
Tested up to: 4.7.3
Stable tag: 1.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to allow you writing on GitHub (or Jekyll site).

== Description ==
A WordPress plugin to allow you writing on GitHub (or Jekyll site).

== Installation ==
1. Navigate to the `Add New` in the plugins dashboard
2. Search for `Writing On GitHub`
3. Click `Install Now`
4. Activate the plugin on the Plugin dashboard

=== Configuring the plugin ===

1. [Create a personal oauth token](https://github.com/settings/tokens/new) with the `public_repo` scope. If you\'d prefer not to use your account, you can create another GitHub account for this.
2. Configure your GitHub host, repository, secret (defined in the next step),  and OAuth Token on the Writing On GitHub settings page within WordPress\'s administrative interface. Make sure the repository has an initial commit or the export will fail.
3. Create a WebHook within your repository with the provided callback URL and callback secret, using `application/json` as the content type. To set up a webhook on GitHub, head over to the **Settings** page of your repository, and click on **Webhooks & services**. After that, click on **Add webhook**.
4. Click `Export to GitHub`.
