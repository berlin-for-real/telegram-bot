<?php
if ($_SERVER['REQUEST_URI'] !== '/bot.php') {
    require __DIR__ . '/bot.php';
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

$token = '7869277159:AAGRmc517KH-yKfCONui2KpNr6rQMMsyGUA';
$bot = new BotApi($token);
$port = getenv('PORT') ?: 80;

// Load admin list from JSON file
$adminList = json_decode(file_get_contents('admins.json'), true) ?? [];
$superAdmins = ['@MujTaBaNext', '@SAVANPATELSP']; // Array with multiple super admins

// Load language list from JSON file
$languages = json_decode(file_get_contents('languages.json'), true) ?? [];
$itemsPerPage = 20;

$startMessage = "Hello!\n\nThis bot will translate messages in your group. It supports 134 languages and has various modes.\n\nTo get started, add it to the group chat.";
$startKeyboard = new InlineKeyboardMarkup([
    [['text' => ' Add to Group', 'url' => 'https://t.me/@TopTestGbot?startgroup=invite']]
]);

$settingsMessage = "⚙️  Bot settings:";
$settingsKeyboard = new InlineKeyboardMarkup([
    [['text' => 'Change group language', 'callback_data' => 'change_language']],
    [['text' => 'Change translation mode', 'callback_data' => 'change_translation_mode']]
]);

$translationModeMessage = "Change translation mode\nHere you can customize when the bot will translate messages ✨\n\n" .
    "Auto — translates all messages that require it\n" .
    "Forwards — translates only forwarded messages that require it\n" .
    "Linked channel — translates only posts from linked channel that require it.\n" .
    "Manual — translates only by replying to a message with @TopTestGbot ! \n\n" .
    "THIS PART IS NOT DONE YET !";

$translationModeKeyboard = new InlineKeyboardMarkup([
    [['text' => 'Auto', 'callback_data' => 'mode_auto']],
    [['text' => 'Only forwards', 'callback_data' => 'mode_forwards']],
    [['text' => 'Linked channel', 'callback_data' => 'mode_linked']],
    [['text' => 'Manual', 'callback_data' => 'mode_manual']],
    [['text' => '❌ Back', 'callback_data' => 'back']]
]);

// Logging function to log bot usage
function logUsage($username, $chatId, $language, $action) {
    $logData = [
        'username' => $username,
        'chat_id' => $chatId,
        'language' => $language,
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    $logFile = 'logs.json';
    $logs = json_decode(file_get_contents($logFile), true) ?? [];
    $logs[] = $logData;
    file_put_contents($logFile, json_encode($logs));
}
function getPageKeyboard($page) {
    global $languages, $itemsPerPage;
    $start = $page * $itemsPerPage;
    $end = min($start + $itemsPerPage, count($languages));

    $buttons = [];
    for ($i = $start; $i < $end; $i += 2) {
        $row = [];
        for ($j = 0; $j < 2; $j++) {
            if ($i + $j < $end) {
                $row[] = ['text' => $languages[$i + $j], 'callback_data' => 'language_' . $languages[$i + $j]];
            }
        }
        $buttons[] = $row;
    }

    $navigationButtons = [];
    if ($page > 0) {
        $navigationButtons[] = ['text' => '⬅️', 'callback_data' => 'prev_' . ($page - 1)];
    }
    $navigationButtons[] = ['text' => '❌ Back', 'callback_data' => 'back'];
    if ($end < count($languages)) {
        $navigationButtons[] = ['text' => '➡️', 'callback_data' => 'next_' . ($page + 1)];
    }
    $buttons[] = $navigationButtons;

    return new InlineKeyboardMarkup($buttons);
}

$data = file_get_contents("php://input");
$update = json_decode($data, true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'];
    $username = $update['message']['from']['username'] ?? '';

    if (!in_array("@$username", $adminList)) {
        $bot->sendMessage($chatId, "Private Bot!");
    } else {
        if ($text === '/start') {
            $bot->sendMessage($chatId, $startMessage, null, false, null, $startKeyboard);
        } elseif ($text === '/settings') {
            $bot->sendMessage($chatId, $settingsMessage, null, false, null, $settingsKeyboard);
        } elseif ($text === '/admin') {
            if (in_array("@$username", $superAdmins)) {
                $adminMessage = "List of admins:\n\n" . implode("\n\n", array_map(fn($admin) => str_replace('@@', '@', $admin), $adminList));
                $bot->sendMessage($chatId, $adminMessage);
            } else {
                $bot->sendMessage($chatId, "You don't have the right to use this command!");
            }
        } elseif (strpos($text, 'set:language=') !== false) {
            $bot->sendMessage($chatId, "Done!", null, false, $update['message']['message_id']);
        }
    }
    
    // Log the text message action
    logUsage($username, $chatId, null, $text);
       // Adding admin functionality to add and remove
       if (preg_match('/^\/admin @(\w+)$/', $text, $matches)) {
        if (in_array("@$username", $superAdmins)) {
            $newAdmin = '@' . $matches[1];
            if (!in_array($newAdmin, $adminList)) {
                $adminList[] = $newAdmin;
                file_put_contents('admins.json', json_encode($adminList)); // Save updated admin list
                $bot->sendMessage($chatId, "$newAdmin has been added to the admin list.");
            } else {
                $bot->sendMessage($chatId, "$newAdmin is already an admin.");
            }
        } else {
            $bot->sendMessage($chatId, "You don't have the right to use this command!");
        }
    } elseif (preg_match('/^\/unadmin @(\w+)$/', $text, $matches)) {
        if (in_array("@$username", $superAdmins)) {
            $adminToRemove = '@' . $matches[1];
            if (($key = array_search($adminToRemove, $adminList)) !== false) {
                unset($adminList[$key]);
                $adminList = array_values($adminList); // Reindex array
                file_put_contents('admins.json', json_encode($adminList)); // Save updated admin list
                $bot->sendMessage($chatId, "$adminToRemove has been removed from the admin list.");
            } else {
                $bot->sendMessage($chatId, "$adminToRemove is not in the admin list.");
            }
        } else {
            $bot->sendMessage($chatId, "You don't have the right to use this command!");
        }
    }
}
if (isset($update['callback_query'])) {
    $callbackData = $update['callback_query']['data'];
    $callbackChatId = $update['callback_query']['message']['chat']['id'];
    $callbackMessageId = $update['callback_query']['message']['message_id'];
    $username = $update['callback_query']['from']['username'] ?? '';

    // Delete previous message when pressing any button in settings
    $bot->deleteMessage($callbackChatId, $callbackMessageId);

    if ($callbackData === 'select_group') {
        $bot->sendMessage($callbackChatId, "Please select a group to add the bot:", null, false, null, $startKeyboard);
    } elseif (strpos($callbackData, 'select_') === 0) {
        $groupId = str_replace('select_', '', $callbackData);
        $bot->sendMessage($callbackChatId, "You have selected the group with ID: $groupId");
    } elseif ($callbackData === 'change_language') {
        $bot->sendMessage($callbackChatId, "Here you can setup primary language for your group:", null, false, null, getPageKeyboard(0));
    } elseif ($callbackData === 'change_translation_mode') {
        $bot->sendMessage($callbackChatId, $translationModeMessage, null, false, null, $translationModeKeyboard);
    } elseif (strpos($callbackData, 'next_') === 0 || strpos($callbackData, 'prev_') === 0) {
        $page = intval(explode('_', $callbackData)[1]);
        $bot->sendMessage($callbackChatId, "Here you can setup primary language for your group:", null, false, null, getPageKeyboard($page));
    } elseif (strpos($callbackData, 'language_') === 0) {
        $language = str_replace('language_', '', $callbackData);

        // Log the language selection action
        logUsage($username, $callbackChatId, $language, 'Language selection');

        $applyKeyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => '✅ Apply', 
                    'switch_inline_query' => "set:language=$language"
                ],
                [
                    'text' => '❌ Cancel', 
                    'callback_data' => 'cancel'
                ]
            ]
        ]);
        $bot->sendMessage($callbackChatId, "Everything is done. To apply settings, click on Apply and choose a chat, or click Cancel.", null, false, null, $applyKeyboard);
    } elseif ($callbackData === 'apply') {
        $bot->editMessageText($callbackChatId, $callbackMessageId, "Done!");
    } elseif ($callbackData === 'cancel') {
        $bot->sendMessage($callbackChatId, $settingsMessage, null, false, null, $settingsKeyboard);
    } elseif ($callbackData === 'back') {
        $bot->sendMessage($callbackChatId, $settingsMessage, null, false, null, $settingsKeyboard);
    }
elseif ($callbackData === 'apply') {
    $bot->editMessageText($callbackChatId, $callbackMessageId, "Done!");
} elseif ($callbackData === 'cancel') {
    $bot->sendMessage($callbackChatId, $settingsMessage, null, false, null, $settingsKeyboard);
} elseif ($callbackData === 'back') {
    $bot->sendMessage($callbackChatId, $settingsMessage, null, false, null, $settingsKeyboard);
} elseif ($callbackData === 'mode_auto') {
    $bot->sendMessage($callbackChatId, "Auto translation mode has been selected.");
} elseif ($callbackData === 'mode_forwards') {
    $bot->sendMessage($callbackChatId, "Forward-only translation mode has been selected.");
} elseif ($callbackData === 'mode_linked') {
    $bot->sendMessage($callbackChatId, "Linked channel translation mode has been selected.");
} elseif ($callbackData === 'mode_manual') {
    $bot->sendMessage($callbackChatId, "Manual translation mode has been selected.");
}
}

// New Logic for Forward-Only Translation Mode
if (isset($update['message']['forward_from'])) {
    // This message is forwarded
    $forwardedText = $update['message']['text'];
    
    // TODO: Add translation logic here using your translation API keys
    // For now, let's just simulate the translation with a placeholder
    $translatedText = "Translated: " . $forwardedText; // Placeholder for translation logic

    // Apply formatting style (assuming formatting logic is defined in a function)
    // For example, let's assume we have a function called applyFormatting
    $formattedText = applyFormatting($translatedText); // Implement your formatting logic here

    // Send the formatted message back to the user
    $bot->sendMessage($chatId, $formattedText);
}

// Function to detect and preserve formatting (bold, italic, underline, etc.) and translate
function detectAndTranslateFormattedText($text, $targetLanguage) {
    // Use regular expressions to detect and split the text with formatting
    $formattedText = preg_replace_callback(
        '/(\*\*|__|~~|`|https:\/\/[^\s]+|\[[^\]]+\]\([^\)]+\))|([^\*\_\~\`\[]+)/',
        function($matches) use ($targetLanguage) {
            if (isset($matches[1])) {
                // Handle formatted text or links
                if (preg_match('/\*\*(.*?)\*\*/', $matches[1])) {
                    // Bold
                    return '**' . translate($matches[1], $targetLanguage) . '**';
                } elseif (preg_match('/__(.*?)__/', $matches[1])) {
                    // Italic
                    return '__' . translate($matches[1], $targetLanguage) . '__';
                } elseif (preg_match('/~~(.*?)~~/', $matches[1])) {
                    // Strikethrough
                    return '~~' . translate($matches[1], $targetLanguage) . '~~';
                } elseif (preg_match('/`(.*?)`/', $matches[1])) {
                    // Monospace/code block
                    return '`' . $matches[1] . '`'; // Do not translate code blocks
                } elseif (preg_match('/\[(.*?)\]\((.*?)\)/', $matches[1], $linkParts)) {
                    // Inline link: [display text](url)
                    $translatedText = translate($linkParts[1], $targetLanguage);
                    return '[' . $translatedText . '](' . $linkParts[2] . ')';
                }
            } else if (isset($matches[2])) {
                // Non-formatted text
                return translate($matches[2], $targetLanguage);
            }
        },
        $text
    );

    return $formattedText;
}

// Log user actions for super admin
function logUserAction($username, $chatId, $groupId, $languageUsed, $originalText, $translatedText) {
    $logData = [
        'username' => $username,
        'chat_id' => $chatId,
        'group_id' => $groupId,
        'language_used' => $languageUsed,
        'original_text' => $originalText,
        'translated_text' => $translatedText,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Save the log entry in JSON format
    file_put_contents('user_actions_log.json', json_encode($logData) . PHP_EOL, FILE_APPEND);
}

if (isset($update['message']['forward_from'])) {
    // This message is forwarded
    $forwardedText = $update['message']['text'];
    
    // TODO: Add target language logic here; using a placeholder for now
    $targetLanguage = 'en'; // Placeholder for target language

    // Translate the forwarded message while preserving formatting
    $translatedText = detectAndTranslateFormattedText($forwardedText, $targetLanguage); // Use the provided logic

    // Log user actions for super admin
    logUserAction($username, $chatId, null, $targetLanguage, $forwardedText, $translatedText);

    // Send the formatted message back to the user
    $bot->sendMessage($chatId, $translatedText);
}
?>

