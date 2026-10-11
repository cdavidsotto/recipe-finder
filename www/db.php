<?php
// Shared setup for every page: database connection and helpers.
// Connection details are written to /etc/recipe-finder/config.ini by the
// app server's startup script (tf-deploy/build-appserver-vm.tpl), so no
// password is stored in this repository.

$config = parse_ini_file('/etc/recipe-finder/config.ini', true);
if ($config === false) {
    http_response_code(500);
    exit('Configuration file missing.');
}

$db = $config['database'];
$dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
$pdo = new PDO($dsn, $db['user'], $db['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function page_header($title) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= escape($title) ?> - Recipe Finder</title>
</head>
<body>
<nav>
    <a href="/">Find recipes</a> |
    <a href="/add_recipe.php">Add a recipe</a> |
    <a href="/lists.php">Shopping lists</a>
</nav>
<h1><?= escape($title) ?></h1>
    <?php
}

function page_footer() {
    ?>
</body>
</html>
    <?php
}
