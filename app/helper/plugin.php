<?php

namespace Helper;

class Plugin extends \Prefab
{
    protected $_hooks   = [];
    protected $_nav     = [];
    protected $_jsCode  = [];
    protected $_jsFiles = [];

    /**
     * Register a hook function
     * @param string   $hook
     * @param callable $action
     */
    public function addHook($hook, callable $action)
    {
        if (isset($this->_hooks[$hook])) {
            $this->_hooks[$hook][] = $action;
        } else {
            $this->_hooks[$hook] = [$action];
        }
    }

    /**
     * Call registered hook functions, passing data
     * @param  string $hook
     * @param  mixed  $data
     * @return mixed
     */
    public function callHook($hook, $data = null)
    {
        if (empty($this->_hooks[$hook])) {
            return $data;
        }

        foreach ($this->_hooks[$hook] as $cb) {
            $data = $cb($data);
        }
        return $data;
    }

    /**
     * Add a navigation item
     * @param string $href
     * @param string $title
     * @param string $match
     * @param string $location
     */
    public function addNavItem($href, $title, $match = null, $location = 'root')
    {
        $this->_nav[] = [
            "href"  => $href,
            "title" => $title,
            "match" => $match,
            "location" => $location,
        ];
    }

    /**
     * Add JavaScript code
     * @param string $code
     * @param string $match
     */
    public function addJsCode($code, $match = null)
    {
        $this->_jsCode[] = ["code"  => $code, "match" => $match];
    }

    /**
     * Add a JavaScript file
     * @param string $file
     * @param string $match
     */
    public function addJsFile($file, $match = null)
    {
        $this->_jsFiles[] = ["file"  => $file, "match" => $match];
    }

    /**
     * Get navbar items, optionally setting matching items as active
     * @param  string $path
     * @param  string $location
     * @return array
     */
    public function getNav($path = null, $location = "root")
    {
        $all = $this->_nav;
        $return = [];
        foreach ($all as $item) {
            if ($item['location'] == $location) {
                $return[] = $item + ["active" => ($item["match"] && $path && preg_match($item["match"], $path))];
            }
        }
        return $return;
    }

    /**
     * Get a multidimensional array of all nav items by location
     * @param  string $path
     * @return array
     */
    public function getAllNavs($path = null)
    {
        return [
            "root" => $this->getNav($path, "root"),
            "user" => $this->getNav($path, "user"),
            "new" => $this->getNav($path, "new"),
            "browse" => $this->getNav($path, "browse"),
        ];
    }

    /**
     * Get an array of matching JS files to include
     * @param  string $path
     * @return array
     */
    public function getJsFiles($path = null)
    {
        $return = [];
        foreach ($this->_jsFiles as $item) {
            if (
                !$item['match'] || !$path ||
                ($item['match'] && $path && preg_match($item['match'], $path))
            ) {
                $return[] = $item['file'];
            }
        }
        return $return;
    }

    /**
     * Get an array of matching JS code to include
     * @param  string $path
     * @return array
     */
    public function getJsCode($path = null)
    {
        $return = [];
        foreach ($this->_jsCode as $item) {
            if (
                !$item['match'] || !$path ||
                ($item['match'] && $path && preg_match($item['match'], $path))
            ) {
                $return[] = $item['code'];
            }
        }
        return $return;
    }
}
