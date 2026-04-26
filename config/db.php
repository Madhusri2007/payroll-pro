 <?php
if (!extension_loaded('mongodb')) {
    die('MongoDB extension is not loaded!');
}

$mongoUri = getenv('MONGO_URI') ?: 'mongodb://localhost:27017';
$manager  = new MongoDB\Driver\Manager($mongoUri);

$dbName              = "payroll_db";
$employeesCollection = "employees";
$usersCollection     = "users";
$payrollCollection   = "payroll";

function insertDoc($manager, $dbName, $collection, $data) {
    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->insert($data);
    return $manager->executeBulkWrite("$dbName.$collection", $bulk);
}

function findDocs($manager, $dbName, $collection, $filter = [], $options = []) {
    $query  = new MongoDB\Driver\Query($filter, $options);
    $cursor = $manager->executeQuery("$dbName.$collection", $query);
    return $cursor->toArray();
}

function findOne($manager, $dbName, $collection, $filter) {
    $query   = new MongoDB\Driver\Query($filter, ['limit' => 1]);
    $cursor  = $manager->executeQuery("$dbName.$collection", $query);
    $results = $cursor->toArray();
    return count($results) > 0 ? $results[0] : null;
}

function updateDoc($manager, $dbName, $collection, $filter, $update) {
    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->update($filter, ['$set' => $update]);
    return $manager->executeBulkWrite("$dbName.$collection", $bulk);
}

function deleteDoc($manager, $dbName, $collection, $filter) {
    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->delete($filter, ['limit' => 1]);
    return $manager->executeBulkWrite("$dbName.$collection", $bulk);
}