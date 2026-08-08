<?php
// ============================================================
// JaiTalk - Realtime English <-> Thai Translator
//
// Interaction model: single-shot translate, styled after the
// Google Translate app - one text area for the source text, one
// result area below it for the translation (no running chat
// transcript). Tapping the translated text still plays it aloud,
// same tap-to-speak pattern used across the E-Bot family.
//
// Backend contract is unchanged from the previous (chat-style)
// version of this file - main.php still just takes my_message +
// dir and returns translated_text, so main.php needed no changes
// at all for this redesign.
// ============================================================

$bot_name = 'JaiTalk';

// ------------------------------------------------------------
// The one supported language pair, in both directions. Keyed by a
// short URL-safe token used in the ?dir= query param.
// NOTE: main.php keeps its own copy of this map to validate
// incoming requests against - keep both in sync.
// ------------------------------------------------------------
$directions = array(
	'entoth' => array('source' => 'English', 'target' => 'Thai',    'source_code' => 'en-US', 'target_code' => 'th', 'listening_hint' => 'Listening for English...'),
	'thtoen' => array('source' => 'Thai',    'target' => 'English', 'source_code' => 'th-TH', 'target_code' => 'en', 'listening_hint' => 'กำลังรอฟังภาษาไทย...'),
);

$dir = (isset($_GET['dir']) && array_key_exists($_GET['dir'], $directions)) ? $_GET['dir'] : 'entoth';
$current = $directions[$dir];
$other_dir = ($dir === 'entoth') ? 'thtoen' : 'entoth';
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<!--
    <meta name="robots" content="noindex, nofollow">
    -->

    <meta charset="utf-8">
    <title>JaiTalk Translator</title>
    <meta name="description" content="JaiTalk is a free English-Thai mobile-first voice translator to use on-the-go in Thailand. Non-profit. No ads. No signup.">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/png" href="assets/jt-icon.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <style>
        /* ============================================================
           Theme palette - same variable names/values as the rest of
           the E-Bot family, so it looks and feels consistent.
           ============================================================ */
        :root {
            --bg: #fafafa;
            --bg2: #f0f1f4;
            --bg3: #e8eaef;
            --border: rgba(0, 0, 0, 0.08);
            --border2: rgba(0, 0, 0, 0.14);
            --text: #1a1d29;
            --text2: #5a5f72;
            --text3: #636878;
            --accent: #2f5fdb;
            --accent-dim: rgba(47, 95, 219, 0.10);
            --accent-glow: rgba(47, 95, 219, 0.35);
            --mic-bg: #2f5fdb;
            --mic-icon: #ffffff;
            --mic-bg-active: #2f5fdb;
        }

        * { box-sizing: border-box; }

        .sr-only {
        	position: absolute;
        	width: 1px;
        	height: 1px;
        	padding: 0;
        	margin: -1px;
        	overflow: hidden;
        	clip: rect(0, 0, 0, 0);
        	white-space: nowrap;
        	border: 0;
        }

        html {
        	background-color: #e4e6eb;
        }

        body {
        	margin: 0;
        	background-color: var(--bg);
        	font-family: Helvetica, Arial, sans-serif;
        	color: var(--text);
        	padding-top: 60px; /* leave room for the fixed top bar */
        	min-height: 100vh;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        a { text-decoration: none; color: inherit; }

        /* ============================================================
           Bottom sticky bar setup, following the same approach as
           E-Bot: the top bar and bottom bar are position:fixed to the
           viewport (not the page pinned to a fixed height), and the
           page scrolls normally underneath them. .translate-panel
           reserves margin-bottom equal to the bottom bar's height so
           its content never sits behind it. This is simpler and more
           robust on mobile than pinning the whole page, since fixed
           elements are positioned relative to the viewport directly
           by the browser rather than relying on viewport-height units.
           ============================================================ */

        /* ============================================================
           Desktop / wide-viewport treatment: on phones the app fills
           the screen edge-to-edge as normal, but on wider screens it's
           constrained to a phone-sized column with rounded corners,
           floating on the darker page background - so it reads as a
           phone mockup rather than a webpage stretched full-width.
           ============================================================ */
        @media only screen and (min-width: 700px) {
        	body {
        		max-width: 420px;
        		height: auto;
        		min-height: calc(100vh - 64px);
        		margin: 32px auto;
        		border-radius: 34px;
        		overflow: hidden;
        		box-shadow: 0 25px 70px rgba(0, 0, 0, 0.18), 0 0 0 1px var(--border2);
        		/* A transform establishes a new containing block, so the
        		   fixed top/bottom bars stay confined to this card
        		   instead of the real browser viewport. */
        		transform: translateZ(0);
        	}
        }

        /* ============================================================
           Top bar - mirrors the Google Translate app's minimal bar:
           just a centered title, no star/avatar. A small gear replaces
           the avatar's spot, since a settings affordance still needs
           to live somewhere.
           ============================================================ */
        .topbar {
        	position: fixed;
        	top: 0;
        	left: 0;
        	width: 100%;
        	box-sizing: border-box;
        	z-index: 500;
        	display: flex;
        	align-items: center;
        	justify-content: center;
        	padding: 16px 20px;
        	background-color: var(--bg);
        	border-bottom: 1px solid var(--border);
            transition: background-color 0.3s ease, border-bottom-color 0.2s ease;
        }

        .topbar-title {
        	margin: 0;
        	font-size: 20px;
        	font-weight: bold;
        	letter-spacing: 0.01em;
        }

        /* .topbar is position:fixed, which already establishes the
           containing block this absolutely-positioned button needs -
           no extra position:relative wrapper required. */
        .history-btn {
        	position: absolute;
        	right: 14px;
        	top: 50%;
        	transform: translateY(-50%);
        	background: transparent;
        	border: none;
        	color: var(--text2);
        	font-size: 19px;
        	cursor: pointer;
        	padding: 8px;
        	display: flex;
        	align-items: center;
        	justify-content: center;
        	border-radius: 8px;
        	transition: background-color 0.15s ease, color 0.15s ease;
        }

        .history-btn:hover {
        	background-color: var(--bg3);
        	color: var(--text);
        }

        .history-btn:focus-visible {
        	outline: 2px solid var(--accent);
        	outline-offset: 2px;
        }

        /* Info/About button - same treatment as .history-btn, just
           pinned to the left side of the topbar instead of the right. */
        .info-btn {
        	position: absolute;
        	left: 14px;
        	top: 50%;
        	transform: translateY(-50%);
        	background: transparent;
        	border: none;
        	color: var(--text2);
        	font-size: 19px;
        	cursor: pointer;
        	padding: 8px;
        	display: flex;
        	align-items: center;
        	justify-content: center;
        	border-radius: 8px;
        	transition: background-color 0.15s ease, color 0.15s ease;
        }

        .info-btn:hover {
        	background-color: var(--bg3);
        	color: var(--text);
        }

        .info-btn:focus-visible {
        	outline: 2px solid var(--accent);
        	outline-offset: 2px;
        }

        /* ============================================================
           History overlay: a centered modal panel listing every
           input/translation pair from this session, newest first,
           independently scrollable from the rest of the page.
           ============================================================ */
        .history-overlay {
        	position: fixed;
        	top: 0;
        	left: 0;
        	right: 0;
        	bottom: 0;
        	background: rgba(0, 0, 0, 0.45);
        	z-index: 1000;
        	display: flex;
        	align-items: center;
        	justify-content: center;
        	padding: 20px;
        }

        .history-panel {
        	background-color: var(--bg);
        	border-radius: 16px;
        	width: 100%;
        	max-width: 420px;
        	max-height: 78vh;
        	display: flex;
        	flex-direction: column;
        	box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        	overflow: hidden;
        }

        .history-panel-header {
        	display: flex;
        	align-items: center;
        	justify-content: space-between;
        	padding: 16px 18px;
        	border-bottom: 1px solid var(--border);
        	flex-shrink: 0;
        }

        .history-panel-header h2 {
        	margin: 0;
        	font-size: 18px;
        }

        .history-close-btn {
        	background: transparent;
        	border: none;
        	color: var(--text2);
        	font-size: 20px;
        	line-height: 1;
        	cursor: pointer;
        	padding: 6px;
        	border-radius: 8px;
        }

        .history-close-btn:hover {
        	background-color: var(--bg3);
        	color: var(--text);
        }

        .history-close-btn:focus-visible {
        	outline: 2px solid var(--accent);
        	outline-offset: 2px;
        }

        .history-list {
        	overflow-y: auto;
        	padding: 6px 18px 18px;
        	flex: 1;
        }

        .history-empty {
        	color: var(--text2);
        	font-size: 14px;
        	font-style: italic;
        	text-align: center;
        	margin-top: 24px;
        }

        .history-item {
        	padding: 18px 0;
        	border-bottom: 1px solid var(--border);
        }

        .history-item:last-child {
        	border-bottom: none;
        }

        .history-item-lang {
        	font-size: 14px;
        	color: var(--text2);
        	letter-spacing: 0.02em;
        	margin-bottom: 6px;
        }

        .history-item-source {
        	font-size: 20px;
        	line-height: 1.4;
        	color: var(--text);
        	margin: 0 0 8px 0;
        }

        .history-item-translated {
        	font-size: 20px;
        	line-height: 1.4;
        	color: var(--accent);
        	margin: 0;
        }

        /* ============================================================
           Main translate panel: one growing text area for the source
           text, and (once there's a result) a divider plus the
           translated text below it. No chat bubbles, no history -
           a new translation replaces the last one, same as Google
           Translate's single input/result model.
           ============================================================ */
        .translate-panel {
        	margin-bottom: 240px; /* leave room for the fixed bottom bar */
        	padding: 24px 22px;
        }

        .source-textarea {
        	width: 100%;
        	border: none;
        	background: transparent;
        	color: var(--text);
        	font-family: inherit;
        	font-size: 28px;
        	line-height: 1.4;
        	resize: none;
        	outline: none;
        	min-height: 90px;
        }

        .source-textarea:focus-visible {
        	outline: 2px solid var(--accent);
        	outline-offset: 4px;
        	border-radius: 4px;
        }

        .source-textarea::placeholder {
        	color: var(--text3);
        }

        .result-section {
        	margin-top: 18px;
        	padding-top: 18px;
        	border-top: 1px solid var(--border);
        }

        .result-section.is-loading .translated-text {
        	opacity: 0.5;
        }

        .target-label {
        	color: var(--text2);
        	font-size: 14px;
        	margin-bottom: 8px;
        	letter-spacing: 0.02em;
        }

        .translated-text {
        	display: block;
        	width: 100%;
        	font-family: inherit;
        	font-size: 32px;
        	line-height: 1.4;
        	margin: 0;
        	padding: 0;
        	border: none;
        	background: transparent;
        	color: inherit;
        	text-align: left;
        	cursor: pointer;
        	transition: opacity 0.15s ease;
        }

        .translated-text:focus-visible {
        	outline: 2px solid var(--accent);
        	outline-offset: 4px;
        	border-radius: 4px;
        }

        .speaker-icon {
        	display: block;
        	margin-top: 10px;
        	font-size: 26px;
        	color: var(--accent);
        }

        .speaker-icon.speaking {
        	animation: speaker-pulse 1s ease-in-out infinite;
        }

        @keyframes speaker-pulse {
        	0%, 100% { opacity: 1; }
        	50% { opacity: 0.35; }
        }

        .no-audio-note {
        	display: block;
        	color: var(--text2);
        	font-size: 13px;
        	font-style: italic;
        	margin-top: 8px;
        }

        .translating-note {
        	color: var(--text2);
        	font-size: 15px;
        	font-style: italic;
        	margin-top: 6px;
        	display: none;
        }

        .result-section.is-loading .translating-note {
        	display: block;
        }

        /* ============================================================
           Bottom bar: the Google-style two-box language selector with
           a swap icon, plus a large rounded-square mic button beneath it.
           Flat - no drag handle, no rounded top corners.
           ============================================================ */
        .bottom-bar {
        	position: fixed;
        	bottom: 0;
        	left: 0;
        	width: 100%;
        	box-sizing: border-box;
        	z-index: 500;
        	padding: 18px 20px 26px;
        	background-color: var(--bg);
        	display: flex;
        	flex-direction: column;
        	align-items: center;
        	gap: 26px;
            transition: background-color 0.3s ease;
        }

        .language-selector {
        	display: flex;
        	align-items: center;
        	justify-content: center;
        	gap: 14px;
        	width: 100%;
        	max-width: 420px;
        }

        .lang-box {
        	flex: 1;
        	text-align: center;
        	background-color: var(--bg3);
        	color: var(--text);
        	padding: 14px 10px;
        	border-radius: 10px;
        	font-size: 17px;
        	font-weight: bold;
        	border: 1px solid transparent;
        	transition: border-color 0.15s ease, background-color 0.3s ease;
        }

        .language-selector:hover .lang-box,
        .language-selector:focus-visible .lang-box {
        	border-color: var(--accent-glow);
        }

        .language-selector:focus-visible {
        	outline: 2px solid var(--accent);
        	outline-offset: 4px;
        	border-radius: 12px;
        }

        .swap-icon {
        	color: var(--text2);
        	font-size: 20px;
        	flex-shrink: 0;
        	width: 32px;
        	text-align: center;
        }

        /* ============================================================
           Listening indicator: sits above the mic button, independent
           of the textarea, so it stays visible even once the textarea
           has text in it (unlike a placeholder, which disappears as
           soon as the field isn't empty). Reserves its line height at
           all times so the mic button doesn't jump when it toggles.
           ============================================================ */
        .listening-indicator {
        	font-size: 18px;
        	font-style: italic;
        	color: var(--text2);
        	display: none;
        }

        .listening-indicator.active {
        	display: block;
        }

        .mic-btn-large {
        	width: 200px;
        	max-width: 100%;
        	height: 64px;
        	border-radius: 32px;
        	background-color: var(--mic-bg);
        	border: none;
        	color: var(--mic-icon);
        	font-size: 30px;
        	cursor: pointer;
        	display: flex;
        	align-items: center;
        	justify-content: center;
        	box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        	transition: background-color 0.2s ease, color 0.2s ease, border-radius 0.2s ease;
        	position: relative;
        }

        .mic-btn-large:hover {
        	filter: brightness(1.05);
        }

        .mic-btn-large:focus-visible {
        	outline: 3px solid var(--accent);
        	outline-offset: 3px;
        }

        .mic-btn-large.listening {
        	background-color: var(--mic-bg-active);
        	color: #fff;
        	border-radius: 32px;
        }

        .mic-btn-large.listening::after {
        	content: '';
        	position: absolute;
        	top: 0; left: 0; right: 0; bottom: 0;
        	border-radius: inherit;
        	border: 3px solid var(--mic-bg-active);
        	animation: mic-pulse 1.4s ease-out infinite;
        	pointer-events: none;
        }

        @keyframes mic-pulse {
        	0% { transform: scale(1); opacity: 0.6; }
        	100% { transform: scale(1.7); opacity: 0; }
        }

        @media only screen and (max-width: 480px) {
        	.translate-panel { padding: 20px 18px; }
        	.source-textarea { font-size: 24px; }
        	.translated-text { font-size: 28px; }
        	.lang-box { font-size: 23px; }
        	.mic-btn-large { width: 80%; max-width: 320px; }
        }
    </style>

</head>

<body>

    <header class="topbar">
        <a href="about.php" class="info-btn" aria-label="About JaiTalk" title="About JaiTalk">
            <i class="fa fa-question" aria-hidden="true"></i>
        </a>
        <h1 class="topbar-title">JaiTalk Translator</h1>
        <button type="button" class="history-btn" id="history-btn" aria-label="View translation history" title="Translation history">
            <i class="fa fa-list-ul" aria-hidden="true"></i>
        </button>
    </header>

    <main class="translate-panel">
        <div id="a11y-status" class="sr-only" role="status" aria-live="polite"></div>
        <label for="user-input" class="sr-only">Text to translate (<?php echo htmlspecialchars($current['source'], ENT_QUOTES); ?>)</label>
        <textarea id="user-input" class="source-textarea" placeholder="Type here or tap the mic" rows="1" lang="<?php echo htmlspecialchars(substr($current['source_code'], 0, 2), ENT_QUOTES); ?>"></textarea>

        <div class="result-section" id="result-section" style="display:none;" aria-live="polite" aria-atomic="true">
            <div class="target-label" id="target-label"><?php echo htmlspecialchars($current['target'], ENT_QUOTES); ?></div>
            <button type="button" class="translated-text clickable" id="translated-output" onclick="speakText()">
                <span id="translated-text-content" lang="<?php echo htmlspecialchars(substr($current['target_code'], 0, 2), ENT_QUOTES); ?>"></span>
                <i class="fa fa-volume-up speaker-icon" id="speaker-icon" title="Click to play" aria-hidden="true"></i>
                <span class="no-audio-note" id="no-audio-note" style="display:none;">Audio not available for <span id="no-audio-lang"></span> on this device</span>
            </button>
            <div class="translating-note">Translating&hellip;</div>
        </div>
    </main>

    <div class="bottom-bar">
        <div class="listening-indicator" id="listening-indicator" aria-hidden="true"><?php echo htmlspecialchars($current['listening_hint'], ENT_QUOTES); ?></div>

        <a class="language-selector" href="index.php?dir=<?php echo htmlspecialchars($other_dir, ENT_QUOTES); ?>" aria-label="Switch to translating <?php echo htmlspecialchars($current['target'], ENT_QUOTES); ?> to <?php echo htmlspecialchars($current['source'], ENT_QUOTES); ?>">
            <span class="lang-box"><?php echo htmlspecialchars($current['source'], ENT_QUOTES); ?></span>
            <span class="swap-icon"><i class="fa fa-exchange" aria-hidden="true"></i></span>
            <span class="lang-box"><?php echo htmlspecialchars($current['target'], ENT_QUOTES); ?></span>
        </a>

        <button type="button" class="mic-btn-large" id="start-voicechat-btn" aria-label="Start Voicechat" title="Start Voicechat" aria-pressed="false">
            <i class="fa fa-microphone" aria-hidden="true"></i>
        </button>
    </div>

    <div class="history-overlay" id="history-overlay" role="dialog" aria-modal="true" aria-labelledby="history-overlay-title" style="display:none;">
        <div class="history-panel">
            <div class="history-panel-header">
                <h2 id="history-overlay-title">History</h2>
                <button type="button" class="history-close-btn" id="history-close-btn" aria-label="Close history">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="history-list" id="history-list">
                <p class="history-empty" id="history-empty">No translations yet.</p>
            </div>
        </div>
    </div>

</body>
</html>


<script>
/* ============================================================
   Config - passed from PHP so the frontend and backend agree on
   which direction is active for this page load.
   ============================================================ */
const bot_name = "<?php echo addslashes($bot_name); ?>";
const currentDir = "<?php echo addslashes($dir); ?>";
const sourceLanguageName = "<?php echo addslashes($current['source']); ?>";
const targetLanguageName = "<?php echo addslashes($current['target']); ?>";
const listeningHintText = "<?php echo addslashes($current['listening_hint']); ?>";
const targetLanguageCode = "<?php echo addslashes($current['target_code']); ?>";
const mic_lang_code = "<?php echo addslashes($current['source_code']); ?>";

const speechRecognitionSupported = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
const speechSynthesisSupported = !!window.speechSynthesis;

let micShouldBeOn = false;

// ============================================================
// Fallback timeout: if the mic never picks up a final result at
// all (the user stays silent, or the recognizer stalls partway
// through an utterance), the mic switches fully off after this
// many ms so it doesn't listen forever. The normal case - the user
// speaks and the recognizer reaches a final result - switches the
// mic off immediately and doesn't wait for this timer at all (see
// the 'result' handler in initialize_recognition()).
// ============================================================
let SILENCE_TIMEOUT_MS = 20000; // 10 seconds
let silenceTimer = null;

function resetSilenceTimer() {
    if (silenceTimer) clearTimeout(silenceTimer);
    silenceTimer = setTimeout(() => {
        stop_recognition(); // silence detected - switch mic off; user must tap to restart
    }, SILENCE_TIMEOUT_MS);
}

function clearSilenceTimer() {
    if (silenceTimer) {
        clearTimeout(silenceTimer);
        silenceTimer = null;
    }
}

// Resolved once, on page load, and reused for the whole session (a
// direction flip reloads the page anyway, which naturally
// re-resolves this for the new target language).
window.targetVoice = null;
window.targetVoiceAvailable = false;

function cacheTargetVoice() {
    if (!speechSynthesisSupported) {
        window.targetVoiceAvailable = false;
        return;
    }
    const voices = window.speechSynthesis.getVoices();
    if (!voices || !voices.length) {
        return; // onvoiceschanged (registered below) will retry
    }

    const prefix = targetLanguageCode.toLowerCase();
    const candidates = voices.filter(v => v.lang && v.lang.toLowerCase().startsWith(prefix));

    if (candidates.length > 0) {
        window.targetVoice = candidates[0];
        window.targetVoiceAvailable = true;
    } else {
        window.targetVoice = null;
        window.targetVoiceAvailable = false;
    }

    updateAudioAvailabilityUI();
}

function updateAudioAvailabilityUI() {
    var icon = document.getElementById('speaker-icon');
    var note = document.getElementById('no-audio-note');
    var noAudioLang = document.getElementById('no-audio-lang');
    if (!icon || !note) return;
    if (window.targetVoiceAvailable) {
        icon.style.display = '';
        note.style.display = 'none';
    } else {
        icon.style.display = 'none';
        noAudioLang.textContent = targetLanguageName;
        note.style.display = 'block';
    }
}

cacheTargetVoice();
if (speechSynthesisSupported) {
    window.speechSynthesis.onvoiceschanged = cacheTargetVoice;
}

// ============================================================
// Speaker icon / tap-to-speak
// There's only ever one translated result on screen at a time, so
// this is simpler than the chat-transcript version: one icon
// element, reused for whichever translation is currently showing.
// ============================================================
function quiet_please() {
    speechSynthesis.cancel();
    var icon = document.getElementById('speaker-icon');
    if (icon) {
        icon.classList.remove('speaking');
        icon.setAttribute('title', 'Click to play');
    }
    if (micShouldBeOn) {
        restart_recognition_if_needed();
    }
}

function speak(text) {
    if (!speechSynthesisSupported || !window.targetVoiceAvailable) {
        return;
    }

    var icon = document.getElementById('speaker-icon');
    speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance();
    utterance.text = text;
    utterance.voice = window.targetVoice;
    utterance.lang = window.targetVoice.lang;
    utterance.rate = 1;

    // Pause recognition while JaiTalk plays audio, so the mic
    // doesn't try to transcribe its own translation being spoken.
    if (window.recognition) {
        window.recognition.removeEventListener('end', handleEnd);
        window.recognition.stop();
    }

    if (icon) {
        icon.classList.add('speaking');
        icon.setAttribute('title', 'Playing - click to mute');
    }

    utterance.onend = function() {
        if (icon) { icon.classList.remove('speaking'); icon.setAttribute('title', 'Click to play'); }
        restart_recognition_if_needed();
    };

    utterance.onerror = function() {
        if (icon) { icon.classList.remove('speaking'); icon.setAttribute('title', 'Click to play'); }
        restart_recognition_if_needed();
    };

    setTimeout(() => { speechSynthesis.speak(utterance); }, 30);
}

function speakText() {
    var icon = document.getElementById('speaker-icon');
    if (icon && icon.classList.contains('speaking')) {
        quiet_please();
        return;
    }
    var textContent = document.getElementById('translated-text-content');
    if (textContent) {
        speak(textContent.textContent);
    }
}

function removeEmojis(text) {
    return text.replace(/[\u{1F600}-\u{1F64F}]/gu, '')
               .replace(/[\u{1F300}-\u{1F5FF}]/gu, '')
               .replace(/[\u{1F680}-\u{1F6FF}]/gu, '')
               .replace(/[\u{2600}-\u{26FF}]/gu, '')
               .replace(/[\u{2700}-\u{27BF}]/gu, '')
               .replace(/[\u{FE00}-\u{FE0F}]/gu, '')
               .replace(/[\u{1F900}-\u{1F9FF}]/gu, '');
}

function removeNewlines(str) {
  return str.replace(/[\r\n]+/g, ' ').trim();
}

// ============================================================
// Mic: tap to start listening. As soon as the recognizer reaches a
// final result (i.e. the user has finished a phrase), the mic
// switches fully off (button reverts) - the user must tap the mic
// again to speak another phrase. SILENCE_TIMEOUT_MS is a fallback:
// if the user never says anything at all (or the recognizer stalls
// mid-utterance without ever reaching a final result), the mic
// switches off automatically after that many ms. Fixed to whichever
// language is currently the SOURCE language for this page load -
// flipping direction reloads the page.
//
// The browser's own speech-recognition engine has its own built-in
// silence timeout, but it's not adjustable and often much shorter
// than we want. So instead we swallow it here (immediately restart
// recognition whenever the browser stops it on its own) and let our
// own logic above be the thing that actually decides when to switch
// the mic off.
// ============================================================
function handleEnd() {
    if (!micShouldBeOn) return;
    try {
        window.recognition.start();
    } catch (err) {
        console.log('Recognition already running or could not be restarted:', err);
    }
}

function restart_recognition_if_needed() {
    if (!micShouldBeOn) return;
    if (!window.recognition) return;
    window.recognition.removeEventListener('end', handleEnd);
    window.recognition.addEventListener('end', handleEnd);
    try {
        window.recognition.start();
        resetSilenceTimer(); // give a fresh window after resuming (e.g. post-playback)
    } catch (err) {
        console.log('Recognition already running or could not be restarted:', err);
    }
}

function initialize_recognition() {
    window.SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    recognition.lang = mic_lang_code;
    window.recognition = recognition;

    window.recognition.addEventListener('end', handleEnd);
    window.recognition.addEventListener('result', (e) => {
        let text = Array.from(e.results)
            .map((result) => result[0])
            .map((result) => result.transcript)
            .join("");

        document.getElementById('user-input').value = text;

        if (e.results[0].isFinal) {
            // The recognizer has determined the user is done speaking -
            // switch the mic off immediately rather than waiting for
            // SILENCE_TIMEOUT_MS. The user taps the mic again to speak
            // another phrase.
            clearSilenceTimer();
            translateNow();
            stop_recognition();
        } else {
            // Still-interim speech: keep the fallback silence timer
            // running in case the recognizer never reaches a final
            // result (e.g. it stalls out mid-utterance).
            resetSilenceTimer();
        }
    });

    window.recognition.start();
    resetSilenceTimer(); // also times out if the user never speaks at all

    userInput.placeholder = listeningHintText;

    const listeningIndicator = document.getElementById('listening-indicator');
    if (listeningIndicator) {
        listeningIndicator.classList.add('active');
    }
    syncBottomBarSpacing();

    const button = document.getElementById("start-voicechat-btn");
    if (button) {
        button.classList.add('listening');
        button.setAttribute('aria-label', 'Stop Voicechat');
        button.setAttribute('title', 'Stop Voicechat');
        button.setAttribute('aria-pressed', 'true');
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-microphone');
            icon.classList.add('fa-stop');
        }
    }
}

function stop_recognition() {
    micShouldBeOn = false;
    clearSilenceTimer();
    if (window.recognition) {
        window.recognition.removeEventListener('end', handleEnd);
        try { window.recognition.stop(); } catch (err) {}
        window.recognition = null;
    }
    const button = document.getElementById("start-voicechat-btn");
    if (button) {
        button.classList.remove('listening');
        button.setAttribute('aria-label', 'Start Voicechat');
        button.setAttribute('title', 'Start Voicechat');
        button.setAttribute('aria-pressed', 'false');
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-stop');
            icon.classList.add('fa-microphone');
        }
    }
    userInput.placeholder = DEFAULT_PLACEHOLDER;

    const listeningIndicator = document.getElementById('listening-indicator');
    if (listeningIndicator) {
        listeningIndicator.classList.remove('active');
    }
    syncBottomBarSpacing();
}

function toggle_voicechat() {
    if (!speechRecognitionSupported) {
        alert('Sorry, voice recognition is not supported in this browser. Please try Google Chrome.');
        return;
    }

    // Tapping the mic no longer wipes whatever text/translation is
    // already on screen - the existing result stays visible until
    // new speech actually comes in (the 'result' handler in
    // initialize_recognition() replaces userInput's value once the
    // recognizer has something to report).
    if (window.recognition) {
        stop_recognition();
        return;
    }
    micShouldBeOn = true;
    initialize_recognition();
}

document.getElementById('start-voicechat-btn').addEventListener('click', toggle_voicechat);

// ============================================================
// Translate: single input, single result - no history. A new
// translation simply replaces whatever was shown before. Fires
// automatically (debounced) as the user types or finishes
// speaking, matching Google Translate's live-translate behavior.
// ============================================================
let translateDebounceTimer = null;
let translateRequestId = 0;

const userInput = document.getElementById('user-input');
const DEFAULT_PLACEHOLDER = userInput.placeholder;
const resultSection = document.getElementById('result-section');
const targetLabel = document.getElementById('target-label');
const translatedTextContent = document.getElementById('translated-text-content');
const a11yStatus = document.getElementById('a11y-status');

function autoResizeTextarea() {
    userInput.style.height = 'auto';
    userInput.style.height = userInput.scrollHeight + 'px';
}

// The .translate-panel CSS margin-bottom is only a static fallback.
// The bottom bar's real height can grow (larger text sizes, wrapped
// language names, etc.), so measure it and keep the panel's reserved
// space in sync to make sure the fixed bar never overlaps content.
function syncBottomBarSpacing() {
    var bar = document.querySelector('.bottom-bar');
    var panel = document.querySelector('.translate-panel');
    if (bar && panel) {
        panel.style.marginBottom = (bar.offsetHeight + 24) + 'px';
    }
}
window.addEventListener('load', syncBottomBarSpacing);
window.addEventListener('resize', syncBottomBarSpacing);

// ============================================================
// Session history: every completed input/translation pair from
// this page load, oldest first. Persisted to sessionStorage so it
// survives the page reload that happens when the translation
// direction is swapped (that link is a normal navigation, not an
// AJAX call) - but still resets when the browser tab is closed,
// matching "this session" scope.
// ============================================================
const HISTORY_STORAGE_KEY = 'jaitalk_history';
let translationHistory = [];

const historyOverlay = document.getElementById('history-overlay');
const historyList = document.getElementById('history-list');
const historyEmpty = document.getElementById('history-empty');
const historyBtn = document.getElementById('history-btn');
const historyCloseBtn = document.getElementById('history-close-btn');

function loadHistoryFromStorage() {
    try {
        var raw = sessionStorage.getItem(HISTORY_STORAGE_KEY);
        translationHistory = raw ? (JSON.parse(raw) || []) : [];
    } catch (err) {
        // Storage unavailable/corrupt (e.g. private browsing) - just
        // start with an empty in-memory history for this page load.
        translationHistory = [];
    }
}

function saveHistoryToStorage() {
    try {
        sessionStorage.setItem(HISTORY_STORAGE_KEY, JSON.stringify(translationHistory));
    } catch (err) {
        // If storage isn't available, history simply won't survive
        // a reload - it still works fine for the current page load.
    }
}

function addHistoryEntry(sourceText, translatedText, sourceLang, targetLang) {
    translationHistory.push({
        sourceText: sourceText,
        translatedText: translatedText,
        sourceLang: sourceLang,
        targetLang: targetLang
    });
    saveHistoryToStorage();
    renderHistory();
    // Keep the newest entry (now at the bottom) in view.
    historyList.scrollTop = historyList.scrollHeight;
}

function renderHistory() {
    historyList.innerHTML = '';

    if (translationHistory.length === 0) {
        historyList.appendChild(historyEmpty);
        return;
    }

    translationHistory.forEach(function(entry) {
        const item = document.createElement('div');
        item.className = 'history-item';

        const langLine = document.createElement('div');
        langLine.className = 'history-item-lang';
        langLine.textContent = entry.sourceLang + ' \u2192 ' + entry.targetLang;

        const sourceP = document.createElement('p');
        sourceP.className = 'history-item-source';
        sourceP.textContent = entry.sourceText;

        const translatedP = document.createElement('p');
        translatedP.className = 'history-item-translated';
        translatedP.textContent = entry.translatedText;

        item.appendChild(langLine);
        item.appendChild(sourceP);
        item.appendChild(translatedP);
        historyList.appendChild(item);
    });
}

function openHistoryOverlay() {
    historyOverlay.style.display = 'flex';
    // historyList has display:none right up until the line above, so
    // its scrollHeight isn't reliable yet - defer to the next frame,
    // once the browser has actually laid it out, before scrolling.
    requestAnimationFrame(function() {
        historyList.scrollTop = historyList.scrollHeight;
    });
}

function closeHistoryOverlay() {
    historyOverlay.style.display = 'none';
}

if (historyBtn) {
    historyBtn.addEventListener('click', openHistoryOverlay);
}
if (historyCloseBtn) {
    historyCloseBtn.addEventListener('click', closeHistoryOverlay);
}
if (historyOverlay) {
    // Click on the dimmed backdrop (not the panel itself) closes it.
    historyOverlay.addEventListener('click', function(e) {
        if (e.target === historyOverlay) closeHistoryOverlay();
    });
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && historyOverlay.style.display !== 'none') {
        closeHistoryOverlay();
    }
});

loadHistoryFromStorage();
renderHistory();

// ============================================================
// Current input/translation persistence: like the history above,
// this survives the reload triggered by swapping the translation
// direction, so the text on screen isn't lost just because the
// direction changed. Saved on every keystroke and whenever a
// translation completes; restored once on page load.
// ============================================================
const CURRENT_STATE_KEY = 'jaitalk_current_state';

function saveCurrentStateToStorage() {
    try {
        var state = {
            sourceText: userInput.value,
            hasResult: resultSection.style.display === 'block',
            translatedText: translatedTextContent.textContent,
            targetLabel: targetLabel.textContent
        };
        sessionStorage.setItem(CURRENT_STATE_KEY, JSON.stringify(state));
    } catch (err) {
        // Storage unavailable - current text just won't survive a
        // direction-swap reload; the page still works normally.
    }
}

function restoreCurrentStateFromStorage() {
    try {
        var raw = sessionStorage.getItem(CURRENT_STATE_KEY);
        if (!raw) return;
        var state = JSON.parse(raw);

        if (state.sourceText) {
            userInput.value = state.sourceText;
            autoResizeTextarea();
        }
        if (state.hasResult && state.translatedText) {
            targetLabel.textContent = state.targetLabel || targetLanguageName;
            translatedTextContent.textContent = state.translatedText;
            resultSection.style.display = 'block';
            updateAudioAvailabilityUI();
        }
    } catch (err) {
        // Malformed/unavailable storage - just start with a blank page.
    }
}

restoreCurrentStateFromStorage();

userInput.addEventListener('input', function() {
    autoResizeTextarea();

    if (translateDebounceTimer) clearTimeout(translateDebounceTimer);

    if (userInput.value.trim() === '') {
        resultSection.style.display = 'none';
        a11yStatus.textContent = '';
        speechSynthesis.cancel();
        saveCurrentStateToStorage();
        return;
    }

    saveCurrentStateToStorage();
    translateDebounceTimer = setTimeout(translateNow, 700);
});

userInput.addEventListener('keydown', function(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        if (translateDebounceTimer) clearTimeout(translateDebounceTimer);
        translateNow();
    }
});

function translateNow() {
    var text = userInput.value.trim();
    if (text === '') {
        resultSection.style.display = 'none';
        return;
    }

    var thisRequestId = ++translateRequestId;

    targetLabel.textContent = targetLanguageName;
    resultSection.style.display = 'block';
    resultSection.classList.add('is-loading');
    a11yStatus.textContent = 'Translating…';

    var formData = new FormData();
    formData.append('my_message', text);
    formData.append('dir', currentDir);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'main.php', true);
    xhr.onload = function() {
        // If the user kept typing and a newer request has already
        // gone out, ignore this now-stale response.
        if (thisRequestId !== translateRequestId) return;

        resultSection.classList.remove('is-loading');

        if (xhr.status === 200) {
            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                translatedTextContent.textContent = 'Sorry, something went wrong. Please try again.';
                a11yStatus.textContent = 'Sorry, something went wrong. Please try again.';
                return;
            }

            if (!response.success) {
                translatedTextContent.textContent = 'Sorry, something went wrong. Please try again.';
                a11yStatus.textContent = 'Sorry, something went wrong. Please try again.';
                return;
            }

            var translatedText = removeEmojis(removeNewlines(response.translated_text || ''));
            translatedTextContent.textContent = translatedText;
            a11yStatus.textContent = targetLanguageName + ' translation: ' + translatedText;
            updateAudioAvailabilityUI();
            addHistoryEntry(text, translatedText, sourceLanguageName, targetLanguageName);
            saveCurrentStateToStorage();
        } else {
            translatedTextContent.textContent = 'Sorry, something went wrong. Please try again.';
            a11yStatus.textContent = 'Sorry, something went wrong. Please try again.';
        }
    };
    xhr.onerror = function() {
        if (thisRequestId !== translateRequestId) return;
        resultSection.classList.remove('is-loading');
        translatedTextContent.textContent = 'Sorry, something went wrong. Please try again.';
        a11yStatus.textContent = 'Sorry, something went wrong. Please try again.';
    };
    xhr.send(formData);
}
</script>
