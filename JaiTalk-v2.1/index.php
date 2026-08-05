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
	'entoth' => array('source' => 'English', 'target' => 'Thai',    'source_code' => 'en-US', 'target_code' => 'th'),
	'thtoen' => array('source' => 'Thai',    'target' => 'English', 'source_code' => 'th-TH', 'target_code' => 'en'),
);

$dir = (isset($_GET['dir']) && array_key_exists($_GET['dir'], $directions)) ? $_GET['dir'] : 'entoth';
$current = $directions[$dir];
$other_dir = ($dir === 'entoth') ? 'thtoen' : 'entoth';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="robots" content="noindex, nofollow">

    <meta charset="utf-8">
    <title>JaiTalk Translator</title>
    <meta name="description" content="JaiTalk is a realtime English-Thai voice and text translator. Type or speak, tap the translation to hear it spoken aloud.">

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
        	border-top: 1px solid var(--border);
        	padding: 18px 20px 26px;
        	background-color: var(--bg);
        	display: flex;
        	flex-direction: column;
        	align-items: center;
        	gap: 26px;
            transition: background-color 0.3s ease, border-top-color 0.2s ease;
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
        <h1 class="topbar-title">JaiTalk Translator</h1>
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
        <a class="language-selector" href="index.php?dir=<?php echo htmlspecialchars($other_dir, ENT_QUOTES); ?>" aria-label="Switch to translating <?php echo htmlspecialchars($current['target'], ENT_QUOTES); ?> to <?php echo htmlspecialchars($current['source'], ENT_QUOTES); ?>">
            <span class="lang-box"><?php echo htmlspecialchars($current['source'], ENT_QUOTES); ?></span>
            <span class="swap-icon"><i class="fa fa-exchange" aria-hidden="true"></i></span>
            <span class="lang-box"><?php echo htmlspecialchars($current['target'], ENT_QUOTES); ?></span>
        </a>

        <button type="button" class="mic-btn-large" id="start-voicechat-btn" aria-label="Start Voicechat" title="Start Voicechat" aria-pressed="false">
            <i class="fa fa-microphone" aria-hidden="true"></i>
        </button>
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
const targetLanguageCode = "<?php echo addslashes($current['target_code']); ?>";
const mic_lang_code = "<?php echo addslashes($current['source_code']); ?>";

const speechRecognitionSupported = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
const speechSynthesisSupported = !!window.speechSynthesis;

let micShouldBeOn = false;

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
// Mic: tap to start listening; recognition auto-stops (and the
// mic button reverts) as soon as the browser detects the user has
// stopped speaking - no need to tap again to turn it off. Fixed to
// whichever language is currently the SOURCE language for this
// page load - flipping direction reloads the page.
// ============================================================
function handleEnd() {
    // Speech recognition ends automatically once the browser detects
    // the user has stopped speaking (silence timeout). Rather than
    // looping back into listening mode, treat that as "done" and
    // turn the mic off automatically.
    stop_recognition();
}

function restart_recognition_if_needed() {
    if (!micShouldBeOn) return;
    if (!window.recognition) return;
    window.recognition.removeEventListener('end', handleEnd);
    window.recognition.addEventListener('end', handleEnd);
    try {
        window.recognition.start();
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
            translateNow();
        }
    });

    window.recognition.start();

    userInput.placeholder = 'Listening...';

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
}

// Clears whatever text/translation is currently on screen. Called
// whenever the mic button is pressed (whether that starts a new
// listening session or manually stops one), so every mic tap begins
// from a blank slate.
function clear_input_and_results() {
    if (translateDebounceTimer) clearTimeout(translateDebounceTimer);
    translateRequestId++; // invalidate any in-flight translation response
    speechSynthesis.cancel();
    userInput.value = '';
    autoResizeTextarea();
    resultSection.style.display = 'none';
    translatedTextContent.textContent = '';
    a11yStatus.textContent = '';
}

function toggle_voicechat() {
    if (!speechRecognitionSupported) {
        alert('Sorry, voice recognition is not supported in this browser. Please try Google Chrome.');
        return;
    }

    clear_input_and_results();

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

userInput.addEventListener('input', function() {
    autoResizeTextarea();

    if (translateDebounceTimer) clearTimeout(translateDebounceTimer);

    if (userInput.value.trim() === '') {
        resultSection.style.display = 'none';
        a11yStatus.textContent = '';
        speechSynthesis.cancel();
        return;
    }

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
