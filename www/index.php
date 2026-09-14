<?php
$db_host = '192.168.56.13';
$db_name = 'recipefinder';
$db_user = 'recipeapp';
$db_password = 'recipe_demo_pw';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$pdo = new PDO($dsn, $db_user, $db_password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Load checkbox choices from the database on every request.
$ingredients = $pdo->query(
    "SELECT DISTINCT ingredient
     FROM recipe_ingredients
     ORDER BY ingredient"
)->fetchAll(PDO::FETCH_COLUMN);

// Accept only ingredient names that exist in our database.
$submitted = $_GET['ingredients'] ?? [];
if (!is_array($submitted)) {
    $submitted = [];
}

$selected = [];
foreach ($ingredients as $ingredient) {
    if (in_array($ingredient, $submitted, true)) {
        $selected[] = $ingredient;
    }
}

$searched = isset($_GET['search']);
$mode = ($_GET['mode'] ?? 'or') === 'and'
    ? 'and'
    : 'or';
$recipes = [];

if ($searched && count($selected) > 0) {
    // One placeholder for each selected ingredient.
    $placeholders = implode(',', array_fill(0, count($selected), '?'));

if ($mode === 'and') {
    $sql = "
        SELECT r.id, r.name, r.instructions
        FROM recipes r
        WHERE EXISTS (
            SELECT 1
            FROM recipe_ingredients ri
            WHERE ri.recipe_id = r.id
              AND ri.ingredient IN ($placeholders)
        )
        ORDER BY r.name
    ";
} else {
    $sql = "
        SELECT r.id, r.name, r.instructions
        FROM recipes r
        WHERE EXISTS (
            SELECT 1
            FROM recipe_ingredients ri
            WHERE ri.recipe_id = r.id
        )
        AND NOT EXISTS (
            SELECT 1
            FROM recipe_ingredients ri
            WHERE ri.recipe_id = r.id
              AND ri.ingredient NOT IN ($placeholders)
        )
        ORDER BY r.name
    ";
}

    $query = $pdo->prepare($sql);
    $query->execute($selected);
    $recipes = $query->fetchAll(PDO::FETCH_ASSOC);
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
<p>Select the ingredients you have. Water is assumed available.</p>

<form method="get" action="/">
    <label for="mode">Search mode:</label>
    <select name="mode" id="mode">
        <option value="or"
            <?= $mode === 'or' ? 'selected' : '' ?>>
            AND
        </option>
        <option value="and"
            <?= $mode === 'and' ? 'selected' : '' ?>>
            OR
        </option>
    </select>
    <p></p>
    <?php foreach ($ingredients as $ingredient): ?>
        <label>
            <input
                type="checkbox"
                name="ingredients[]"
                value="<?= escape($ingredient) ?>"
                <?= in_array($ingredient, $selected, true) ? 'checked' : '' ?>
            >
            <?= escape($ingredient) ?>
        </label>
        <br>
    <?php endforeach; ?>

    <button type="submit" name="search" value="1">Find recipes</button>
</form>

<?php if ($searched): ?>
    <h2>Matching recipes</h2>

    <?php if (count($selected) === 0): ?>
        <p>Please select at least one ingredient.</p>
    <?php elseif (count($recipes) === 0): ?>
        <p>No recipes match your available ingredients.</p>
    <?php else: ?>
        <?php foreach ($recipes as $recipe): ?>
            <article>
                <h3><?= escape($recipe['name']) ?></h3>
                <p><?= escape($recipe['instructions']) ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
