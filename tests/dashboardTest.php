<?php

use PHPUnit\Framework\TestCase;

class DashboardTest extends TestCase
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
        if (!$this->user->id) {
            $this->markTestIncomplete(
                'User not available'
            );
        }
        \Base::instance()->set('user', $this->user->cast());
    }

    public function testGetOwnerIds()
    {
        $helper = \Helper\Dashboard::instance();
        $result = $helper->getOwnerIds();
        $this->assertContains($this->user->id, $result);
    }
}
