---
layout: md
title: Plugins
---
<h1 class="page-header">Plugins</h1>

<p class="alert alert-info">Plugin support is limited in the <code>release</code> branch. For the most up-to-date plugin support, use <code>master</code>.</p>

## Installing plugins
Most plugins can be installed by simply cloning their repository in your `app/plugin` directory. To install without git, simply copy/move the plugin's folder to `app/plugin`. Note that the folder name *must* match the plugin's package name (@package in the plugin's `base.php` file).

### Official plugins

Phproject includes several officially supported plugins that are maintained along with the core code.

[Bitbucket](https://github.com/phproject-plugins/bitbucket) - Integrate Bitbucket commits into your Phproject issues

[Porter](https://github.com/phproject-plugins/porter) - Maintains your Phproject instance

[Wiki](https://github.com/phproject-plugins/wiki) - A minimal but powerful Wiki for your projects

[All Official Plugins &rsaquo;](https://github.com/phproject-plugins)

---

## Plugin development

<p class="text-warning">Plugin support is still under development, and may include significant changes to the API with future updates.</p>

Plugins are made up of one or more PHP classes installed the `app/plugin` directory. As a minimum, all plugins must have a `base.php` file within their own directory.


### Plugin Standards

* Plugins must have a `Base` class in `base.php` that extends `\Plugin`
    * This class should contain all core code for the plugin, including all methods and properties in the Plugin Standards
* Plugins must have a `_load()` method which is called when initializing the plugin
    * Any hooks and routes used in the plugin should be initialized in this method
* Plugins must have a PHPDoc comment block at the start of the `base.php` file
    * Block must contain at least the @package tag
    * Block should contain the @author tag
* Plugins must have an `_installed()` method which will be called to check the installation status of the plugin
* Plugins may have an `_install()` method which will be called if `_installed()` returns `false`
* Plugins may have a `dict` directory, which will be loaded for localization
* Plugins should follow the [Code Standards](/contribute.html)

[Plugin README &rsaquo;](https://github.com/Alanaktion/phproject/tree/master/app/plugin/README.md)

### Routes

If your plugin adds additional routes, they should point to a separate class outside of `base.php`, which should extend `\Controller`. Routes can be added in your `Base->_load()` method by getting the F3 Base class and calling the [`route()`](http://fatfreeframework.com/base#route) method on it:

{% highlight php %}
<?php
...
public function _load() {
    $f3 = \Base::instance();
    $f3->route("GET /yourplugin/action", "Plugin\Yourplugin\Controller->action");
}
...
?>
{% endhighlight %}


### Hooks

Hooks are not currently implemented in Phproject, but will be initialized from your `Base->_load()` method with calls to `$this->_hook()` in future releases.

#### Future hooks

`view.parse`, `model.before_save`, `model.after_save`, `model/issue.before_save`, `model/issue.after_save`


### Menu Items

Menu items can be added to the main navigation with a simple call to `$this->_addNav($href, $title, $match = null)` from your `Base->_load()` method. The third parameter is optional, and if present will be run through `preg_match()` with the current F3 `@PATH` to determine whether the menu item is active.

{% highlight php %}
<?php
    // ...
    public function _load() {
        $this->_addNav("myplugin/page", "My Plugin", "/myplugin\/page/");
    }
    // ...
?>
{% endhighlight %}


### Localization

Plugins can provide additional localized strings by adding a `dict` directory to the plugin, with `*.ini` files for each language supported. These files must follow the same structure as the Phproject core `dict/*.ini` files, with the addition of a plugin namespace. For example:

{% highlight ini %}
dict.yourplugin.my_button=My Button
{% endhighlight %}

These can be called within templates using the standard syntax found in all the core views.

### Example Base Class

{% highlight php %}
<?php
/**
 * @package YourPlugin
 * @author  Phproject User <user@example.org>
 * @version 1.0.0
 */

namespace Plugin\YourPlugin;

class Base extends \Plugin {

    public function _load() {
        $this->_hook( ... );
    }

    public function _installed() {
        return true;
    }

}
?>
{% endhighlight %}

### Example folder structure

    app
        plugin
            yourplugin
                base.php
                ...

