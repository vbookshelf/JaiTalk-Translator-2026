<?php
// ============================================================
// about.php
// Deliberately self-contained: its own copy of the palette and
// base styles rather than anything shared with index.php, so it
// has no dependency on that file and can't be broken by future
// changes there (or vice versa). If you re-theme index.php later,
// update the :root values below to match.
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

    <title>About JaiTalk</title>
    <meta name="description" content="JaiTalk is a free English-Thai voice translator to use on-the-go in Thailand. Non-profit, no ads, no signup, and no app download required.">

    <!-- Deliberately no noindex/nofollow here, unlike index.php - this
         page is meant to be discoverable. The live translator interface
         stays excluded from search; this page doesn't need to be. -->

    <link rel="shortcut icon" type="image/png" href="assets/jt-icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <style>
        :root {
            --bg: #fafafa;
            --bg2: #ffffff;
            --bg3: #f0f1f4;
            --border: rgba(0, 0, 0, 0.08);
            --border2: rgba(0, 0, 0, 0.14);
            --text: #1a1d29;
            --text2: #5a5f72;
            --text3: #8a91a5;
            --accent: #2f5fdb;
            --accent-dim: rgba(47, 99, 219, 0.08);
            --accent-glow: rgba(47, 95, 219, 0.18);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0d0f14; --bg2: #141720; --bg3: #1c2030;
                --border: rgba(255,255,255,0.08); --border2: rgba(255,255,255,0.14);
                --text: #e8eaf0; --text2: #8b90a4; --text3: #555b70;
                --accent: #5b8cff; --accent-dim: rgba(91,140,255,0.12); --accent-glow: rgba(91,140,255,0.25);
            }
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: Helvetica, Arial, sans-serif;
            font-size: 18px;
            margin: 0;
            padding: 50px 20px 80px;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .hero {
            text-align: center;
            margin-bottom: 34px;
        }

        .hero-image {
            width: 100%;
            height: auto;
            max-width: 140px;
            margin: 0 auto 4px auto;
            display: block;
        }

        h1 {
            letter-spacing: -0.02em;
            font-size: clamp(2rem, 8vw, 2.6rem);
            margin: 0 0 4px 0;
        }

        .hero-subtitle {
            color: var(--text2);
            font-weight: normal;
            font-size: 17px;
            margin: 0;
        }

        h2 {
            font-size: 20px;
            margin: 36px 0 12px 0;
            letter-spacing: -0.01em;
        }

        p {
            line-height: 1.7;
            color: var(--text);
            margin: 0 0 14px 0;
        }

        ul {
            margin: 0 0 14px 0;
            padding-left: 22px;
            line-height: 1.7;
        }

        li {
            margin-bottom: 6px;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        .card {
            background-color: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px 20px;
        }

        .privacy-card {
            background-color: var(--bg2);
            border: 1px solid var(--border2);
            border-left: 3px solid var(--accent);
            border-radius: 10px;
            padding: 18px 20px;
            margin-top: 10px;
        }

        .privacy-card p:last-child {
            margin-bottom: 0;
        }

        .back-link-wrap {
            text-align: center;
            margin-top: 44px;
        }

        .back-link {
            display: inline-block;
            background-color: var(--accent);
            color: #fff;
            text-decoration: none;
            padding: 12px 26px;
            border-radius: 8px;
            font-size: 16px;
        }

        .back-link:hover {
            opacity: 0.92;
        }

        .top-back-link {
            display: inline-block;
            color: var(--text2);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 26px;
        }

        .top-back-link:hover {
            color: var(--accent);
        }

        footer {
            text-align: center;
            color: var(--text3);
            font-size: 13px;
            margin-top: 50px;
        }
    </style>
</head>
<body>

<div class="container">

    <a href="index.php" class="top-back-link">Back to JaiTalk</a>

    <div class="hero">
        <img src="assets/jt-icon.png" alt="JaiTalk" class="hero-image" loading="eager">
        <h1><b>About JaiTalk</b></h1>
        <p class="hero-subtitle">Free English-Thai voice translator</p>
    </div>

    <div class="card">
        <p>JaiTalk is a free voice translator for English and Thai, built for both Thai locals and for travelers who need to communicate on the go in Thailand.</p>
		<p/>It's a simple and fast way to communicate at street food stalls, in markets, in a taxi or anywhere else where there's a language barrier.</p>
        <p>Speak or type in either language and JaiTalk translates it instantly, both as text and as spoken audio.</p>
		
		<p>No account needed. Just go to the website and start talking.</p>
    </div>

	<h2>Privacy & Safety</h2>
	<div class="privacy-card">
	  <ul>
	    <li>Your translation history is stored only in your browser for the current session and is cleared when you close the tab.</li>
	    <li>There are no ads, analytics, or user tracking built into JaiTalk.</li>
	    <li>JaiTalk sends your inputs to a third-party AI model provider that may retain prompt data. Please do not share sensitive or private information.</li>
	  </ul>
	</div>

    <h2>About this project</h2>
    <p>The open-source code is available on <a href="https://github.com/vbookshelf/JaiTalk-Translator-2026" target="_blank" rel="noopener">GitHub</a>.</p>

    <div class="back-link-wrap">
        <a href="index.php" class="back-link">Start translating</a>
    </div>

</div>

</body>
</html>
