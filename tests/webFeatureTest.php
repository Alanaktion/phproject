<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class WebFeatureTest extends TestCase
{
    /** @var \Model\User */
    protected $user;

    protected bool $configured = false;
    protected int $initialOutputBufferLevel = 0;

    /**
     * HTTP status captured from F3's error handling during the last mock()
     * call; null when the route completed without an error.
     */
    protected ?int $lastMockStatus = null;

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
                // Pdo\Mysql::ATTR_INIT_COMMAND exists on PHP 8.4+; PDO::MYSQL_ATTR_INIT_COMMAND is deprecated since 8.5
                [(\PHP_VERSION_ID >= 80400 ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND) => 'SET NAMES utf8mb4;']
            ));
        }

        \Model\Config::loadAll();
        \Helper\Security::instance()->initCsrfToken();

        // Replicate index.php's global template data so full-page renders
        // (e.g. the footer's issue-type picker) behave as in production.
        $f3->set('issue_types', (new \Model\Issue\Type())->find());

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

        // Capture the error status from F3's error handling rather than
        // parsing output: a route that emits partial content before failing
        // would otherwise be misread as a 200.
        $this->lastMockStatus = null;

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
            $this->lastMockStatus = 500;
            echo json_encode([
                "status" => 500,
                "error" => $e->getMessage()
            ], JSON_THROW_ON_ERROR);
        } finally {
            // Capture F3's recorded error status before clearing it. This
            // works regardless of which ONERROR handler ran (controllers
            // may install their own) and even when the route emitted partial
            // output before failing.
            if ($this->lastMockStatus === null) {
                $code = $f3->get('ERROR.code');
                $this->lastMockStatus = $code === null ? null : (int) $code;
            }
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

    /**
     * Public routes that should respond without a fatal error.
     * @return array<string, array{string, int|null}>
     */
    public static function publicRouteProvider(): array
    {
        return [
            'login page renders' => ['GET /login', 200],
            'anonymous home reroutes' => ['GET /', null],
            'opensearch renders' => ['GET /opensearch.xml', 200],
            'api without key is rejected' => ['GET /issues.json', 401],
        ];
    }

    /**
     * Smoke test: hit public routes and assert none of them fatal (HTTP 500).
     * Catches PHP-version incompatibilities in controllers/templates early.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('publicRouteProvider')]
    public function testPublicRouteDoesNotFatal(string $route, ?int $expectedStatus): void
    {
        if (!$this->configured) {
            $this->markTestSkipped();
        }

        // Ensure no user leaks in from other tests; these routes are public
        // and the API case specifically asserts anonymous access is rejected.
        $f3 = \Base::instance();
        $f3->clear('user');
        $f3->clear('user_obj');

        $output = $this->mock($route);
        $this->assertIsString($output, "Route {$route} failed to mock");

        // Use the status captured from F3's error handling; a route that
        // emits partial content before failing must still report its 500.
        $status = $this->lastMockStatus ?? 200;

        $this->assertNotSame(500, $status, "Route {$route} caused a fatal error");
        if ($expectedStatus !== null) {
            $this->assertSame($expectedStatus, $status, "Route {$route} returned an unexpected status");
        }
    }

    /**
     * A route that emits partial output before failing must still report
     * its 500 status instead of being misread as a 200.
     */
    public function testMockCapturesStatusAfterPartialOutput(): void
    {
        if (!$this->configured) {
            $this->markTestSkipped();
        }

        $f3 = \Base::instance();
        $f3->route('GET /_test_partial_output', function (): void {
            echo 'partial output';
            throw new \Exception('boom');
        });

        $this->mock('GET /_test_partial_output');
        $this->assertSame(500, $this->lastMockStatus);
    }
}
