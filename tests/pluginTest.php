<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testHook()
    {
        $helper = \Helper\Plugin::instance();

        // Set hook
        $helper->addHook("test", function () {
            $GLOBALS["test--testHook"] = true;
        });

        // Trigger hook
        $helper->callHook("test");

        $this->assertArrayHasKey("test--testHook", $GLOBALS);
    }

    public function testNav()
    {
        $helper = \Helper\Plugin::instance();

        // Add nav items
        $helper->addNavItem("test1", "Test 1", "/^\/test1/", "root");
        $helper->addNavItem("test2", "Test 2", "/^\/test2/", "user");

        // Get all nav items
        $result = $helper->getAllNavs("/test1");
        $expected = array(
            "root" => array(
                array(
                    "href"  => "test1",
                    "title" => "Test 1",
                    "match" => "/^\/test1/",
                    "location" => "root",
                    "active" => true
                )
            ),
            "user" => array(
                array(
                    "href"  => "test2",
                    "title" => "Test 2",
                    "match" => "/^\/test2/",
                    "location" => "user",
                    "active" => false
                )
            ),
            "new" => array(),
            "browse" => array(),
        );

        $this->assertEquals($result, $expected);
    }

    public function testJsFiles()
    {
        $helper = \Helper\Plugin::instance();

        // Add JS file
        $helper->addJsFile("test.js", "/^\/test1/");

        // Get JS file list
        $result1 = $helper->getJsFiles("/test1");
        $result2 = $helper->getJsFiles("/test2");

        $expected = array("test.js");
        $this->assertEquals($result1, $expected);
        $this->assertNotEquals($result2, $expected);
    }

    public function testJsCode()
    {
        $helper = \Helper\Plugin::instance();

        // Add JS code block
        $helper->addJsCode("'test';", "/^\/test1/");

        // Get JS code block list
        $result1 = $helper->getJsCode("/test1");
        $result2 = $helper->getJsCode("/test2");

        $expected = array("'test';");
        $this->assertEquals($result1, $expected);
        $this->assertNotEquals($result2, $expected);
    }
}
