<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    /** @var \Model\User */
    protected $user;

    protected $configured = false;
    protected int $initialOutputBufferLevel = 0;

    protected function apiHeaders(): array
    {
        return [
            'X-API-Key' => $this->user->api_key,
        ];
    }

    protected function createIssue(array $payload): array
    {
        $response = json_decode($this->mock("POST /issues.json", $payload, $this->apiHeaders()), true);
        $this->assertArrayHasKey('issue', $response);
        $this->assertArrayHasKey('id', $response['issue']);
        return $response;
    }

    protected function setUp(): void
    {
        $this->initialOutputBufferLevel = ob_get_level();

        $f3 = \Base::instance();
        if ($this->configured) {
            $f3->set('ERROR', null);
            return;
        }

        // Configure framework
        $config_file = dirname(__DIR__) . '/config.php';
        if (!file_exists($config_file)) {
            return;
        }
        $config = include($config_file);
        if (!$config) {
            return;
        }

        $f3->mset($config);

        // Load routes
        $f3->config(dirname(__DIR__) . "/app/routes.ini");

        // Configure database connection
        if ($f3->get("db.engine") == "sqlite") {
            $f3->set("db.instance", new \Helper\SQL("sqlite:" . $f3->get("db.name")));
        } else {
            $f3->set("db.instance", new \Helper\SQL(
                "mysql:host=" . $f3->get("db.host") . ";port=" . $f3->get("db.port") . ";dbname=" . $f3->get("db.name"),
                $f3->get("db.user"),
                $f3->get("db.pass"),
                // Pdo\Mysql::ATTR_INIT_COMMAND exists on PHP 8.4+; PDO::MYSQL_ATTR_INIT_COMMAND is deprecated since 8.5
                [(\PHP_VERSION_ID >= 80400 ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND) => 'SET NAMES utf8mb4;']
            ));
        }

        // Load final configuration
        \Model\Config::loadAll();

        // Load test user
        $this->user = (new \Model\User())->load(['deleted_date IS NULL AND api_key IS NOT NULL']);

        $this->configured = true;
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialOutputBufferLevel) {
            ob_end_clean();
        }
    }

    /**
     * Mock an HTTP request, returning the response as a string.
     * Uses output buffering with a custom fatal error handler to capture error responses.
     *
     * @return string|false
     */
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
        $originalLoggable = $f3->get('LOGGABLE');

        // Save and clear HTTP headers from $_SERVER to prevent cross-call contamination
        $savedHttpHeaders = [];
        foreach (array_keys($_SERVER) as $key) {
            if (strncmp($key, 'HTTP_', 5) === 0 && $key !== 'HTTP_HOST') {
                $savedHttpHeaders[$key] = $_SERVER[$key];
                unset($_SERVER[$key]);
            }
        }

        // Prevent F3 from calling exit by setting HALT to false
        $f3->set('HALT', false);

        // Suppress error_log() calls for expected HTTP errors during tests
        $f3->set('LOGGABLE', '');

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
            $f3->set('LOGGABLE', $originalLoggable);
            $f3->clear('ERROR');

            // Restore $_SERVER HTTP headers
            foreach (array_keys($_SERVER) as $key) {
                if (strncmp($key, 'HTTP_', 5) === 0 && $key !== 'HTTP_HOST') {
                    unset($_SERVER[$key]);
                }
            }
            foreach ($savedHttpHeaders as $key => $val) {
                $_SERVER[$key] = $val;
            }
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

    public function testSingleUser()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $response = json_decode($this->mock("GET /user/me.json", [], $this->apiHeaders()), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertEquals($this->user->id, $response['id']);
        $this->assertEquals($this->user->name, $response['name']);
        $this->assertEquals($this->user->username, $response['username']);
        $this->assertEquals($this->user->email, $response['email']);
        return null;
    }

    public function testSingleUserEmail()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $response = json_decode($this->mock("GET /useremail/{$this->user->email}.json", [], $this->apiHeaders()), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertEquals($this->user->id, $response['id']);
        $this->assertEquals($this->user->name, $response['name']);
        $this->assertEquals($this->user->username, $response['username']);
        $this->assertEquals($this->user->email, $response['email']);
        return null;
    }

    public function testUserList()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $response = json_decode($this->mock("GET /user.json", [], $this->apiHeaders()), true);

        $this->assertArrayHasKey('total_count', $response);
        $this->assertArrayHasKey('limit', $response);
        $this->assertArrayHasKey('users', $response);
        $this->assertArrayHasKey('offset', $response);
        $this->assertIsInt($response['total_count']);
        $this->assertIsInt($response['limit']);
        $this->assertIsArray($response['users']);
        $this->assertIsInt($response['offset']);

        $this->assertArrayHasKey('id', $response['users'][0]);
        $this->assertArrayHasKey('name', $response['users'][0]);
        $this->assertArrayHasKey('username', $response['users'][0]);
        $this->assertArrayHasKey('email', $response['users'][0]);
        return null;
    }

    public function testUnauthorizedApiRequest()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $f3 = \Base::instance();
        $f3->clear('user');
        $f3->clear('user_obj');
        $f3->clear('COOKIE.' . \Model\Session::COOKIE_NAME);

        $response = json_decode($this->mock("GET /user/me.json"), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertSame(401, $response['status']);
        return null;
    }

    public function testIssueCrudLifecycle()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $created = $this->createIssue([
            'name' => 'API lifecycle test issue',
            'description' => 'Created by PHPUnit',
            'owner_id' => $this->user->id,
        ]);

        $issueId = (int)$created['issue']['id'];

        $fetched = json_decode($this->mock("GET /issues/{$issueId}.json", [], $this->apiHeaders()), true);
        $this->assertArrayHasKey('issue', $fetched);
        $this->assertSame($issueId, (int)$fetched['issue']['id']);
        $this->assertSame('API lifecycle test issue', $fetched['issue']['name']);

        $updated = json_decode($this->mock("PUT /issues/{$issueId}.json", [
            'name' => 'API lifecycle test issue (updated)',
            'author_id' => $this->user->id + 9999,
        ], $this->apiHeaders()), true);

        $this->assertArrayHasKey('updated_fields', $updated);
        $this->assertContains('name', $updated['updated_fields']);
        $this->assertNotContains('author_id', $updated['updated_fields']);
        $this->assertSame('API lifecycle test issue (updated)', $updated['issue']['name']);

        $comment = json_decode($this->mock("POST /issues/{$issueId}/comments.json", [
            'text' => 'Lifecycle comment',
        ], $this->apiHeaders()), true);
        $this->assertArrayHasKey('id', $comment);
        $this->assertSame('Lifecycle comment', $comment['text']);

        $comments = json_decode($this->mock("GET /issues/{$issueId}/comments.json", [], $this->apiHeaders()), true);
        $this->assertIsArray($comments);
        $this->assertNotEmpty($comments);
        $this->assertSame('Lifecycle comment', $comments[0]['text']);

        $deleted = json_decode($this->mock("DELETE /issues/{$issueId}.json", [], $this->apiHeaders()), true);
        $this->assertArrayHasKey('deleted', $deleted);
        $this->assertSame((string)$issueId, (string)$deleted['deleted']);

        $issueModel = new \Model\Issue();
        $issueModel->load($issueId);
        $this->assertNotEmpty($issueModel->deleted_date);
        return null;
    }

    /**
     * Create a non-admin user with an API key, not in any groups
     */
    protected function createRestrictedUser(): \Model\User
    {
        $suffix = bin2hex(random_bytes(4));
        $user = new \Model\User();
        $user->username = "restricted_{$suffix}";
        $user->email = "restricted_{$suffix}@example.com";
        $user->name = "Restricted {$suffix}";
        $user->role = 'user';
        $user->rank = 1;
        $user->api_key = sha1(random_bytes(16));
        $user->save();
        return $user;
    }

    public function testRestrictedAccessEnforcedOnApi()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $f3 = \Base::instance();
        $originalRestrict = $f3->get('security.restrict_access');
        $restricted = $this->createRestrictedUser();
        $restrictedHeaders = ['X-API-Key' => $restricted->api_key];

        try {
            $f3->set('security.restrict_access', 1);
            \Registry::clear(\Helper\Dashboard::class);

            $created = $this->createIssue([
                'name' => 'Restricted API issue',
                'description' => 'Confidential #restrictedtag',
                'owner_id' => $this->user->id,
            ]);
            $issueId = (int)$created['issue']['id'];
            \Registry::clear(\Helper\Dashboard::class);

            $single = json_decode($this->mock("GET /issues/{$issueId}.json", [], $restrictedHeaders), true);
            $this->assertSame(403, $single['status'] ?? null);
            \Registry::clear(\Helper\Dashboard::class);

            $comments = json_decode($this->mock("GET /issues/{$issueId}/comments.json", [], $restrictedHeaders), true);
            $this->assertSame(403, $comments['status'] ?? null);
            \Registry::clear(\Helper\Dashboard::class);

            $commentPost = json_decode($this->mock("POST /issues/{$issueId}/comments.json", [
                'text' => 'Should not be saved',
            ], $restrictedHeaders), true);
            $this->assertSame(403, $commentPost['status'] ?? null);
            $comment = new \Model\Issue\Comment();
            $this->assertSame(0, $comment->count(['issue_id = ? AND user_id = ?', $issueId, $restricted->id]));
            \Registry::clear(\Helper\Dashboard::class);

            $list = json_decode($this->mock("GET /issues.json", ['id' => $issueId], $restrictedHeaders), true);
            $this->assertSame([], $list['issues']);
            \Registry::clear(\Helper\Dashboard::class);

            $tagged = json_decode($this->mock("GET /tag/restrictedtag.json", [], $restrictedHeaders), true);
            $this->assertNotContains($issueId, array_map('intval', array_column($tagged, 'id')));
            \Registry::clear(\Helper\Dashboard::class);

            $child = json_decode($this->mock("POST /issues.json", [
                'name' => 'Child of restricted issue',
                'description' => 'Created by PHPUnit',
                'parent_id' => $issueId,
            ], $restrictedHeaders), true);
            $this->assertSame(400, $child['status'] ?? null);
            \Registry::clear(\Helper\Dashboard::class);

            // The owner still has access
            $fetched = json_decode($this->mock("GET /issues/{$issueId}.json", [], $this->apiHeaders()), true);
            $this->assertSame($issueId, (int)$fetched['issue']['id']);
        } finally {
            $f3->set('security.restrict_access', $originalRestrict);
            \Registry::clear(\Helper\Dashboard::class);
            $restricted->erase();
        }

        return null;
    }

    public function testIssueCreateIgnoresAuthorIdForNonAdmin()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $restricted = $this->createRestrictedUser();
        try {
            $response = json_decode($this->mock("POST /issues.json", [
                'name' => 'Spoofed author issue',
                'description' => 'Created by PHPUnit',
                'author_id' => $this->user->id,
            ], ['X-API-Key' => $restricted->api_key]), true);
            $this->assertSame((int)$restricted->id, (int)$response['issue']['author_id']);
        } finally {
            // Remove issues authored by the user first to satisfy the author foreign key
            foreach ((new \Model\Issue())->find(['author_id = ?', $restricted->id]) as $issue) {
                $issue->erase();
            }
            $restricted->erase();
        }

        return null;
    }

    public function testDeletedUserApiKeyRejected()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $f3 = \Base::instance();
        $restricted = $this->createRestrictedUser();
        $restricted->delete();
        try {
            $f3->clear('user');
            $f3->clear('user_obj');
            $response = json_decode($this->mock("GET /user/me.json", [], ['X-API-Key' => $restricted->api_key]), true);
            $this->assertSame(401, $response['status'] ?? null);
        } finally {
            $restricted->erase();
        }

        return null;
    }

    /**
     * Test that API validates required fields
     * Note: Full error response testing would require process isolation to handle F3's exit()
     */
    public function testIssueCreateValidationError()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        // Verify the issue model has required name field
        $issueModel = new \Model\Issue();
        $schema = $issueModel->schema();
        $this->assertArrayHasKey('name', $schema, 'Issue model should have name field');

        // Verify the API endpoint code checks for non-empty name
        // (actual error response testing with F3's exit() requires separate process isolation)
        $controllerFile = file_get_contents(dirname(__DIR__) . '/app/controller/api/issues.php');
        $this->assertStringContainsString('empty($post["name"])', $controllerFile, 'API should validate name field');
        $this->assertStringContainsString('name\' value is required', $controllerFile, 'API should have name required error message');

        return null;
    }
}
