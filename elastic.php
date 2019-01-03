<?php
/**
 * Elasticsearch importer and test client
 */

require_once 'vendor/autoload.php';

$f3 = Base::instance();
$f3->mset(array(
    'UI' => 'app/view/',
    'LOGS' => 'log/',
    'AUTOLOAD' => 'app/;lib/vendor/',
    'TEMP' => 'tmp/',
    'TZ' => 'UTC',
));

// Load local configuration
$f3->mset(require_once('config.php'));

// Connect to database
$f3->set('db.instance', new DB\SQL(
    'mysql:host=' . $f3->get('db.host') . ';port=3306;dbname=' . $f3->get('db.name'),
    $f3->get('db.user'),
    $f3->get('db.pass')
));

// Load database-backed config
Model\Config::loadAll();

$client = Elasticsearch\ClientBuilder::create()->build();

// Import all issues
if (isset($_GET['import'])) {
    $issue = new Model\Issue\Detail;
    $issues = $issue->find(["deleted_date IS NULL"]);
    foreach ($issues as $issue) {
        $result = $client->index([
            'index' => 'issues',
            'type' => 'issue',
            'id' => $issue->id,
            'body' => [
                'name' => $issue->name,
                'description' => $issue->description,
                'type' => $issue->type_name,
                'owner' => $issue->owner_name,
                'author' => $issue->author_name,
            ],
        ]);
        echo "<pre>" , json_encode($result, JSON_PRETTY_PRINT), "</pre>";
    }
}

// Drop issue index
if (isset($_GET['drop'])) {
    $result = $client->indices()->delete([
        'index' => 'issues'
    ]);
    echo "<pre>" , json_encode($result, JSON_PRETTY_PRINT), "</pre>";
}

// Get a single issue
if (isset($_GET['id'])) {
    $result = $client->get([
        'index' => 'issues',
        'type' => (isset($_GET['type']) ? $_GET['type'] : 'issue'),
        'id' => $_GET['id']
    ]);
    echo "<pre>" , json_encode($result, JSON_PRETTY_PRINT), "</pre>";
}

// Search all indices for the query string
if (isset($_GET['q'])) {
    $result = $client->search([
        'index' => (isset($_GET['index']) ? $_GET['index'] : '_all'),
        'type' => (isset($_GET['type']) ? $_GET['type'] : null),
        'body' => [
            'query' => [
                'match' => [
                    '_all' => $_GET['q']
                ]
            ]
        ]
    ]);
    echo "<pre>" , json_encode($result, JSON_PRETTY_PRINT), "</pre>";
}

// List all documents
if (isset($_GET['list'])) {
    $result = $client->search([
        'index' => '_all', // 'issues'
        // 'type' => 'issue',
        'body' => [
            'query' => [
                'match_all' => []
            ]
        ]
    ]);
    echo "<pre>" , json_encode($result, JSON_PRETTY_PRINT), "</pre>";
}
