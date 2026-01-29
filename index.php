<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}
$recipes = [];
// Try to read from SQLite DB first. If that fails, fall back to CSV for compatibility.
$dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'recipes.db';
if (file_exists($dbPath)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // fetch numeric rows to keep existing $r[0]..$r[4] indexing used later
        $stmt = $pdo->query('SELECT id, name, ingredients, steps, image FROM recipes ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        if ($rows) {
            $recipes = $rows;
        }
    } catch (Exception $e) {
        // On DB error we'll fall back to CSV below
        $recipes = [];
    }
}
// Fallback to CSV if DB not present or empty
if (count($recipes) === 0) {
    if (($handle = fopen(__DIR__ . "/recipes.csv", "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $recipes[] = $row;
        }
        fclose($handle);
    }
}
$results = $recipes;
if (isset($_GET['ingredient']) && $_GET['ingredient'] !== "") {
    $search = strtolower(trim($_GET['ingredient']));

    $searchTerms = preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    $results = array_filter($recipes, function($r) use ($searchTerms) {
        $ingredients = strtolower($r[2]);

        foreach ($searchTerms as $term) {
            if (strpos($ingredients, $term) === false) {
                return false;
            }
        }
        return true;
    });
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>bhooklagihai.com 😋</title>

    <!-- WhatsApp / Facebook / Instagram / Twitter Thumbnail -->
    <meta property="og:title" content="Bhook Laghi Hai">
    <meta property="og:description" content="Welcome to Bhook Laghi Hai – website preview.">
    <meta property="og:image" content="http://bhooklahilagihai.kesug.com/thumbnail.jpg">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:type" content="website">
    <meta property="og:url" content="http://bhooklahilagihai.kesug.com/">

    <meta charset="UTF-8">
    <style>
        @keyframes titleEntrance {
            0% {
                opacity: 0;
                transform: translateY(-30px) scale(0.96);
            }
            60% {
                opacity: 1;
                transform: translateY(6px) scale(1.03);
            }
            80% {
                transform: translateY(-2px) scale(0.99);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        @keyframes bounce {
            0%   { transform: scale(1); }
            30%  { transform: scale(1.08, 0.96); }
            50%  { transform: scale(0.96, 1.08); }
            70%  { transform: scale(1.04, 0.98); }
            100% { transform: scale(1); }
        }
        .search-bar-center {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 32px;
        }
        .search-bar-center form {
            background: linear-gradient(120deg, #fff 80%, #fda08522 100%);
            border-radius: 16px;
            box-shadow: 0 4px 24px #fda08533;
            padding: 18px 24px 14px 24px;
            display: flex;
            gap: 12px;
            align-items: center;
            border: 2.5px solid;
                    border-image: linear-gradient(90deg, #f6d365 0%, #fda085 100%);
                                    border-image-slice: 1;
                }
                /* top controls container: align logout and add-recipe */
                .top-controls{max-width:1200px;margin:0 auto 8px auto;display:flex;justify-content:flex-end;align-items:center;gap:10px;color:black;font-size:1.08rem}
        .search-bar-center input[type="text"] {
            padding: 10px 16px;
            width: 260px;
            border-radius: 7px;
            border: 1.5px solid #fda085;
            background: linear-gradient(90deg, #fffbe6 60%, #fda08522 100%);
            font-size: 1.08rem;
            color: #f76b1c;
            transition: border 0.2s, box-shadow 0.2s, background 0.2s;
            outline: none;
        }
        .search-bar-center input[type="text"]:focus {
            border: 1.5px solid #43e97b;
            box-shadow: 0 0 0 2px #43e97b33;
            background: #fff;
            animation: bounce 0.4s;
        }
        .search-bar-center button {
            padding: 10px 22px;
            background: linear-gradient(90deg,#f6d365,#fda085 60%,#43e97b 100%);
            color: #fff;
            border: none;
            border-radius: 7px;
            font-weight: bold;
            font-size: 1.08rem;
            box-shadow: 0 2px 8px #fda08533;
            cursor: pointer;
            letter-spacing: 1px;
            transition: background 0.2s, transform 0.2s;
        }
        .search-bar-center button:hover {
            background: linear-gradient(90deg,#43e97b,#fda085 60%,#f6d365 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.04);
            animation: bounce 0.4s;
        }
        .search-bar-center {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(120deg, #f6d365cc 0%, #fda085cc 100%),
                url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat fixed;
            margin: 0;
            min-height: 100vh;
        }
        h1 {
            color: #333;
            margin-top: 40px;
            font-size: 2.5rem;
            letter-spacing: 2px;
            text-shadow: 1px 2px 8px #fff7;
            opacity: 0;
            animation: titleEntrance 1.2s cubic-bezier(.33,1,.68,1) 0.2s forwards;
        }
        .recipes-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 32px;
            margin: 40px auto 0 auto;
            max-width: 1200px;
        }
        .recipe-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 220px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px #0001;
            text-decoration: none;
            transition: box-shadow 0.2s, transform 0.2s;
            overflow: hidden;
            padding-bottom: 18px;
        }
        .recipe-card:hover {
            box-shadow: 0 8px 32px #fda08555;
            transform: translateY(-4px) scale(1.03);
        }
        .img-wrap {
            width: 100%;
            height: 140px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px 16px 0 0;
            overflow: hidden;
        }
        .img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .recipe-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #f76b1c;
            margin-top: 14px;
            text-align: center;
            padding: 0 10px;
        }
        @media (max-width: 900px) {
            .recipes-grid {
                gap: 18px;
            }
            .recipe-card {
                width: 45vw;
                max-width: 260px;
            }
        }
        @media (max-width: 600px) {
            .recipes-grid {
                gap: 10px;
            }
            .recipe-card {
                width: 90vw;
                max-width: 98vw;
            }
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>

</head>
<body>
<h1>bhooklagihai.com 😋</h1>
<div style="text-align:right;max-width:1200px;margin:0 auto 10px auto;color:black
;font-size:1.08rem;">
    Welcome, <b><?php echo htmlspecialchars($_SESSION['user']); ?></b>!
</div>
<div class="top-controls">
    <form id="logoutForm" method="post" action="logout.php" style="display:inline-flex;align-items:center">
                <button id="logoutBtn" type="submit" class="logout-btn" title="Logout">
                        <svg class="logout-svg" viewBox="0 0 68 56" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <!-- door -->
                                <g transform="translate(6,4)">
                                    <rect class="door" x="0" y="0" width="28" height="48" rx="3"></rect>
                                    <circle cx="22" cy="24" r="2" fill="#ffc87c"></circle>
                                </g>
                                <!-- man silhouette group -->
                                <g class="walk-man" transform="translate(36,18)">
                                                <script>
                                                    (function(){
                                                        const form = document.getElementById('logoutForm');
                                                        const btn = document.getElementById('logoutBtn');
                                                        if(!form || !btn) return;
                                                        let animating = false;
                                                        form.addEventListener('submit', function(e){
                                                            if(animating) return e.preventDefault();
                                                            e.preventDefault();
                                                            animating = true;
                                                            btn.classList.add('animate-exit');
                                                            btn.disabled = true;
                                                            setTimeout(function(){
                                                                try{ form.submit(); } catch(err){ window.location = 'logout.php'; }
                                                            }, 780);
                                                        });
                                                    })();
                                                </script>
                                    <circle class="man" cx="6" cy="-2" r="4"></circle>
                                    <rect class="man" x="2" y="2" width="8" height="12" rx="2"></rect>
                                    <rect class="man" x="0" y="14" width="4" height="8" rx="1"></rect>
                                    <rect class="man" x="6" y="14" width="4" height="8" rx="1"></rect>
                                </g>
                        </svg>
                        <span class="btn-label">Logout</span>
                </button>
    </form>
    <a href="add_recipe.php" class="add-recipe-btn" style="padding:8px 12px;background:linear-gradient(90deg,#43e97b,#38f9d7);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">+ Add Recipe</a>
</div>
    <style>
        /* Logout animation styles */
        .logout-btn{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;background:linear-gradient(90deg,#ff7a5c,#ffb085);color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:700;overflow:hidden;position:relative}
        .logout-svg{width:34px;height:28px;flex:0 0 34px}
        .door{fill:#2d3748}
        .man{fill:#fff}
        .walk-man{transform-origin:center;}
        /* animation: man moves right and fades while door rotates open */
        .animate-exit .walk-man{animation:walkOut 720ms cubic-bezier(.2,.9,.2,1) forwards}
        .animate-exit .door{animation:doorOpen 400ms ease-out forwards}
        @keyframes walkOut{
            0%{transform:translateX(0) scale(1);opacity:1}
            60%{transform:translateX(26px) scale(0.98);opacity:1}
            100%{transform:translateX(56px) scale(0.9);opacity:0}
        }
        @keyframes doorOpen{
            0%{transform:rotateY(0deg)}
            100%{transform:rotateY(-55deg)}
        }
    </style>
<div class="search-bar-center">
    <form method="get">
        <input type="text" name="ingredient" placeholder="Search by ingredient (e.g. potato)" style="padding:8px; width:250px; border-radius:6px; border:1px solid #ccc; font-size:1rem;">
        <button type="submit" style="padding:8px 12px; background:linear-gradient(90deg,#43e97b,#38f9d7); color:white; border:none; border-radius:6px; font-weight:bold;">Search</button>
    </form>
</div>
<div class="recipes-grid">
    <?php if (count($results) > 0): ?>
        <?php foreach ($results as $r): ?>
            <?php if (strtolower(trim($r[1])) !== 'gulab jamun'): ?>
            <a class="recipe-card" href="recipes/recipe-<?php echo htmlspecialchars($r[0]); ?>.php">
                <div class="img-wrap">
                    <?php
                        $imgFile = 'images/' . htmlspecialchars($r[4]);
                        $imgPath = __DIR__ . '/' . $imgFile;
                        if (!empty($r[4]) && file_exists($imgPath)) {
                            $imgSrc = $imgFile;
                        } else {
                            $imgSrc = 'assets/default_recipe.jpg';
                        }
                    ?>
                    <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($r[1]); ?>">
                </div>
                <div class="recipe-title"><?php echo htmlspecialchars($r[1]); ?></div>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="color:red; text-align:center; width:100%; font-size:1.1rem;">No recipes found for this ingredient.</div>
    <?php endif; ?>
</div>
</body>
</html>