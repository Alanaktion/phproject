<?php

use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    protected $user;

    public function setUp()
    {
        if (!\Base::instance()->exists('db.instance')) {
            $this->markTestSkipped(
                'Database connection not available'
            );
            return;
        }
        $this->user = new \Model\User;
        $this->user->load(['rank = ?', \Model\User::RANK_SUPER]);
    }

    public function testAvatar()
    {
        $user = new \Model\User;
        $this->assertFalse($user->avatar());

        $this->assertRegExp('/(\\/\\/gravatar.com\\/avatar\\/[0-9a-f]+|\\/avatar\\/[0-9]+-[0-9]+\\.png+)/', $this->user->avatar());
    }

    public function testGetLists()
    {
        $user = new \Model\User;
        $this->assertThat($user->getAll(), $this->isType('array'));
        $this->assertThat($user->getAllDeleted(), $this->isType('array'));
        $this->assertThat($user->getAllGroups(), $this->isType('array'));
    }

    public function testGetGroupUsers()
    {
        $this->assertThat(
            $this->user->getGroupUsers(),
            $this->logicalOr(
                $this->isType('array'),
                $this->isNull()
            )
        );
    }

    public function testGetGroupUserIds()
    {
        $this->assertThat(
            $this->user->getGroupUserIds(),
            $this->logicalOr(
                $this->isType('array'),
                $this->isNull()
            )
        );
    }

    public function testGetSharedGroupUserIds()
    {
        $this->assertThat(
            $this->user->getSharedGroupUserIds(),
            $this->isType('array')
        );
    }

    public function testOptions()
    {
        $this->assertThat($this->user->options(), $this->isType('array'));
    }

    public function testStats()
    {
        $stats = $this->user->stats();
        $this->assertArrayHasKey('labels', $stats);
        $this->assertArrayHasKey('spent', $stats);
        $this->assertArrayHasKey('closed', $stats);
        $this->assertArrayHasKey('created', $stats);
        $this->assertThat($stats['labels'], $this->isType('array'));
        $this->assertThat($stats['spent'], $this->isType('array'));
        $this->assertThat($stats['closed'], $this->isType('array'));
        $this->assertThat($stats['created'], $this->isType('array'));
    }

    public function testDatePicker()
    {
        $result = $this->user->date_picker();
        $this->assertObjectHasAttribute('language', $result);
        $this->assertObjectHasAttribute('js', $result);
    }

    public function testResetToken()
    {
        $token = $this->user->generateResetToken();
        $this->assertRegExp('/.{96}/', $token);
        $this->assertEquals(hash("sha384", $token), $this->user->reset_token);
        $this->assertTrue($this->user->validateResetToken($token));
        $this->assertFalse($this->user->validateResetToken("asdf"));
    }
}
