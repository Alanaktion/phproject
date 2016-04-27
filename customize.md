---
layout: md
title: Customization
---
<h1 class="page-header">Customize</h1>

## Issues

Phproject has three issue types that are included by default, Tasks, Projects, and Bugs. These issue types can be modified and new ones can be added by in the `issue_type` database table. If you're updating the existing issues, or would like to change which issue types are used for Tasks and Projects on the Taskboard, add/update the `issue_type.task` and `issue_type.project` options in your `config.ini` file with the `id` values of the `issue_type`s you want to use.

Issues also have a Status and Priority value, which uses options from the `issue_status` and `issue_priority` tables, respectively. These tables can also be modified with the statuses and priorities needed. No changes outside of the database tables are required for these options. Note that priorities are designed to default to `0`, though this is not required.


## Appearance

Phproject's UI is built around [Twitter Bootstrap 3](http://getbootstrap.com), and is compatible with customized Bootstrap styles including Bootswatch. Changing the 'Default Theme' option in Administration > Configuration to the web path of a Bootstrap CSS file will replace the main CSS. Phproject already includes all of the themes from Bootswatch as well as a few custom ones we've built ourselves based on Bootswatch's Flatly. Phproject's additions to the Bootstrap core are designed to add features without breaking any existing components, so unless your customized Bootstrap is very heavily modified, everything should continue to work consistently. Dark themes are known to have a few issues, if you need to use a dark theme, include [the CSS below](#dark-css) to ensure everything looks right.

Your site name and meta description can also be updated in the Administration > Configuration tab. Phproject also allows using an image for the logo in the top navigation. Adding an image URL under the 'Logo' option on the Site Basics configuration will replace the text logo in the navbar with your logo image.

You can also customize the default user image that is shown when a user does not have a Gravatar (Phproject uses `mm` by default) as well as the maximum content rating of Gravatars to show (`pg` by default). The `gravatar.default` and `gravatar.rating` entries in the `config` database table can be added/updated to change these.

### CSS for dark themes

If you want to use a custom Bootstrap theme that uses light text on a dark background, you'll find that some of the custom componants don't look great. Use the CSS below to make the components display properly, making adjustments where necessary to match your design.

```css
/* This CSS fixes display issues when using a dark Bootstrap theme */
#taskboard table.taskboard-head {
	background-color: transparent;
}
#taskboard table.taskboard-head th {
	background-color: #222;
	background-color: rgba(34,34,34,.8) !important;
	color: white !important;
}
#taskboard td, #taskboard th {
	border-color: #666 !important;
}
#taskboard .spinner {
	background-image: url('../img/ajax-loader-dark.gif') !important;
}
.datepicker {
	color: #111;
}
```


## Demo Mode

While not a necessary feature for most installations, Phproject includes a demo mode which causes the site to automatically log in as a specific user, as well as display a demo alert at the top of each page. This can be enabled by setting `site.demo` in the `config` database table to the ID of the user you want to auto-login as.
