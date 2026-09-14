<?php
$pdo = new PDO(
    'mysql:host=192.168.56.13;dbname=recipefinder;charset=utf8mb4',
    'recipeapp',
    'recipe_demo_pw',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$recipes = $pdo->query(
    'SELECT id, name, instructions FROM recipes ORDER BY id'
)->fetchAll();

function escape($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recipe Finder</title>
</head>
<body>
    <h1>Recipe Finder</h1>
    <p>Recipes loaded from our database:</p>

    <?php foreach ($recipes as $recipe): ?>
        <article>
            <h2><?= escape($recipe['name']) ?></h2>
            <p><?= escape($recipe['instructions']) ?></p>
        </article>
    <?php endforeach; ?>
</body>
</html>
