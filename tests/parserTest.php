<?php

use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function setUp()
    {
        \Base::instance()->mset([
            'parse.ids' => false,
            'parse.hashtags' => false,
            'parse.urls' => false,
            'parse.emoticons' => false,
            'parse.markdown' => false,
            'parse.textile' => false,
        ]);
    }

    public function testParseIds()
    {
        if (!\Base::instance()->exists('db.instance')) {
            $this->markTestSkipped(
                'Database connection not available'
            );
            return;
        }
        \Base::instance()->set('parse.ids', true);
        $helper = \Helper\View::instance();
        $str = 'Test #1';
        $result = $helper->parseText($str);
        $this->assertRegExp('/Test <a href=".+1">.+<\\/a>/', $result);
        \Base::instance()->set('parse.ids', false);
    }

    public function testParseHashtags()
    {
        \Base::instance()->set('parse.hashtags', true);
        $helper = \Helper\View::instance();
        $str = 'Test #tag';
        $result = $helper->parseText($str);
        $this->assertRegExp('/Test <a href=".*tag\\/tag">#tag<\\/a>/', $result);
        \Base::instance()->set('parse.hashtags', false);
    }

    public function testParseUrls()
    {
        \Base::instance()->set('parse.urls', true);
        $helper = \Helper\View::instance();

        $str = 'Test http://example.com';
        $result = $helper->parseText($str);
        $this->assertRegExp('/Test <a href="http:\\/\\/example.com".*>http:\\/\\/example.com<\\/a>/', $result);

        $str = 'Test www.example.com';
        $result = $helper->parseText($str);
        $this->assertRegExp('/Test <a href="http:\\/\\/www.example.com".*>www.example.com<\\/a>/', $result);

        $str = 'Test user@example.com';
        $result = $helper->parseText($str);
        $this->assertEquals('Test <a href="mailto:user@example.com">user@example.com</a>', $result);

        \Base::instance()->set('parse.urls', false);
    }

    public function testParseEmoticons()
    {
        \Base::instance()->set('parse.emoticons', true);
        $helper = \Helper\View::instance();
        $str = 'Test :P, :(';
        $result = $helper->parseText($str);
        $this->assertEquals("Test \xF0\x9F\x98\x8B, \xF0\x9F\x99\x81", $result);
        \Base::instance()->set('parse.emoticons', false);
    }

    public function testParseTextile()
    {
        \Base::instance()->set('parse.textile', true);
        $helper = \Helper\View::instance();
        $str = "h1. Test\n\n**bold**";
        $result = $helper->parseText($str);
        $this->assertEquals("<h1>Test</h1>\n\n<p><b>bold</b></p>", $result);
        \Base::instance()->set('parse.textile', false);
    }

    public function testParseMarkdown()
    {
        \Base::instance()->set('parse.markdown', true);
        $helper = \Helper\View::instance();
        $str = "# Test\n\n**bold**";
        $result = $helper->parseText($str);
        $this->assertEquals("<h1>Test</h1>\n<p><strong>bold</strong></p>", $result);
        \Base::instance()->set('parse.markdown', false);
    }
}
