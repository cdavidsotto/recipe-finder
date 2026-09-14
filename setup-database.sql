CREATE TABLE IF NOT EXISTS recipes (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    instructions TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    recipe_id INT NOT NULL,
    ingredient VARCHAR(50) NOT NULL,
    PRIMARY KEY (recipe_id, ingredient),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id)
);

INSERT INTO recipes (id, name, instructions) VALUES
(1, 'Scrambled eggs',
 'Beat eggs. Melt butter in a pan, add eggs and stir until set.'),
(2, 'Tomato pasta',
 'Cook pasta. Heat tomatoes with oil and garlic, then combine.'),
(3, 'Ban Mian',
 'Cook noodles. Mix peanut butter and soy sauce with a little hot water, then toss with noodles.'),
(4, 'Mushroom rice',
 'Cook rice. Fry mushrooms and onion in oil, then mix with the rice.'),
(5, 'Banana porridge',
 'Cook oats in milk, stirring until thick. Top with sliced banana.'),
(6, 'Lentil tomato soup',
 'Simmer lentils, tomatoes and onion in water until the lentils are tender.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    instructions = VALUES(instructions);

INSERT INTO recipe_ingredients (recipe_id, ingredient) VALUES
(1, 'eggs'), (1, 'butter'),
(2, 'pasta'), (2, 'tomatoes'), (2, 'oil'), (2, 'garlic'),
(3, 'noodles'), (3, 'peanut butter'), (3, 'soy sauce'),
(4, 'rice'), (4, 'mushrooms'), (4, 'onion'), (4, 'oil'),
(5, 'oats'), (5, 'milk'), (5, 'banana'),
(6, 'lentils'), (6, 'tomatoes'), (6, 'onion')
ON DUPLICATE KEY UPDATE
    ingredient = VALUES(ingredient);
