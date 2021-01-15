<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    /** @var \Model\User */
    protected $user;
    protected $configured = false;

    public function setUp(): void
    {
        $f3 = \Base::instance();
        if ($this->configured) {
            $f3->set('ERROR', null);
            return;
        }

        // Configure framework
        $config = include('config.php');
        if (!$config) {
            return;
        }
        $f3->mset($config);

        // Load routes
        $f3->config(dirname(__DIR__) . "/app/routes.ini");

        // Configure databsae connection
        $f3->set("db.instance", new DB\SQL(
            "mysql:host=" . $f3->get("db.host") . ";port=" . $f3->get("db.port") . ";dbname=" . $f3->get("db.name"),
            $f3->get("db.user"),
            $f3->get("db.pass"),
            [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4;']
        ));

        // Load final configuration
        \Model\Config::loadAll();

        // Load test user
        $this->user = (new \Model\User())->load(['deleted_date IS NULL AND api_key IS NOT NULL']);

        $this->configured = true;
    }

    /**
     * Mock an HTTP request, returning the response as a string.
     *
     * @return string|false
     */
    protected function mock(string $route, ?array $args = null, ?array $headers = null)
    {
        ob_start();
        $f3 = \Base::instance();
        $f3->mock($route, $args, $headers);
        $f3->clear('ERROR');
        return ob_get_clean();
    }

    public function testSingleUser()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $response = json_decode($this->mock("GET /user/me.json", [], [
            'X-API-Key' => $this->user->api_key,
        ]), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertEquals($this->user->id, $response['id']);
        $this->assertEquals($this->user->name, $response['name']);
        $this->assertEquals($this->user->username, $response['username']);
        $this->assertEquals($this->user->email, $response['email']);
    }

    public function testSingleUserEmail()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $response = json_decode($this->mock("GET /useremail/{$this->user->email}.json", [], [
            'X-API-Key' => $this->user->api_key,
        ]), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertEquals($this->user->id, $response['id']);
        $this->assertEquals($this->user->name, $response['name']);
        $this->assertEquals($this->user->username, $response['username']);
        $this->assertEquals($this->user->email, $response['email']);
    }

    public function testUserList()
    {
        if (!$this->configured) {
            return $this->markTestSkipped();
        }

        $response = json_decode($this->mock("GET /user.json", [], [
            'X-API-Key' => $this->user->api_key,
        ]), true);

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
    }
}
