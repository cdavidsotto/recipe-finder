<?php
require __DIR__ . '/db.php';
require __DIR__ . '/lambda.php';

$recipes = $pdo->query(
    "SELECT id, name FROM recipes ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

$ingredients = $pdo->query(
    "SELECT DISTINCT ingredient FROM recipe_ingredients ORDER BY ingredient"
)->fetchAll(PDO::FETCH_COLUMN);

$errors = [];
$name = trim($_POST['name'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept only recipe IDs and ingredients that exist in the database.
    $recipe_ids = array_map('intval', (array) ($_POST['recipes'] ?? []));
    $valid_ids = array_map('intval', array_column($recipes, 'id'));
    $recipe_ids = array_values(array_intersect($recipe_ids, $valid_ids));

    $have = array_values(array_intersect(
        (array) ($_POST['have'] ?? []),
        $ingredients
    ));

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Please name the list (up to 100 characters).';
    }
    if (count($recipe_ids) === 0) {
        $errors[] = 'Please choose at least one recipe.';
    }

    if (count($errors) === 0) {
        $pdo->beginTransaction();

        $pdo->prepare("INSERT INTO shopping_lists (name) VALUES (?)")
            ->execute([$name]);
        $list_id = (int) $pdo->lastInsertId();

        $query = $pdo->prepare(
            "INSERT INTO shopping_list_recipes (list_id, recipe_id) VALUES (?, ?)"
        );
        foreach ($recipe_ids as $recipe_id) {
            $query->execute([$list_id, $recipe_id]);
        }

        $query = $pdo->prepare(
            "INSERT INTO shopping_list_have (list_id, ingredient) VALUES (?, ?)"
        );
        foreach ($have as $item) {
            $query->execute([$list_id, $item]);
        }

        $pdo->commit();

        // The list is saved even if Lambda fails; it can be regenerated.
        $ok = regenerate_list($pdo, $config, $list_id);
        header('Location: /list.php?id=' . $list_id . ($ok ? '' : '&failed=1'));
        exit;
    }
}

$saved_lists = $pdo->query(
    "SELECT id, name, updated_at FROM shopping_lists ORDER BY updated_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

page_header('Shopping lists');
?>

<h2>Saved lists</h2>
<?php if (count($saved_lists) === 0): ?>
    <p>No saved lists yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($saved_lists as $list): ?>
            <li>
                <a href="/list.php?id=<?= (int) $list['id'] ?>"><?= escape($list['name']) ?></a>
                (updated <?= escape($list['updated_at']) ?> UTC)
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Make a new list</h2>

<?php foreach ($errors as $error): ?>
    <p><strong><?= escape($error) ?></strong></p>
<?php endforeach; ?>

<form method="post" action="/lists.php">
    <p>
        <label for="name">List name:</label>
        <input type="text" name="name" id="name" maxlength="100"
            value="<?= escape($name) ?>">
    </p>

    <h3>Recipes to cook</h3>
    <?php foreach ($recipes as $recipe): ?>
        <label>
            <input type="checkbox" name="recipes[]" value="<?= (int) $recipe['id'] ?>">
            <?= escape($recipe['name']) ?>
        </label>
        <br>
    <?php endforeach; ?>

    <h3>Ingredients I already have</h3>
    <?php foreach ($ingredients as $ingredient): ?>
        <label>
            <input type="checkbox" name="have[]" value="<?= escape($ingredient) ?>">
            <?= escape($ingredient) ?>
        </label>
        <br>
    <?php endforeach; ?>

    <p><button type="submit">Build and save list</button></p>
</form>

<?php page_footer(); ?>
