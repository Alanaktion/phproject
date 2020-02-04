<?php

namespace Helper;

class Plugin extends \Prefab
{
    protected $hooks = [];
    protected $nav = [];
    protected $jsCode = [];
    protected $jsFiles = [];

    /**
     * Register a hook function
     * @api
     * @param string   $hook
     * @param callable $action
     */
    public function addHook(string $hook, callable $action)
    {
        if (isset($this->hooks[$hook])) {
            $this->hooks[$hook][] = $action;
        } else {
            $this->hooks[$hook] = [$action];
        }
    }

    /**
     * Call registered hook functions, passing data
     * @param  string $hook
     * @param  mixed  $data
     * @return mixed
     */
    public function callHook(string $hook, $data = null)
    {
        if (empty($this->hooks[$hook])) {
            return $data;
        }

        foreach ($this->hooks[$hook] as $cb) {
            $data = $cb($data);
        }
        return $data;
    }

    /**
     * Add a navigation item
     * @api
     * @param string $href
     * @param string $title
     * @param string|null $match
     * @param string $location
     */
    public function addNavItem(
        string $href,
        string $title,
        ?string $match = null,
        string $location = 'root'
    ) {
        $this->nav[] = [
            'href'  => $href,
            'title' => $title,
            'match' => $match,
            'location' => $location,
        ];
    }

    /**
     * Add JavaScript code
     * @api
     * @param string $code
     * @param string|null $match
     */
    public function addJsCode(string $code, ?string $match = null)
    {
        $this->jsCode[] = [
            'code'  => $code,
            'match' => $match,
        ];
    }

    /**
     * Add a JavaScript file
     * @api
     * @param string $file
     * @param string|null $match
     */
    public function addJsFile(string $file, ?string $match = null)
    {
        $this->jsFiles[] = [
            'file'  => $file,
            'match' => $match,
        ];
    }

    /**
     * Get navbar items, optionally setting matching items as active
     * @param  string|null $path
     * @param  string $location
     * @return array
     */
    public function getNav(?string $path = null, string $location = 'root')
    {
        $return = [];
        foreach ($this->nav as $item) {
            if ($item['location'] == $location) {
                $return[] = $item + [
                    'active' => $item['match'] && $path && preg_match($item['match'], $path),
                ];
            }
        }
        return $return;
    }

    /**
     * Get a multidimensional array of all nav items by location
     * @param  string|null $path
     * @return array
     */
    public function getAllNavs(?string $path = null)
    {
        return array(
            'root' => $this->getNav($path, 'root'),
            'user' => $this->getNav($path, 'user'),
            'new' => $this->getNav($path, 'new'),
            'browse' => $this->getNav($path, 'browse')
        );
    }

    /**
     * Get an array of matching JS files to include
     * @param  string|null $path
     * @return array
     */
    public function getJsFiles(?string $path = null)
    {
        $return = [];
        foreach ($this->jsFiles as $item) {
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
     * @return array
     */
    public function getJsCode(?string $path = null)
    {
        $return = [];
        foreach ($this->jsCode as $item) {
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
