<?php


// ADD YOUR OPENROUTER API KEY
// Get a key from https://openrouter.ai/keys and add it to the
// ebot_config.ini.txt file. Change the file name from
// ebot_config.ini.txt to ebot_config.ini
// This can be the SAME ebot_config.ini file E-Bot/T-Bot use, if
// JaiTalk is deployed alongside them and you want to share one key.

// *** IMPORTANT SECURITY NOTE ***
// Please secure your API key by moving the ebot_config.ini file
// to a folder located outside your website root folder, and
// updating the path below to match.
$path_to_config_ini = 'ebot_config.ini';

$url = "https://openrouter.ai/api/v1/chat/completions";

$site_url = "https://example.com";
$site_title = "JaiTalk";

// Same model JaiTalk's sibling apps E-Bot/T-Bot use.
$translation_agent_config = [
	"model_id" => "qwen/qwen3.5-flash-02-23",
	"provider" => "alibaba",
];

$temperature = 0.3; // Translation should be consistent, not creative.
$max_tokens = 300;

// ------------------------------------------------------------
// The one supported language pair, in both directions - MUST be
// kept in sync with the map in index.php. Used here purely as a
// validation allow-list plus the source of the display names that
// get built into the system prompt.
// ------------------------------------------------------------
$directions = array(
	'entoth' => array('source' => 'English', 'target' => 'Thai'),
	'thtoen' => array('source' => 'Thai',    'target' => 'English'),
);


// This function cleans and secures the user input
function test_input(&$data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = strip_tags($data);
	return $data;
}


// This code is triggered when the user submits a message.
// The form data arrives here via Ajax.
if (isset($_REQUEST["my_message"]) && empty($_REQUEST["robotblock"])) {

	$user_message = isset($_REQUEST["my_message"]) ? $_REQUEST["my_message"] : '';
	$user_message = test_input($user_message);

	$dir = isset($_REQUEST["dir"]) ? $_REQUEST["dir"] : '';
	if (!array_key_exists($dir, $directions)) {
		echo json_encode(['success' => false, 'error' => 'Unsupported translation direction.']);
		exit;
	}

	if (trim($user_message) === '') {
		echo json_encode(['success' => false, 'error' => 'Empty message.']);
		exit;
	}

	$source_language = $directions[$dir]['source'];
	$target_language = $directions[$dir]['target'];

	//---------------------------
	// Run the translation agent
	//---------------------------
	$translation_agent_system_message = <<<EOT
You are a highly skilled {$source_language} to {$target_language} translator. You will be given {$source_language} text. Your task is to translate it into {$target_language}. Keep the tone and meaning of the original text. Return your translated text.
	Respond in a consistent format. Output a JSON string with the following schema:
{
"translation": "<Your translated version of the text.>"
}

EOT;

	$translated_response_list = run_agent_without_memory(
		$translation_agent_system_message,
		$user_message,
		$translation_agent_config['model_id'],
		$translation_agent_config['provider']
	);

	if ($translated_response_list[1] === 'api_error') {
		echo json_encode(['success' => false, 'error' => 'api_error']);
		exit;
	}

	// Process the response
	if ($translated_response_list[0] != "is_plain_text") {
		// It is json
		$translated_text = $translated_response_list[1]["translation"];
	} else {
		// It is plain text
		$translated_text = $translated_response_list[1];
	}

	$translated_text = replaceItemsInString($translated_text);

	$response = array(
		'success' => true,
		'source_text' => $user_message,
		'translated_text' => $translated_text,
		'source_language' => $source_language,
		'target_language' => $target_language,
	);

	echo json_encode($response);
}


// ============================================================
// Helper functions
// (copied from E-Bot's/T-Bot's main.php - same OpenRouter/Qwen
// plumbing, unchanged so all three apps keep behaving identically
// against the same API.)
// ============================================================


/**
 * Load configuration from a file
 */
function load_config($file) {
	if (!file_exists($file)) {
		throw new Exception("Configuration file not found: $file");
	}
	return parse_ini_file($file, true);
}


/**
 * Convert a simple ["role" => "user", "parts" => [["text" => "..."]]]
 * message history into the OpenAI-compatible "messages" array that
 * OpenRouter expects.
 */
function build_openrouter_messages($system_message, $message_history) {
	$messages = [];
	$messages[] = ["role" => "system", "content" => $system_message];

	foreach ($message_history as $turn) {
		$role = (isset($turn['role']) && $turn['role'] === 'model') ? 'assistant' : ($turn['role'] ?? 'user');

		$text = '';
		if (isset($turn['parts']) && is_array($turn['parts'])) {
			foreach ($turn['parts'] as $part) {
				if (isset($part['text'])) {
					$text .= $part['text'];
				}
			}
		}

		$messages[] = ["role" => $role, "content" => $text];
	}

	return $messages;
}


/**
 * Make an API call to OpenRouter, with retry support
 */
function make_api_call($system_message, $message_history, $model_id, $provider, $max_retries = 3) {
	global $path_to_config_ini;
	global $url;
	global $temperature;
	global $max_tokens;
	global $site_url;
	global $site_title;

	$timestamp = date('Y-m-d H:i:s');
	$file_path = "php-errors.log";

	try {
		$config = load_config($path_to_config_ini);
	} catch (Exception $e) {
		error_log($timestamp . ' ' . $e->getMessage(), 3, $file_path);
		return 'Failed to load configuration.';
	}

	$apiKey = $config['api']['API_KEY'] ?? '';
	if (empty($apiKey) || empty($url)) {
		error_log($timestamp . ' API key or URL not configured properly.', 3, $file_path);
		return 'API key or URL not configured properly.';
	}

	$messages = build_openrouter_messages($system_message, $message_history);

	$data = [
		"model" => $model_id,
		"provider" => [
			"order" => [$provider],
			"allow_fallbacks" => false
		],
		"messages" => $messages,
		"temperature" => $temperature,
		"max_tokens" => $max_tokens,
		"reasoning" => [
			"effort" => "none"
		],
	];
	$headers = [
		"Authorization: Bearer {$apiKey}",
		"Content-Type: application/json",
		"HTTP-Referer: {$site_url}",
		"X-Title: {$site_title}"
	];

	$attempt = 0;
	while ($attempt < $max_retries) {
		$attempt++;

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);
		$httpStatusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curlError = curl_errno($curl) ? curl_error($curl) : null;

		curl_close($curl);

		if ($curlError) {
			error_log($timestamp . " Attempt $attempt - cURL error: $curlError\n", 3, $file_path);
		} elseif ($httpStatusCode >= 400) {
			error_log($timestamp . " Attempt $attempt - HTTP error: $httpStatusCode - Response: $result\n", 3, $file_path);
		} else {
			$decodedResult = json_decode($result, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				return $decodedResult;
			} else {
				error_log($timestamp . " Attempt $attempt - JSON decode error: " . json_last_error_msg() . "\n", 3, $file_path);
			}
		}

		sleep(1);
	}

	return 'api_error';
}


/**
 * Extract text from an OpenRouter (OpenAI-compatible) API response
 */
function extract_text_from_response($response) {
	if (isset($response["choices"][0]['message']['content'])) {
		return $response["choices"][0]['message']['content'];
	} elseif (isset($response['error'])) {
		$error_code = $response['error']['code'] ?? '';
		$error_message = $response['error']['message'] ?? 'Unknown error';
		return "Error: " . $error_code . "<br>" . $error_message;
	} else {
		return "Sorry. Something went wrong. Please try again.";
	}
}


/**
 * Run agent without memory (translation is always a one-off
 * request - there's no conversation history to carry between
 * calls).
 */
function run_agent_without_memory($system_message, $prompt, $model_id, $provider) {
	$my_message1 = ["text" => $prompt];
	$parts_list = [$my_message1];
	$message_history = [["role" => "user", "parts" => $parts_list]];

	$response = make_api_call($system_message, $message_history, $model_id, $provider);

	if ($response == "api_error") {
		$response = make_api_call($system_message, $message_history, $model_id, $provider);
	}

	if ($response != "api_error") {
		$response_text = extract_text_from_response($response);
		if ($response_text == "Sorry. Something went wrong. Please try again.") {
			$response = make_api_call($system_message, $message_history, $model_id, $provider);
		}
	}

	if ($response != "api_error") {
		$response_text = extract_text_from_response($response);
		if ($response_text == "Sorry. Something went wrong. Please try again.") {
			$response = make_api_call($system_message, $message_history, $model_id, $provider);
		}
	}

	if ($response != "api_error") {
		$response_text = extract_text_from_response($response);
		$output_type = check_output_type($response_text);

		if ($output_type == "is_json_string") {
			$output_text = json_decode($response_text, true);
		} elseif ($output_type == "is_json_object") {
			$response_text = json_encode($response_text);
			$output_text = json_decode($response_text, true);
		} else {
			$output_text = $response_text;
		}

		return [$output_type, $output_text];
	} else {
		return ["is_plain_text", "api_error"];
	}
}


/**
 * Check the output type
 */
function check_output_type($output) {
	if (is_object($output)) {
		return "is_json_object";
	} elseif (is_string($output)) {
		$decoded = json_decode($output, true);
		if ($decoded !== null) {
			return "is_json_string";
		} else {
			return "is_plain_text";
		}
	}
}


// Function to remove items from a JSON string before it gets
// displayed on the page (strips the JSON scaffolding the model
// sometimes leaves in, e.g. stray ```json fences or the
// "translation": " key/prefix).
function replaceItemsInString($inputString) {
	$itemsToReplace = array("```", "json", "{", "}", '"translation": "', "#");

	$modifiedString = $inputString;
	foreach ($itemsToReplace as $item) {
		$modifiedString = str_replace($item, "", $modifiedString);
	}

	$modifiedString = trim($modifiedString);

	// Only strip a trailing quote character if one is actually left
	// over (from removing the '"translation": "' prefix above).
	if (substr($modifiedString, -1) === '"') {
		$modifiedString = substr($modifiedString, 0, -1);
	}

	return $modifiedString;
}

?>
