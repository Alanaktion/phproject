<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class WebFeatureTest extends TestCase
{
    /** @var \Model\User */
    protected $user;

    protected bool $configured = false;
    protected int $initialOutputBufferLevel = 0;

    protected function setUp(): void
    {
        $this->initialOutputBufferLevel = ob_get_level();

        $f3 = \Base::instance();
        if ($this->configured) {
            $f3->set('ERROR', null);
            return;
        }

        $config_file = dirname(__DIR__) . '/config.php';
        if (!file_exists($config_file)) {
            return;
        }
        $config = include($config_file);
        if (!$config) {
            return;
        }

        $f3->mset($config);
        $f3->config(dirname(__DIR__) . '/app/routes.ini');

        if ($f3->get('db.engine') == 'sqlite') {
            $f3->set('db.instance', new \Helper\SQL('sqlite:' . $f3->get('db.name')));
        } else {
            $f3->set('db.instance', new \Helper\SQL(
                'mysql:host=' . $f3->get('db.host') . ';port=' . $f3->get('db.port') . ';dbname=' . $f3->get('db.name'),
                $f3->get('db.user'),
                $f3->get('db.pass'),
                [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4;']
            ));
        }

        \Model\Config::loadAll();
        \Helper\Security::instance()->initCsrfToken();

        $this->configured = true;
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialOutputBufferLevel) {
            ob_end_clean();
        }
    }

    protected function mock(string $route, ?array $args = null, ?array $headers = null): string|false
    {
        // Track initial buffer level to ensure cleanup
        $initialLevel = ob_get_level();
        $f3 = \Base::instance();

        // Capture output
        ob_start();

        // Store original handlers
        $originalErrorHandler = $f3->get('ONERROR');
        $originalOnReroute = $f3->get('ONREROUTE');
        $originalHalt = $f3->get('HALT');

        // Prevent F3 from calling exit by setting HALT to false
        $f3->set('HALT', false);

        // Set custom error handler that won't exit
        $f3->set('ONERROR', function ($f3) {
            echo json_encode([
                "status" => $f3->get("ERROR.code"),
                "error" => $f3->get("ERROR.text"),
            ], JSON_THROW_ON_ERROR);
        });

        // Prevent reroute() from calling die during tests
        $f3->set('ONREROUTE', static function (): bool {
            return true;
        });

        try {
            $f3->mock($route, $args, $headers);
        } catch (\Throwable $e) {
            // Catch any uncaught exceptions
            echo json_encode([
                "status" => 500,
                "error" => $e->getMessage()
            ], JSON_THROW_ON_ERROR);
        } finally {
            // Restore original handlers
            $f3->set('ONERROR', $originalErrorHandler);
            $f3->set('ONREROUTE', $originalOnReroute);
            $f3->set('HALT', $originalHalt);
            $f3->clear('ERROR');
        }

        // Clean up all output buffers started during this call
        $output = '';
        while (ob_get_level() > $initialLevel) {
            $buffer = ob_get_clean();
            if ($buffer !== false) {
                $output .= $buffer;
            }
        }

        return $output;
    }

    protected function csrfToken(): string
    {
        $f3 = \Base::instance();
        \Helper\Security::instance()->initCsrfToken();
        return (string) $f3->get('COOKIE.XSRF-TOKEN');
    }

    protected function loadTestAdmin(): ?\Model\User
    {
        $user = new \Model\User();
        $user->load(['username = ? AND deleted_date IS NULL', 'test']);
        if (!$user->id) {
            return null;
        }

        return $user;
    }

    protected function setLoggedInUser(\Model\User $user): void
    {
        $f3 = \Base::instance();
        $f3->set('user', $user->cast());
        $f3->set('user_obj', $user);
    }

    public function testLoginLogoutFlowWithCsrf(): void
    {
        if (!$this->configured) {
            $this->markTestSkipped();
        }

        $user = $this->loadTestAdmin();
        if (!$user) {
            $this->markTestSkipped('Expected admin user test was not found.');
        }

        $security = \Helper\Security::instance();
        if (!$security->verifyPassword('secret', $user->password, $user->salt ?: '')) {
            $this->markTestSkipped('Expected admin test user does not have password secret.');
        }

        $this->mock('POST /login', [
            'username' => 'test',
            'password' => 'secret',
            'csrf-token' => $this->csrfToken(),
        ]);

        $token = (string) \Base::instance()->get('COOKIE.' . \Model\Session::COOKIE_NAME);
        $this->assertNotSame('', $token);

        $session = new \Model\Session();
        $session->load(['token = ?', $token]);
        $this->assertSame((int) $user->id, (int) $session->user_id);

        $this->setLoggedInUser($user);
        $this->mock('POST /logout', [
            'csrf-token' => $this->csrfToken(),
        ]);

        $session->reset();
        $session->load(['token = ?', $token]);
        $this->assertFalse((bool) $session->id);
    }

    public function testIssueSaveCreatesIssueViaWebRoute(): void
    {
        if (!$this->configured) {
            $this->markTestSkipped();
        }

        $user = $this->loadTestAdmin();
        if (!$user) {
            $this->markTestSkipped('Expected admin user test was not found.');
        }

        $this->setLoggedInUser($user);

        $title = 'Web issue test ' . uniqid('', true);
        $this->mock('POST /issues/save', [
            'csrf-token' => $this->csrfToken(),
            'type_id' => 1,
            'status' => 1,
            'priority' => 0,
            'name' => $title,
            'description' => 'Created from web feature test',
        ]);

        $issue = new \Model\Issue();
        $issue->load(['name = ? AND author_id = ? AND deleted_date IS NULL', $title, $user->id]);

        $this->assertNotFalse((bool) $issue->id);
        $this->assertSame($title, (string) $issue->name);
        $this->assertSame('Created from web feature test', (string) $issue->description);

        if ($issue->id) {
            $issue->delete(false);
        }
    }

    protected function createUser(string $prefix, int $rank): \Model\User
    {
        $security = \Helper\Security::instance();
        $user = new \Model\User();
        $user->username = $prefix . uniqid();
        $user->email = $user->username . '@example.com';
        $user->name = $prefix;
        $user->role = $rank >= \Model\User::RANK_ADMIN ? 'admin' : 'user';
        $user->rank = $rank;
        $user->salt = $security->salt();
        $user->password = $security->hash('original-password', $user->salt);
        $user->task_color = '336699';
        $user->created_date = date('Y-m-d H:i:s');
        $user->save();
        return $user;
    }

    public function testAdminCannotModifyHigherRankUser(): void
    {
        if (!$this->configured) {
            $this->markTestSkipped();
        }

        $admin = $this->createUser('rankadmin', \Model\User::RANK_ADMIN);
        $super = $this->createUser('ranksuper', \Model\User::RANK_SUPER);
        $peer = $this->createUser('rankpeer', \Model\User::RANK_USER);

        try {
            $this->setLoggedInUser($admin);

            // Edit a higher-ranked user
            $output = $this->mock('POST /admin/users/save', [
                'csrf-token' => $this->csrfToken(),
                'user_id' => $super->id,
                'username' => $super->username,
                'email' => $super->email,
                'name' => 'Hijacked',
                'task_color' => 'ff0000',
                'rank' => \Model\User::RANK_CLIENT,
                'password' => 'AttackerChosen123',
                'password_confirm' => 'AttackerChosen123',
            ]);
            $this->assertStringContainsString('"status":403', (string) $output);

            $check = new \Model\User();
            $check->load($super->id);
            $this->assertSame((string) $super->password, (string) $check->password);
            $this->assertSame(\Model\User::RANK_SUPER, (int) $check->rank);
            $this->assertSame('ranksuper', (string) $check->name);

            // Promote a lower-ranked user above own rank
            $this->mock('POST /admin/users/save', [
                'csrf-token' => $this->csrfToken(),
                'user_id' => $peer->id,
                'username' => $peer->username,
                'email' => $peer->email,
                'name' => 'Peer',
                'task_color' => '00ff00',
                'rank' => \Model\User::RANK_SUPER,
            ]);
            $check = new \Model\User();
            $check->load($peer->id);
            $this->assertSame(\Model\User::RANK_USER, (int) $check->rank);

            // Delete a higher-ranked user, directly or via the group route
            $this->mock('POST /admin/users/' . $super->id . '/delete', [
                'csrf-token' => $this->csrfToken(),
            ]);
            $this->mock('POST /admin/groups/' . $super->id . '/delete', [
                'csrf-token' => $this->csrfToken(),
            ]);
            $check = new \Model\User();
            $check->load($super->id);
            $this->assertNull($check->deleted_date);
        } finally {
            $admin->erase();
            $super->erase();
            $peer->erase();
        }
    }

    public function testRestrictedAccessEnforcedOnIssueWrites(): void
    {
        if (!$this->configured) {
            $this->markTestSkipped();
        }

        $f3 = \Base::instance();
        $originalRestrict = $f3->get('security.restrict_access');

        $alice = $this->createUser('restrictalice', \Model\User::RANK_USER);
        $bob = $this->createUser('restrictbob', \Model\User::RANK_MANAGER);

        $secret = \Model\Issue::create([
            'type_id' => 1,
            'status' => 1,
            'priority' => 0,
            'name' => 'Restricted web issue',
            'description' => '',
            'author_id' => $alice->id,
            'owner_id' => $alice->id,
        ], false);
        $own = \Model\Issue::create([
            'type_id' => 1,
            'status' => 1,
            'priority' => 0,
            'name' => 'Accessible web issue',
            'description' => '',
            'author_id' => $bob->id,
            'owner_id' => $bob->id,
        ], false);
        $dependency = new \Model\Issue\Dependency();
        $dependency->issue_id = $secret->id;
        $dependency->dependency_id = $own->id;
        $dependency->dependency_type = 'FS';
        $dependency->save();

        try {
            $f3->set('security.restrict_access', 1);
            \Registry::clear(\Helper\Dashboard::class);
            $this->setLoggedInUser($bob);

            // Comment and close
            $output = $this->mock('POST /issues/comment/save', [
                'csrf-token' => $this->csrfToken(),
                'issue_id' => $secret->id,
                'text' => 'Injected',
                'action' => 'close',
            ]);
            $this->assertStringContainsString('"status":403', (string) $output);
            $comment = new \Model\Issue\Comment();
            $this->assertSame(0, $comment->count(['issue_id = ?', $secret->id]));
            $check = new \Model\Issue();
            $check->load($secret->id);
            $this->assertNull($check->closed_date);

            // Watchers
            $this->mock('POST /issues/' . $secret->id . '/watchers', [
                'csrf-token' => $this->csrfToken(),
                'user_id' => $bob->id,
            ]);
            $watcher = new \Model\Issue\Watcher();
            $this->assertSame(0, $watcher->count(['issue_id = ?', $secret->id]));

            // Adding a watcher who cannot see the issue
            $this->mock('POST /issues/' . $own->id . '/watchers', [
                'csrf-token' => $this->csrfToken(),
                'user_id' => $alice->id,
            ]);
            $this->assertSame(0, $watcher->count(['issue_id = ? AND user_id = ?', $own->id, $alice->id]));

            // Dependencies, via the restricted issue's route and via an accessible issue's route
            $this->mock('POST /issues/' . $secret->id . '/dependencies', [
                'csrf-token' => $this->csrfToken(),
                'issue_id' => $secret->id,
                'dependency_id' => $own->id,
                'type' => 'SS',
            ]);
            $this->mock('POST /issues/' . $own->id . '/dependencies', [
                'csrf-token' => $this->csrfToken(),
                'issue_id' => $own->id,
                'dependency_id' => $secret->id,
                'type' => 'FS',
            ]);
            $this->assertSame(1, $dependency->count(['issue_id = ? OR dependency_id = ?', $secret->id, $secret->id]));

            $this->mock('POST /issues/' . $secret->id . '/dependencies/delete', [
                'csrf-token' => $this->csrfToken(),
                'id' => $dependency->id,
            ]);
            $this->assertSame(1, $dependency->count(['id = ?', $dependency->id]));

            // Delete as a manager without access
            $output = $this->mock('POST /issues/delete/' . $secret->id, [
                'csrf-token' => $this->csrfToken(),
            ]);
            $this->assertStringContainsString('"status":403', (string) $output);
            $check = new \Model\Issue();
            $check->load($secret->id);
            $this->assertNull($check->deleted_date);

            // Taskboard and backlog edits
            $this->mock('POST /taskboard/edit/' . $secret->id, [
                'csrf-token' => $this->csrfToken(),
                'taskId' => $secret->id,
                'title' => 'Hijacked',
                'description' => '',
                'assigned' => $bob->id,
                'priority' => 0,
            ]);
            $this->mock('POST /backlog/edit', [
                'csrf-token' => $this->csrfToken(),
                'id' => $secret->id,
                'sprint_id' => 999,
            ]);
            $check = new \Model\Issue();
            $check->load($secret->id);
            $this->assertSame('Restricted web issue', (string) $check->name);
            $this->assertSame((int) $alice->id, (int) $check->owner_id);
            $this->assertNull($check->sprint_id);

            // Access to own issue still works
            $this->mock('POST /issues/' . $own->id . '/watchers', [
                'csrf-token' => $this->csrfToken(),
                'user_id' => $bob->id,
            ]);
            $this->assertSame(1, $watcher->count(['issue_id = ? AND user_id = ?', $own->id, $bob->id]));
        } finally {
            $f3->set('security.restrict_access', $originalRestrict);
            \Registry::clear(\Helper\Dashboard::class);
            $f3->get('db.instance')->exec(
                'DELETE FROM issue_watcher WHERE issue_id IN (?, ?)',
                [1 => $secret->id, 2 => $own->id]
            );
            $f3->get('db.instance')->exec(
                'DELETE FROM issue_dependency WHERE issue_id IN (?, ?) OR dependency_id IN (?, ?)',
                [1 => $secret->id, 2 => $own->id, 3 => $secret->id, 4 => $own->id]
            );
            $f3->get('db.instance')->exec(
                'DELETE FROM issue_comment WHERE issue_id IN (?, ?)',
                [1 => $secret->id, 2 => $own->id]
            );
            $secret->erase();
            $own->erase();
            $alice->erase();
            $bob->erase();
        }
    }
}
