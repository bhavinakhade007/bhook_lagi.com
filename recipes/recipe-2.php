<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Paneer Butter Masala</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(120deg, #f6d365cc 0%, #fda085cc 100%),
                url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat fixed;
            margin: 0;
            min-height: 100vh;
        }
        .recipe-container {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px #0002;
            max-width: 420px;
            margin: 60px auto 0 auto;
            padding: 32px 28px 28px 28px;
            text-align: center;
        }
        .recipe-img {
            width: 320px;
            height: 220px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 2px 12px #fda08533;
            margin-bottom: 18px;
            background: #eee;
        }
        h1 {
            color: #f76b1c;
            font-size: 2rem;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        h3 {
            color: #43e97b;
            margin-bottom: 6px;
            margin-top: 18px;
        }
        p {
            color: #444;
            font-size: 1.08rem;
            margin: 0 0 10px 0;
        }
        .back-link {
            display: inline-block;
            margin-top: 24px;
            padding: 10px 22px;
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            box-shadow: 0 2px 8px #38f9d755;
            transition: background 0.2s, transform 0.2s;
        }
        .back-link:hover {
            background: linear-gradient(90deg, #38f9d7 0%, #43e97b 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.04);
        }
        @media (max-width: 600px) {
            .recipe-container {
                max-width: 98vw;
                padding: 10vw 2vw 8vw 2vw;
            }
            .recipe-img {
                width: 98vw;
                max-width: 98vw;
                height: 38vw;
            }
            h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
<div class="recipe-container">
    <img class="recipe-img" src="../images/paneer_butter_masala.jpg" alt="Paneer Butter Masala" onerror="this.onerror=null;this.src='../assets/default_recipe.jpg';">
    <h1>Paneer Butter Masala</h1>
    <h3>Ingredients:</h3>
    <p>paneer, tomato, cream, butter, spice</p>
    <h3>Steps:</h3>
    <ol style="text-align:left;max-width:380px;margin:0 auto 10px auto;padding-left:1.2em;">
        <li>Heat butter in a pan and sauté onions until golden.</li>
        <li>Add ginger-garlic paste and cook for 1 minute.</li>
        <li>Add chopped tomatoes and cook until soft.</li>
        <li>Blend onion-tomato mix into smooth paste.</li>
        <li>Heat butter again, add spices like cumin, garam masala, and chili powder.</li>
        <li>Pour in the paste and cook until oil separates.</li>
        <li>Add cream and adjust salt.</li>
        <li>Add paneer cubes and simmer for 5 minutes.</li>
        <li>Garnish with coriander and butter.</li>
        <li>Serve with naan or rice.</li>
    </ol>
    <a class="back-link" href="../index.php">⬅ Back to Recipes</a>
    <a href="../spin.php" style="display:inline-block;margin-top:18px;margin-left:8px;padding:10px 18px;background:#6c63ff;color:#fff;border:none;border-radius:6px;font-size:0.95rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px #6c63ff55;">Can't decide?</a>
    <a href="https://www.youtube.com/results?search_query=Paneer+Butter+Masala+recipe" target="_blank" style="display:inline-block;margin-top:18px;padding:10px 22px;background:linear-gradient(90deg,#f76b1c,#fda085);color:#fff;border:none;border-radius:6px;font-size:1rem;font-weight:bold;text-decoration:none;box-shadow:0 2px 8px #fda08533;transition:background 0.2s,transform 0.2s;">▶ Watch on YouTube</a>
</div>
<?php include_once __DIR__ . '/../recipe_ai_snippet.php'; ?>
</body>
</html>