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
}
