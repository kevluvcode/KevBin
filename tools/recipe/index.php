<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online recipe generator with 15+ templates. Create, edit and scale recipes with live preview, print support, and export to Markdown or JSON. All processing is client-side.',
    'keywords' => 'recipe generator, recipe card, meal planner, cooking, recipe template, recipe scale',
];
page_header('Recipe Generator — Create, Scale & Print Recipes');
?>
<style>
    .recipe-preview{background:var(--bs-body-bg,#1a1a2e);border:1px solid var(--line);border-radius:12px;padding:1.5rem;margin-top:1rem;}
    .recipe-preview h2{font-size:1.4rem;font-weight:700;margin-bottom:.75rem;}
    .badge-time{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .6rem;border-radius:8px;font-size:.78rem;font-weight:600;margin-right:.4rem;margin-bottom:.4rem;}
    .badge-prep{background:rgba(59,130,246,.18);color:#60a5fa;}
    .badge-cook{background:rgba(249,115,22,.18);color:#fb923c;}
    .badge-serv{background:rgba(16,185,129,.18);color:#34d399;}
    .badge-tag{display:inline-flex;padding:.25rem .55rem;border-radius:6px;font-size:.72rem;font-weight:600;margin-right:.3rem;margin-bottom:.3rem;}
    .badge-vegetarian{background:rgba(34,197,94,.18);color:#4ade80;}
    .badge-vegan{background:rgba(132,204,22,.18);color:#a3e635;}
    .badge-glutenfree{background:rgba(251,191,36,.18);color:#fbbf24;}
    .badge-dairyfree{background:rgba(56,189,248,.18);color:#38bdf8;}
    .badge-quick{background:rgba(168,85,247,.18);color:#c084fc;}
    .badge-easy{background:rgba(236,72,153,.18);color:#f472b6;}
    .ingredient-check{display:flex;align-items:center;gap:.5rem;padding:.3rem 0;border-bottom:1px solid var(--line,#2a2a3e);}
    .ingredient-check:last-child{border-bottom:none;}
    .ingredient-check input[type=checkbox]{accent-color:#34d399;width:16px;height:16px;}
    .instruction-step{display:flex;gap:.6rem;padding:.5rem 0;border-bottom:1px solid var(--line,#2a2a3e);}
    .instruction-step:last-child{border-bottom:none;}
    .step-num{min-width:24px;height:24px;border-radius:50%;background:rgba(99,102,241,.2);color:#818cf8;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;}
    .notes-callout{background:rgba(251,191,36,.08);border-left:3px solid #fbbf24;border-radius:0 8px 8px 0;padding:.75rem 1rem;margin-top:.75rem;font-size:.88rem;color:#e2e8f0;}
    .ing-row{display:flex;gap:.4rem;align-items:center;margin-bottom:.4rem;}
    .ing-row input,.ing-row select{font-size:.85rem;}
    .step-row{display:flex;gap:.4rem;align-items:flex-start;margin-bottom:.4rem;}
    .step-row textarea{font-size:.85rem;resize:vertical;}
    .servings-control{display:inline-flex;align-items:center;gap:.3rem;}
    .servings-control button{width:28px;height:28px;border-radius:50%;border:1px solid var(--line,#333);background:transparent;color:#e2e8f0;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .servings-control button:hover{background:rgba(99,102,241,.2);}
    .servings-control span{min-width:28px;text-align:center;font-weight:600;font-size:.95rem;}
    #recipe-card{color:#e2e8f0;}
    #recipe-card .form-label{font-size:.82rem;color:#94a3b8;margin-bottom:.2rem;}
    @media print{
        body *{visibility:hidden!important;}
        #recipe-card,#recipe-card *{visibility:visible!important;}
        #recipe-card{position:absolute;left:0;top:0;width:100%;padding:1.5rem;background:#fff!important;color:#1a1a2e!important;border:none!important;}
        #recipe-card h2{color:#1a1a2e!important;}
        #recipe-card .badge-prep,#recipe-card .badge-cook,#recipe-card .badge-serv{background:#e2e8f0!important;color:#333!important;}
        #recipe-card .badge-tag{background:#e2e8f0!important;color:#333!important;}
        #recipe-card .notes-callout{background:#f5f5f5!important;color:#333!important;border-color:#999!important;}
        #recipe-card .ingredient-check input[type=checkbox]{display:none;}
        #recipe-card .step-num{background:#e2e8f0!important;color:#333!important;}
    }
</style>

<div class="container" style="max-width:1200px;">
    <h1 class="h4 mb-2 reveal in-view">Recipe Generator</h1>
    <p class="text-secondary mb-1 reveal in-view">Create, edit and scale recipes with a live preview. Choose a template to start, adjust servings and watch amounts auto-scale. Print, copy as Markdown or JSON, or save to your browser.</p>
    <p class="text-secondary mb-4 reveal in-view">Everything runs in your browser &mdash; no data is ever uploaded.</p>

    <div class="row g-4">
        <div class="col-lg-6 reveal in-view">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h6 mb-0">Edit Recipe</h2>
                    <select id="tpl-select" class="form-select form-select-sm" style="max-width:260px;" onchange="loadTemplate(this.value)">
                        <option value="">-- Choose a Template --</option>
                        <optgroup label="Breakfast">
                            <option value="pancakes">Classic Pancakes</option>
                            <option value="scrambled">Scrambled Eggs</option>
                            <option value="frenchtoast">French Toast</option>
                        </optgroup>
                        <optgroup label="Lunch">
                            <option value="caesar">Classic Caesar Salad</option>
                            <option value="grilledcheese">Grilled Cheese Sandwich</option>
                            <option value="stirfry">Chicken Stir Fry</option>
                        </optgroup>
                        <optgroup label="Dinner">
                            <option value="bolognese">Spaghetti Bolognese</option>
                            <option value="alfredo">Chicken Alfredo</option>
                            <option value="tacos">Taco Night</option>
                            <option value="pulledpork">BBQ Pulled Pork</option>
                        </optgroup>
                        <optgroup label="Dessert">
                            <option value="cookies">Chocolate Chip Cookies</option>
                            <option value="bananabread">Banana Bread</option>
                            <option value="brownies">Brownies</option>
                        </optgroup>
                        <optgroup label="Healthy">
                            <option value="greeksalad">Greek Salad Bowl</option>
                            <option value="oats">Overnight Oats</option>
                        </optgroup>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Recipe Name</label>
                    <input id="r-name" class="form-control" placeholder="My Recipe" oninput="renderPreview()">
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label">Servings</label>
                        <div class="servings-control">
                            <button onclick="adjServ(-1)">&minus;</button>
                            <span id="r-serv-num">4</span>
                            <button onclick="adjServ(1)">+</button>
                        </div>
                        <input type="hidden" id="r-serv" value="4">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Prep (min)</label>
                        <input id="r-prep" type="number" class="form-control form-control-sm" value="10" min="0" oninput="renderPreview()">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Cook (min)</label>
                        <input id="r-cook" type="number" class="form-control form-control-sm" value="20" min="0" oninput="renderPreview()">
                    </div>
                </div>

                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <label class="form-label mb-0">Ingredients</label>
                        <button class="btn btn-outline-light btn-sm" style="font-size:.75rem;" onclick="addIng()">+ Add</button>
                    </div>
                    <div id="ing-list"></div>
                </div>

                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <label class="form-label mb-0">Instructions</label>
                        <button class="btn btn-outline-light btn-sm" style="font-size:.75rem;" onclick="addStep()">+ Add</button>
                    </div>
                    <div id="step-list"></div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Notes</label>
                    <textarea id="r-notes" class="form-control" rows="2" placeholder="Optional tips or notes..." oninput="renderPreview()"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tags</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="tag-vegetarian" onchange="renderPreview()"><label class="form-check-label small" for="tag-vegetarian">Vegetarian</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="tag-vegan" onchange="renderPreview()"><label class="form-check-label small" for="tag-vegan">Vegan</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="tag-glutenfree" onchange="renderPreview()"><label class="form-check-label small" for="tag-glutenfree">Gluten-Free</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="tag-dairyfree" onchange="renderPreview()"><label class="form-check-label small" for="tag-dairyfree">Dairy-Free</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="tag-quick" onchange="renderPreview()"><label class="form-check-label small" for="tag-quick">Quick</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="tag-easy" onchange="renderPreview()"><label class="form-check-label small" for="tag-easy">Easy</label></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-light" onclick="saveRecipe()">Save</button>
                    <button class="btn btn-sm btn-outline-light" onclick="loadRecipe()">Load</button>
                    <button class="btn btn-sm btn-outline-light" onclick="clearAll()">Clear</button>
                </div>
            </div></div>
        </div>

        <div class="col-lg-6 reveal in-view">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 mb-0">Preview</h2>
                    <div class="d-flex flex-wrap gap-1">
                        <button class="btn btn-primary btn-sm" onclick="window.print()">Print</button>
                        <button class="btn btn-outline-light btn-sm" onclick="copyMarkdown()">Copy MD</button>
                        <button class="btn btn-outline-light btn-sm" onclick="copyJSON()">Copy JSON</button>
                    </div>
                </div>
                <div class="recipe-preview" id="recipe-card">
                    <p class="text-secondary small mb-0">Select a template or start filling in the form.</p>
                </div>
            </div></div>
        </div>
    </div>
</div>

<script>
var $ = function(id){ return document.getElementById(id); };

var BASE_SERVINGS = {};
var templates = {
    pancakes: {
        name: "Classic Pancakes",
        baseServ: 4,
        prep: 10, cook: 15,
        ings: [
            {n:"All-purpose flour",a:1.5,u:"cups"},
            {n:"Eggs",a:2,u:"pcs"},
            {n:"Milk",a:1.25,u:"cups"},
            {n:"Butter (melted)",a:3,u:"tbsp"},
            {n:"Sugar",a:2,u:"tbsp"},
            {n:"Baking powder",a:2,u:"tsp"},
            {n:"Salt",a:0.5,u:"tsp"},
            {n:"Vanilla extract",a:1,u:"tsp"}
        ],
        steps: [
            "In a large bowl, whisk together flour, sugar, baking powder, and salt.",
            "Make a well in the center and pour in milk, eggs, melted butter, and vanilla. Mix until smooth.",
            "Heat a lightly oiled griddle or frying pan over medium-high heat.",
            "Pour batter onto the griddle, using about 1/4 cup for each pancake. Cook until bubbles form, then flip and cook until browned.",
            "Serve hot with maple syrup and butter."
        ],
        notes: "For fluffier pancakes, don't overmix the batter — lumps are okay. Let the batter rest 5 minutes before cooking.",
        tags: ["easy"]
    },
    scrambled: {
        name: "Scrambled Eggs",
        baseServ: 2,
        prep: 5, cook: 5,
        ings: [
            {n:"Eggs",a:6,u:"pcs"},
            {n:"Butter",a:1,u:"tbsp"},
            {n:"Salt",a:0.25,u:"tsp"},
            {n:"Black pepper",a:0.25,u:"tsp"},
            {n:"Fresh chives",a:1,u:"tbsp"}
        ],
        steps: [
            "Crack eggs into a bowl, add salt and pepper, and whisk until well combined.",
            "Melt butter in a non-stick skillet over medium-low heat.",
            "Pour in eggs and gently stir with a spatula, pushing curds from the edges to the center.",
            "Cook until just set but still slightly creamy — about 3 minutes. Remove from heat.",
            "Garnish with chopped chives and serve immediately."
        ],
        notes: "Low and slow is the key to creamy scrambled eggs. Remove from heat slightly before they look done — they'll continue cooking on the plate.",
        tags: ["quick","easy","vegetarian"]
    },
    frenchtoast: {
        name: "French Toast",
        baseServ: 4,
        prep: 10, cook: 10,
        ings: [
            {n:"Bread (thick slices)",a:8,u:"pcs"},
            {n:"Eggs",a:3,u:"pcs"},
            {n:"Milk",a:0.5,u:"cups"},
            {n:"Cinnamon",a:1,u:"tsp"},
            {n:"Vanilla extract",a:1,u:"tsp"},
            {n:"Maple syrup",a:0.25,u:"cups"}
        ],
        steps: [
            "In a shallow dish, whisk together eggs, milk, cinnamon, and vanilla.",
            "Heat a griddle or skillet over medium heat and lightly grease with butter.",
            "Dip each slice of bread into the egg mixture, coating both sides well.",
            "Cook each slice for 2-3 minutes per side until golden brown.",
            "Serve warm with maple syrup, fresh fruit, or powdered sugar."
        ],
        notes: "Day-old bread works best because it absorbs the custard without falling apart. Use thick-cut bread like brioche or challah for best results.",
        tags: ["easy","vegetarian"]
    },
    caesar: {
        name: "Classic Caesar Salad",
        baseServ: 4,
        prep: 15, cook: 0,
        ings: [
            {n:"Romaine lettuce",a:2,u:"heads"},
            {n:"Croutons",a:1,u:"cups"},
            {n:"Parmesan cheese",a:0.5,u:"cups"},
            {n:"Caesar dressing",a:0.33,u:"cups"},
            {n:"Lemon juice",a:1,u:"tbsp"}
        ],
        steps: [
            "Wash and dry romaine lettuce, then chop or tear into bite-sized pieces.",
            "Place lettuce in a large salad bowl.",
            "Drizzle with Caesar dressing and lemon juice, then toss to coat evenly.",
            "Top with croutons and shaved Parmesan cheese.",
            "Serve immediately with extra Parmesan on the side."
        ],
        notes: "For homemade croutons, cube day-old bread, toss with olive oil and garlic powder, and bake at 375°F for 10 minutes.",
        tags: ["easy","vegetarian"]
    },
    grilledcheese: {
        name: "Grilled Cheese Sandwich",
        baseServ: 2,
        prep: 5, cook: 8,
        ings: [
            {n:"Bread slices",a:4,u:"pcs"},
            {n:"Cheddar cheese",a:4,u:"slices"},
            {n:"Butter",a:2,u:"tbsp"},
            {n:"Tomato soup (optional)",a:2,u:"cups"}
        ],
        steps: [
            "Butter one side of each bread slice.",
            "Place bread butter-side-down on a skillet, add cheese slices, then top with remaining bread butter-side-up.",
            "Cook over medium-low heat for 3-4 minutes until golden brown on the bottom.",
            "Flip carefully and cook another 3-4 minutes until the second side is golden and cheese is melted.",
            "Serve with warm tomato soup for dipping."
        ],
        notes: "Low and slow prevents burning while allowing the cheese to fully melt. Try adding a slice of tomato or a smear of mustard inside.",
        tags: ["easy","vegetarian"]
    },
    stirfry: {
        name: "Chicken Stir Fry",
        baseServ: 4,
        prep: 15, cook: 10,
        ings: [
            {n:"Chicken breast",a:1,u:"lbs"},
            {n:"Soy sauce",a:3,u:"tbsp"},
            {n:"Fresh ginger",a:1,u:"tbsp"},
            {n:"Garlic cloves",a:3,u:"pcs"},
            {n:"Bell peppers",a:2,u:"pcs"},
            {n:"Cooked rice",a:2,u:"cups"}
        ],
        steps: [
            "Slice chicken breast into thin strips and marinate in soy sauce for 10 minutes.",
            "Mince garlic and grate fresh ginger.",
            "Heat a wok or large skillet over high heat with a tablespoon of oil.",
            "Stir-fry chicken strips for 3-4 minutes until golden. Remove and set aside.",
            "Add sliced bell peppers and stir-fry for 2 minutes until crisp-tender.",
            "Return chicken to the wok, add ginger and garlic, stir for 30 seconds.",
            "Pour in remaining soy sauce, toss everything together, and serve over rice."
        ],
        notes: "Prep all ingredients before you start cooking — stir frying goes fast. Keep the heat high for that restaurant-style sear.",
        tags: ["dairyfree"]
    },
    bolognese: {
        name: "Spaghetti Bolognese",
        baseServ: 4,
        prep: 15, cook: 30,
        ings: [
            {n:"Ground beef",a:1,u:"lbs"},
            {n:"Onion",a:1,u:"pcs"},
            {n:"Garlic cloves",a:3,u:"pcs"},
            {n:"Crushed tomatoes",a:28,u:"oz"},
            {n:"Spaghetti",a:12,u:"oz"},
            {n:"Italian herbs",a:2,u:"tsp"}
        ],
        steps: [
            "Dice onion and mince garlic.",
            "Brown ground beef in a large pan over medium-high heat, breaking it into crumbles. Drain excess fat.",
            "Add onion and cook for 3 minutes until softened. Add garlic and cook 30 seconds.",
            "Pour in crushed tomatoes, add Italian herbs, salt, and pepper. Simmer for 20 minutes.",
            "Meanwhile, cook spaghetti according to package directions until al dente.",
            "Drain pasta and serve topped with the Bolognese sauce and grated Parmesan."
        ],
        notes: "For deeper flavor, let the sauce simmer longer (up to 45 min). Add a splash of red wine when cooking the meat.",
        tags: []
    },
    alfredo: {
        name: "Chicken Alfredo",
        baseServ: 4,
        prep: 10, cook: 20,
        ings: [
            {n:"Chicken breast",a:1,u:"lbs"},
            {n:"Fettuccine",a:12,u:"oz"},
            {n:"Butter",a:3,u:"tbsp"},
            {n:"Heavy cream",a:1,u:"cups"},
            {n:"Parmesan cheese",a:1,u:"cups"},
            {n:"Garlic cloves",a:2,u:"pcs"}
        ],
        steps: [
            "Season chicken breasts with salt, pepper, and Italian herbs.",
            "Cook chicken in a skillet over medium-high heat for 6-7 minutes per side until cooked through. Slice and set aside.",
            "Cook fettuccine in salted boiling water according to package directions. Reserve 1/2 cup pasta water before draining.",
            "In the same skillet, melt butter over medium heat. Add minced garlic and cook 30 seconds.",
            "Pour in heavy cream and bring to a gentle simmer. Cook for 3-4 minutes until slightly thickened.",
            "Remove from heat and stir in Parmesan cheese until smooth. Season with salt and pepper.",
            "Toss drained pasta in the sauce, adding reserved pasta water if needed. Top with sliced chicken."
        ],
        notes: "Always add Parmesan off the heat to prevent it from becoming grainy. Freshly grated Parmesan melts much better than pre-grated.",
        tags: []
    },
    tacos: {
        name: "Taco Night",
        baseServ: 6,
        prep: 15, cook: 15,
        ings: [
            {n:"Ground beef",a:1,u:"lbs"},
            {n:"Taco shells",a:12,u:"pcs"},
            {n:"Lettuce (shredded)",a:2,u:"cups"},
            {n:"Tomato (diced)",a:2,u:"pcs"},
            {n:"Shredded cheese",a:1,u:"cups"},
            {n:"Salsa",a:0.5,u:"cups"},
            {n:"Sour cream",a:0.5,u:"cups"}
        ],
        steps: [
            "Brown ground beef in a large skillet over medium-high heat, breaking into crumbles.",
            "Add taco seasoning and water per package directions. Simmer 5 minutes.",
            "Warm taco shells according to package instructions.",
            "Dice tomatoes and shred lettuce.",
            "Set up a taco bar: shells, meat, and all toppings in separate bowls.",
            "Let everyone build their own tacos and enjoy!"
        ],
        notes: "Set up a toppings bar with extra options like jalapeños, cilantro, lime wedges, diced onion, and guacamole for a fun build-your-own experience.",
        tags: ["easy"]
    },
    pulledpork: {
        name: "BBQ Pulled Pork",
        baseServ: 8,
        prep: 20, cook: 240,
        ings: [
            {n:"Pork shoulder",a:4,u:"lbs"},
            {n:"BBQ sauce",a:1,u:"cups"},
            {n:"Coleslaw",a:2,u:"cups"},
            {n:"Burger buns",a:8,u:"pcs"}
        ],
        steps: [
            "Season pork shoulder generously with salt, pepper, paprika, and garlic powder.",
            "Place in a slow cooker or roasting pan. Cook at 300°F for 4-5 hours (or low in slow cooker for 8 hours) until fork-tender.",
            "Remove pork and shred with two forks, discarding any large pieces of fat.",
            "Mix shredded pork with BBQ sauce in a bowl.",
            "Toast buns lightly, then pile high with pulled pork and top with coleslaw.",
            "Serve with extra BBQ sauce on the side."
        ],
        notes: "The longer you cook, the more tender it gets. For a smoky flavor, add a tablespoon of liquid smoke to the cooking liquid.",
        tags: ["dairyfree"]
    },
    cookies: {
        name: "Chocolate Chip Cookies",
        baseServ: 24,
        prep: 15, cook: 12,
        ings: [
            {n:"All-purpose flour",a:2.25,u:"cups"},
            {n:"Butter (softened)",a:1,u:"cups"},
            {n:"Sugar",a:0.75,u:"cups"},
            {n:"Brown sugar",a:0.75,u:"cups"},
            {n:"Eggs",a:2,u:"pcs"},
            {n:"Chocolate chips",a:2,u:"cups"},
            {n:"Vanilla extract",a:1,u:"tsp"},
            {n:"Baking soda",a:1,u:"tsp"}
        ],
        steps: [
            "Preheat oven to 375°F (190°C).",
            "Cream together softened butter, sugar, and brown sugar until light and fluffy.",
            "Beat in eggs one at a time, then stir in vanilla.",
            "In a separate bowl, whisk flour and baking soda. Gradually blend into the wet mixture.",
            "Fold in chocolate chips.",
            "Drop rounded tablespoons of dough onto ungreased baking sheets, spaced 2 inches apart.",
            "Bake for 9-12 minutes until edges are golden but centers look slightly underdone.",
            "Cool on baking sheet for 5 minutes, then transfer to a wire rack."
        ],
        notes: "For thicker cookies, chill the dough for 30 minutes before baking. Use a mix of milk and dark chocolate chips for more flavor complexity.",
        tags: ["easy","vegetarian"]
    },
    bananabread: {
        name: "Banana Bread",
        baseServ: 10,
        prep: 10, cook: 60,
        ings: [
            {n:"Ripe bananas",a:3,u:"pcs"},
            {n:"All-purpose flour",a:1.5,u:"cups"},
            {n:"Sugar",a:0.75,u:"cups"},
            {n:"Butter (melted)",a:0.33,u:"cups"},
            {n:"Eggs",a:1,u:"pcs"},
            {n:"Baking soda",a:1,u:"tsp"},
            {n:"Salt",a:0.25,u:"tsp"}
        ],
        steps: [
            "Preheat oven to 350°F (175°C). Grease a 9x5 inch loaf pan.",
            "Mash bananas in a large bowl until mostly smooth.",
            "Stir in melted butter, then mix in sugar, beaten egg, and vanilla.",
            "Sprinkle baking soda and salt over the mixture and stir to combine.",
            "Add flour and fold gently until just combined — do not overmix.",
            "Pour batter into the prepared loaf pan.",
            "Bake for 55-65 minutes, or until a toothpick inserted in the center comes out clean.",
            "Cool in pan for 10 minutes, then turn out onto a wire rack."
        ],
        notes: "The riper the bananas, the sweeter and more flavorful the bread. Freeze overripe bananas peeled in a zip bag for later use.",
        tags: ["easy","vegetarian"]
    },
    brownies: {
        name: "Brownies",
        baseServ: 16,
        prep: 15, cook: 25,
        ings: [
            {n:"Dark chocolate",a:8,u:"oz"},
            {n:"Butter",a:0.5,u:"cups"},
            {n:"Sugar",a:1,u:"cups"},
            {n:"Eggs",a:2,u:"pcs"},
            {n:"All-purpose flour",a:0.5,u:"cups"},
            {n:"Cocoa powder",a:0.25,u:"cups"}
        ],
        steps: [
            "Preheat oven to 350°F (175°C). Line an 8x8 inch baking pan with parchment paper.",
            "Melt chocolate and butter together in a heatproof bowl over simmering water (or microwave in 30-second intervals). Stir until smooth.",
            "Remove from heat and stir in sugar until combined.",
            "Beat in eggs one at a time, then stir in vanilla.",
            "Sift flour and cocoa powder together, then fold into the chocolate mixture until just combined.",
            "Pour into prepared pan and spread evenly.",
            "Bake for 22-25 minutes. The top should be set but a toothpick should come out with moist crumbs.",
            "Cool completely in the pan before cutting into squares."
        ],
        notes: "For fudgier brownies, slightly underbake and use more butter than flour. For cakey brownies, add an extra egg and a bit more flour.",
        tags: ["vegetarian"]
    },
    greeksalad: {
        name: "Greek Salad Bowl",
        baseServ: 2,
        prep: 10, cook: 0,
        ings: [
            {n:"Cucumber",a:1,u:"pcs"},
            {n:"Cherry tomatoes",a:1,u:"cups"},
            {n:"Feta cheese",a:4,u:"oz"},
            {n:"Kalamata olives",a:0.25,u:"cups"},
            {n:"Red onion",a:0.5,u:"pcs"},
            {n:"Olive oil",a:2,u:"tbsp"}
        ],
        steps: [
            "Dice cucumber into chunks, halve cherry tomatoes, and slice red onion into thin rings.",
            "Arrange cucumber, tomatoes, and onion in a bowl or on a plate.",
            "Crumble feta cheese generously over the top.",
            "Scatter Kalamata olives around the salad.",
            "Drizzle with extra virgin olive oil and a pinch of dried oregano.",
            "Season with salt and pepper to taste. Serve immediately."
        ],
        notes: "Use a block of feta and crumble it by hand for the best texture. A splash of red wine vinegar adds a nice tang.",
        tags: ["easy","vegetarian","glutenfree","healthy"]
    },
    oats: {
        name: "Overnight Oats",
        baseServ: 1,
        prep: 5, cook: 0,
        ings: [
            {n:"Rolled oats",a:0.5,u:"cups"},
            {n:"Greek yogurt",a:0.25,u:"cups"},
            {n:"Milk",a:0.5,u:"cups"},
            {n:"Chia seeds",a:1,u:"tbsp"},
            {n:"Honey",a:1,u:"tbsp"},
            {n:"Mixed berries",a:0.25,u:"cups"}
        ],
        steps: [
            "In a jar or container, combine rolled oats, yogurt, milk, and chia seeds.",
            "Stir well until everything is evenly mixed.",
            "Add honey and stir again.",
            "Cover and refrigerate overnight (or at least 4 hours).",
            "In the morning, top with fresh berries and an extra drizzle of honey if desired.",
            "Eat cold straight from the jar or warm in the microwave for 1-2 minutes."
        ],
        notes: "Make 5 jars on Sunday for a full week of breakfasts. Swap toppings daily — try banana and peanut butter, or apple and cinnamon.",
        tags: ["easy","vegetarian","glutenfree","healthy"]
    }
};

var ingredients = [];
var steps = [];

function addIng(name, amount, unit) {
    ingredients.push({ n: name || '', a: amount || '', u: unit || 'cups' });
    renderIngredients();
    renderPreview();
}

function removeIng(i) {
    ingredients.splice(i, 1);
    renderIngredients();
    renderPreview();
}

function addStep(text) {
    steps.push(text || '');
    renderSteps();
    renderPreview();
}

function removeStep(i) {
    steps.splice(i, 1);
    renderSteps();
    renderPreview();
}

function renderIngredients() {
    var units = ['pcs','cups','tbsp','tsp','oz','lbs','lbs','ml','liters','clove','pinch','slices','heads','pcs'];
    var unitOpts = ['pcs','cups','tbsp','tsp','oz','lbs','ml','liters','clove','pinch','slices','heads'].map(function(u){
        return '<option value="'+u+'"'+(ingredients.length && ingredients[arguments[1]] && ingredients[arguments[1]].u===u?' selected':'')+'>'+u+'</option>';
    });
    var html = '';
    for (var i = 0; i < ingredients.length; i++) {
        var ing = ingredients[i];
        var opts = ['pcs','cups','tbsp','tsp','oz','lbs','ml','liters','clove','pinch','slices','heads'].map(function(u){
            return '<option value="'+u+'"'+(ing.u===u?' selected':'')+'>'+u+'</option>';
        }).join('');
        html += '<div class="ing-row">' +
            '<input type="number" class="form-control form-control-sm" style="width:65px;flex-shrink:0;" placeholder="Amt" value="'+esc(ing.a)+'" min="0" step="0.25" oninput="ingredients['+i+'].a=this.value;renderPreview()">' +
            '<select class="form-select form-select-sm" style="width:80px;flex-shrink:0;" onchange="ingredients['+i+'].u=this.value;renderPreview()">'+opts+'</select>' +
            '<input type="text" class="form-control form-control-sm" placeholder="Ingredient name" value="'+esc(ing.n)+'" oninput="ingredients['+i+'].n=this.value;renderPreview()">' +
            '<button class="btn btn-outline-danger btn-sm" style="flex-shrink:0;padding:.15rem .4rem;" onclick="removeIng('+i+')">&times;</button>' +
            '</div>';
    }
    $('ing-list').innerHTML = html;
}

function renderSteps() {
    var html = '';
    for (var i = 0; i < steps.length; i++) {
        html += '<div class="step-row">' +
            '<span class="step-num" style="margin-top:5px;">'+(i+1)+'</span>' +
            '<textarea class="form-control form-control-sm" rows="2" placeholder="Step '+(i+1)+'..." oninput="steps['+i+'].value=this.value;steps['+i+']=this.value;renderPreview()">'+esc(steps[i])+'</textarea>' +
            '<button class="btn btn-outline-danger btn-sm" style="flex-shrink:0;padding:.15rem .4rem;margin-top:4px;" onclick="removeStep('+i+')">&times;</button>' +
            '</div>';
    }
    $('step-list').innerHTML = html;
}

function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function adjServ(d) {
    var el = $('r-serv');
    var v = parseInt(el.value, 10) || 1;
    v = Math.max(1, v + d);
    el.value = v;
    $('r-serv-num').textContent = v;
    renderPreview();
}

function loadTemplate(key) {
    if (!key || !templates[key]) return;
    var t = templates[key];
    $('r-name').value = t.name;
    $('r-prep').value = t.prep;
    $('r-cook').value = t.cook;
    $('r-notes').value = t.notes || '';
    $('r-serv').value = t.baseServ;
    $('r-serv-num').textContent = t.baseServ;
    BASE_SERVINGS[key] = t.baseServ;

    ingredients = t.ings.map(function(ig){
        return { n: ig.n, a: ig.a, u: ig.u };
    });
    steps = t.steps.slice();

    var allTags = ['vegetarian','vegan','glutenfree','dairyfree','quick','easy'];
    allTags.forEach(function(tag){
        var cb = $('tag-'+tag);
        if (cb) cb.checked = t.tags && t.tags.indexOf(tag) !== -1;
    });

    renderIngredients();
    renderSteps();
    renderPreview();
}

function renderPreview() {
    var name = $('r-name').value.trim();
    var serv = parseInt($('r-serv').value, 10) || 1;
    var prep = $('r-prep').value;
    var cook = $('r-cook').value;
    var notes = $('r-notes').value.trim();
    var selKey = $('tpl-select').value;
    var baseServ = (selKey && BASE_SERVINGS[selKey]) ? BASE_SERVINGS[selKey] : serv;
    var scale = serv / baseServ;

    var tagList = ['vegetarian','vegan','glutenfree','dairyfree','quick','easy'];
    var activeTags = [];
    tagList.forEach(function(tag){
        var cb = $('tag-'+tag);
        if (cb && cb.checked) activeTags.push(tag);
    });

    if (!name && ingredients.length === 0 && steps.length === 0) {
        $('recipe-card').innerHTML = '<p class="text-secondary small mb-0">Select a template or start filling in the form.</p>';
        return;
    }

    var h = '';
    h += '<h2>' + esc(name || 'Untitled Recipe') + '</h2>';

    h += '<div style="margin-bottom:.75rem;">';
    if (prep) h += '<span class="badge-time badge-prep">Prep: ' + esc(prep) + ' min</span>';
    if (cook) h += '<span class="badge-time badge-cook">Cook: ' + esc(cook) + ' min</span>';
    h += '<span class="badge-time badge-serv">Servings: ' + serv + '</span>';
    h += '</div>';

    if (activeTags.length) {
        h += '<div style="margin-bottom:.75rem;">';
        activeTags.forEach(function(tag){
            h += '<span class="badge-tag badge-'+tag+'">'+tag.charAt(0).toUpperCase()+tag.slice(1)+'</span>';
        });
        h += '</div>';
    }

    if (ingredients.length) {
        h += '<div style="margin-bottom:.75rem;"><strong style="font-size:.92rem;">Ingredients</strong>';
        ingredients.forEach(function(ig){
            if (!ig.n && !ig.a) return;
            var amt = ig.a ? parseFloat(ig.a) : 0;
            if (selKey && amt) {
                amt = Math.round(amt * scale * 100) / 100;
            }
            var amtStr = amt ? amt : '';
            h += '<div class="ingredient-check"><input type="checkbox"><span>' +
                (amtStr ? '<strong>' + amtStr + '</strong> ' + esc(ig.u) + ' ' : '') +
                esc(ig.n) + '</span></div>';
        });
        h += '</div>';
    }

    if (steps.length) {
        h += '<div style="margin-bottom:.75rem;"><strong style="font-size:.92rem;">Instructions</strong>';
        steps.forEach(function(s, i){
            if (!s) return;
            h += '<div class="instruction-step"><span class="step-num">'+(i+1)+'</span><span>'+ esc(s) +'</span></div>';
        });
        h += '</div>';
    }

    if (notes) {
        h += '<div class="notes-callout"><strong>Notes:</strong> ' + esc(notes) + '</div>';
    }

    $('recipe-card').innerHTML = h;
}

function getFormData() {
    var name = $('r-name').value.trim();
    var serv = parseInt($('r-serv').value, 10) || 1;
    var prep = parseInt($('r-prep').value, 10) || 0;
    var cook = parseInt($('r-cook').value, 10) || 0;
    var notes = $('r-notes').value.trim();
    var tags = [];
    ['vegetarian','vegan','glutenfree','dairyfree','quick','easy'].forEach(function(t){
        var cb = $('tag-'+t);
        if (cb && cb.checked) tags.push(t);
    });
    var ings = ingredients.filter(function(ig){ return ig.n || ig.a; }).map(function(ig){
        return { name: ig.n, amount: ig.a ? parseFloat(ig.a) : 0, unit: ig.u };
    });
    var instr = steps.filter(function(s){ return s.trim(); });
    return { name: name, servings: serv, prepMinutes: prep, cookMinutes: cook, ingredients: ings, instructions: instr, notes: notes, tags: tags };
}

function copyMarkdown() {
    var d = getFormData();
    var md = '# ' + (d.name || 'Untitled Recipe') + '\n\n';
    md += '**Servings:** ' + d.servings;
    if (d.prepMinutes) md += ' | **Prep:** ' + d.prepMinutes + ' min';
    if (d.cookMinutes) md += ' | **Cook:** ' + d.cookMinutes + ' min';
    md += '\n\n';
    if (d.tags.length) {
        md += 'Tags: ' + d.tags.join(', ') + '\n\n';
    }
    if (d.ingredients.length) {
        md += '## Ingredients\n\n';
        d.ingredients.forEach(function(ig){
            md += '- [ ] ' + (ig.amount ? ig.amount + ' ' : '') + ig.unit + ' ' + ig.name + '\n';
        });
        md += '\n';
    }
    if (d.instructions.length) {
        md += '## Instructions\n\n';
        d.instructions.forEach(function(s, i){
            md += (i+1) + '. ' + s + '\n';
        });
        md += '\n';
    }
    if (d.notes) {
        md += '## Notes\n\n' + d.notes + '\n';
    }
    navigator.clipboard.writeText(md).then(function(){
        showToast('Markdown copied!');
    });
}

function copyJSON() {
    var d = getFormData();
    var json = JSON.stringify(d, null, 2);
    navigator.clipboard.writeText(json).then(function(){
        showToast('JSON copied!');
    });
}

function showToast(msg) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#34d399;color:#000;padding:.5rem 1rem;border-radius:8px;font-weight:600;font-size:.85rem;z-index:9999;animation:fadeIn .3s;';
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 2000);
}

function saveRecipe() {
    var d = getFormData();
    var saved = JSON.parse(localStorage.getItem('recipegen_saved') || '[]');
    var exists = false;
    for (var i = 0; i < saved.length; i++) {
        if (saved[i].name === d.name) { saved[i] = d; exists = true; break; }
    }
    if (!exists) saved.push(d);
    localStorage.setItem('recipegen_saved', JSON.stringify(saved));
    showToast('Recipe saved!');
}

function loadRecipe() {
    var saved = JSON.parse(localStorage.getItem('recipegen_saved') || '[]');
    if (!saved.length) { showToast('No saved recipes found.'); return; }
    var names = saved.map(function(r,i){ return (i+1) + '. ' + r.name; }).join('\n');
    var choice = prompt('Saved recipes:\n' + names + '\n\nEnter the number to load:');
    if (!choice) return;
    var idx = parseInt(choice, 10) - 1;
    if (idx < 0 || idx >= saved.length) { showToast('Invalid selection.'); return; }
    var r = saved[idx];
    $('r-name').value = r.name || '';
    $('r-serv').value = r.servings || 4;
    $('r-serv-num').textContent = r.servings || 4;
    $('r-prep').value = r.prepMinutes || 0;
    $('r-cook').value = r.cookMinutes || 0;
    $('r-notes').value = r.notes || '';
    ['vegetarian','vegan','glutenfree','dairyfree','quick','easy'].forEach(function(tag){
        var cb = $('tag-'+tag);
        if (cb) cb.checked = r.tags && r.tags.indexOf(tag) !== -1;
    });
    ingredients = (r.ingredients || []).map(function(ig){
        return { n: ig.name, a: ig.amount, u: ig.unit };
    });
    steps = (r.instructions || []).slice();
    renderIngredients();
    renderSteps();
    renderPreview();
    showToast('Recipe loaded!');
}

function clearAll() {
    $('r-name').value = '';
    $('r-prep').value = '0';
    $('r-cook').value = '0';
    $('r-notes').value = '';
    $('r-serv').value = '4';
    $('r-serv-num').textContent = '4';
    $('tpl-select').value = '';
    ['vegetarian','vegan','glutenfree','dairyfree','quick','easy'].forEach(function(tag){
        var cb = $('tag-'+tag);
        if (cb) cb.checked = false;
    });
    ingredients = [];
    steps = [];
    BASE_SERVINGS = {};
    renderIngredients();
    renderSteps();
    renderPreview();
}

renderIngredients();
renderSteps();
</script>
<?php page_footer(); ?>
