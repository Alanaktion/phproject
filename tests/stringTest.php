<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class StringTest extends TestCase
{
    protected function setUp(): void
    {
        $f3 = \Base::instance();
        $f3->set('TZ', 'America/Phoenix');
        $f3->set('LANGUAGE', 'en');
    }

    public function testSalt(): void
    {
        $helper = \Helper\Security::instance();
        $result = $helper->salt();
        $this->assertMatchesRegularExpression("/[0-9a-f]{32}/", $result);
    }

    public function testSaltSha1(): void
    {
        $helper = \Helper\Security::instance();
        $result = $helper->salt_sha1();
        $this->assertMatchesRegularExpression("/[0-9a-f]{40}/", $result);
    }

    public function testHash(): void
    {
        $helper = \Helper\Security::instance();
        $string = "Hello world!";
        $hash = $helper->hash($string);
        $this->assertEquals("bcrypt", $hash["salt"]);
        $this->assertTrue(password_verify($string, $hash["hash"]));
    }

    public function testVerifyPassword(): void
    {
        $helper = \Helper\Security::instance();
        $string = "Hello world!";
        $hash = $helper->hash($string);
        $this->assertTrue($helper->verifyPassword($string, $hash["hash"], $hash["salt"]));
        $this->assertFalse($helper->verifyPassword("wrong", $hash["hash"], $hash["salt"]));
    }

    public function testVerifyLegacyPassword(): void
    {
        $helper = \Helper\Security::instance();
        $string = "Hello world!";
        $salt = $helper->salt();
        $legacyHash = sha1($salt . sha1($string));
        $this->assertTrue($helper->verifyPassword($string, $legacyHash, $salt));
        $this->assertFalse($helper->verifyPassword("wrong", $legacyHash, $salt));
    }

    public function testFormatFilesize(): void
    {
        $helper = \Helper\View::instance();
        $size = 1288490189;
        $result = $helper->formatFilesize($size);
        $this->assertContains($result, ["1.2 GB", "1.20 GB"]);
    }

    public function testGravatar(): void
    {
        $helper = \Helper\View::instance();
        $email = "alan@phpizza.com";
        $result = $helper->gravatar($email);
        $this->assertStringContainsString("gravatar.com/avatar/996df14", $result);
    }

    public function testUtc2local(): void
    {
        $helper = \Helper\View::instance();
        $time = 1420498500;
        $result = $helper->utc2local($time);
        $this->assertEquals(1420473300, $result);
    }

    public function testConvertClosedDate(): void
    {
        $helper = \Helper\Update::instance();
        $time = '2016-01-01 12:34:56';
        $result = $helper->convertClosedDate($time);
        $this->assertEquals('January 1, 2016 at 5:34 AM', $result);
    }

    public function testFormatDate(): void
    {
        $f3 = \Base::instance();
        $helper = \Helper\View::instance();
        $timestamp = mktime(12, 0, 0, 1, 15, 2024); // Jan 15, 2024

        $f3->set('LANGUAGE', 'en');
        $this->assertEquals('January 15, 2024', $helper->formatDate($timestamp));

        if (!extension_loaded('intl')) {
            return;
        }
        $f3->set('LANGUAGE', 'de');
        $this->assertEquals('15. Januar 2024', $helper->formatDate($timestamp));

        $f3->set('LANGUAGE', 'fr');
        $this->assertEquals('15 janvier 2024', $helper->formatDate($timestamp));
    }

    public function testFormatDateTime(): void
    {
        $f3 = \Base::instance();
        $helper = \Helper\View::instance();
        $timestamp = mktime(15, 45, 0, 1, 15, 2024); // Jan 15, 2024 3:45 PM

        $f3->set('LANGUAGE', 'en');
        $this->assertEquals('January 15, 2024 at 3:45 PM', $helper->formatDateTime($timestamp));

        if (!extension_loaded('intl')) {
            return;
        }
        $f3->set('LANGUAGE', 'de');
        $this->assertEquals('15. Januar 2024 um 15:45', $helper->formatDateTime($timestamp));
    }

    public function testFormatShortDate(): void
    {
        $f3 = \Base::instance();
        $helper = \Helper\View::instance();
        $timestamp = mktime(12, 0, 0, 1, 15, 2024); // Jan 15, 2024

        $f3->set('LANGUAGE', 'en');
        $this->assertEquals('1/15/24', $helper->formatShortDate($timestamp));

        if (!extension_loaded('intl')) {
            return;
        }
        $f3->set('LANGUAGE', 'de');
        $this->assertEquals('15.01.24', $helper->formatShortDate($timestamp));

        $f3->set('LANGUAGE', 'ja');
        $this->assertEquals('2024/01/15', $helper->formatShortDate($timestamp));
    }
}
