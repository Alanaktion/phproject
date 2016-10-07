---
layout: use
title: Use
---
<h1 class="page-header">Use</h1>

## Basics

#### Issues
Phproject breaks down work into issues. Issues can be projects, tasks, or bugs. Issues can be assigned to a specific user or group of users. Users can track changes and progress on issues with comments and updates. Editing an issue will log the changes that were made, as well as who made them.

#### Notifications
Notifiacations are sent to users when an issue they are involved in is updated. By default, notifications are sent to the creator and assignee of an issue. To have notifications sent to yourself or anoter user, add the user under the Watchers tab on the issue.

#### Files
Users can upload files to issues, and include a comment on each file. Files have automatically generated thumbnails, and record who uploaded the file, and when. By default, there are no limitations on which types of files can be uploaded.

#### Sprints
Sprints are a collection of projects, containing tasks and bugs, which are going to be worked on in a specific period of time. Users can see the list of sprints as well as the project backlog by clicking Sprints in the top navigation. For an interactive sprint-specific task board, click the Taskboard button on a specific sprint. Sprints can only be created by Administrators.

While infinitely-nested projects are allowed, only bottom-level projects are shown on the backlog. The backlog and sprints can be sorted on a per-group basis, based on the group selected in the filter.

#### Administration
Administrators can see an overview of everything on the site. They can create new users and user groups, as well as manage sprints. Administrators are also able to delete issues and comments.

#### Hotkeys
Phproject includes a number of hotkeys which can be used to quickly perform common tasks:

<div class="well">
	<div class="row">
		<div class="col-xs-6">
			<p><b>Global</b></p>
			<kbd>?</kbd>: Show hotkeys<br>
			<kbd>b</kbd>: Browse issues<br>
			<kbd>r</kbd>: Reload page<br>
			<kbd>alt+1</kbd>: Create new Task<br>
			<kbd>alt+2</kbd>: Create new Project<br>
			<kbd>alt+3</kbd>: Create new Bug<br>
		</div>
		<div class="col-xs-6">
			<p><b>Issues</b></p>
			<kbd>w</kbd>: Watch/unwatch<br>
			<kbd>e</kbd>: Edit issue<br>
			<kbd>c</kbd>: Write a comment<br>
			<kbd>ctrl+enter</kbd>: Save edit/comment<br>
			<kbd>shift+c</kbd>: Mark as complete<br>
			<kbd>shift+r</kbd>: Reopen issue<br>
		</div>
	</div>
</div>

Note that the alt hotkeys for creating new issues are dependant on the `issue_types` table. If your Phproject installation has modified values, the hotkeys will be different.

<hr>

## Advanced
Phproject includes some advanced features that allow users to integrate other systems with their Phproject site, including email and other software applications.

#### Incoming mail
To allow users to create issues and comment on existing issues via email, set up an email inbox with remote IMAP access. You can then add the IMAP connection settings in your Administration > Configuration tab, and add a cron job to your server that runs `cron/checkmail.php`, which imports emails as issues and comments from the email inbox.

Example configuration for Gmail:

```
Hostname: {imap.gmail.com:993/imap/ssl}INBOX
Username: phproject@example.com
Password: Passw0rd1
```

#### Due issue alerts
Phproject can send alert emails to users with issues that are overdue or due on the current date. This can be enabled by setting up a cron job to run `cron/due_alerts.php`. Users can opt-out of these emails in their settings.

In order for emails to include correct URLs, make sure your site's base URL (e.g. `http://example.com/phproject/`) is set as `site.url` in either config.ini or the config database table.

#### Atom feeds
Phproject generates Atom feeds for issues assigned to and created by each user. These feeds can be subscribed to from user pages or the Dashboard in browsers that support them.

#### REST API
Phproject provides a REST API, and generates API keys for administrator users, which can be used by your application to authenticate API requests. The API currently provides basic functionality for listing, viewing, and creating issues, and listing and viewing users. This API is partially compatible with the Redmine API, although in practice using a Redmine client is not recommended. All API responses are in JSON format.

Each request to the API must include one of the following to authenticate:

* An `X-API-Key` header containing a valid API key
* A `key` GET parameter containing a valid API key
* An `X-Redmine-API-Key` header containing a valid API key (not recommended)

To ease debugging with the API, logging in as a user with API access from the normal web interface will allow accessing the API actions through a browser without additional headers or parameters.

##### Issues
`GET /issues.json` retrives a list of issues. Results can be paginated by passing the `offset` and `limit` GET parameters.

`POST /issues.json` creates a new issue. Fields must be passed as an HTTP query string or a JSON object. The only required field is `name`.

`GET /issues/{ID}.json` retrives a single issue by ID.

`PUT /issues/{ID}.json` updates a single issue by ID. Fields to be updated must be passed as an HTTP query string.

`DELETE /issues/{ID}.json` deletes a single issue by ID.

##### Users
`GET /user.json` retrives a list of users. Results can be paginated by passing the `offset` and `limit` GET parameters.

`GET /user/{username}.json` retrives a single user by username.

`GET /useremail/{email}.json` retrives a single user by email.

`GET /usergroups.json` retrives a list of groups. Results can be paginated by passing the `offset` and `limit` GET parameters.
