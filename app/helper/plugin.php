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
     */
    public function addHook($hook, callable $action): void
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
     * @param string|null $match
     * @param string $location
     */
    public function addNavItem($href, $title, $match = null, $location = 'root'): void
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
     * @param string|null $match
     */
    public function addJsCode($code, $match = null): void
    {
        $this->_jsCode[] = ["code"  => $code, "match" => $match];
    }

    /**
     * Add a JavaScript file
     * @param string $file
     * @param string|null $match
     */
    public function addJsFile($file, $match = null): void
    {
        $this->_jsFiles[] = ["file"  => $file, "match" => $match];
    }

    /**
     * Get navbar items, optionally setting matching items as active
     * @param  string|null $path
     * @param  string $location
     */
    public function getNav($path = null, $location = "root"): array
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
     * @param  string|null $path
     */
    public function getAllNavs($path = null): array
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
     * @param  string|null $path
     */
    public function getJsFiles($path = null): array
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
     * @param  string|null $path
     */
    public function getJsCode($path = null): array
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
