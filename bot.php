<?php
/**
 * Bot Class
 * 
 * @version 1.0.1
 * @license Premium License
 * 
 * (c) 2024 M Ali <onesender.id@gmail.com>
 */

final class Bot {
    
    const VERSION = '1.0.1';

    private static $instance;

    private $countryCode;
    private $sender;
    private $securityCode;
    private $geminiApiKey;
    private $inputText = [];
    private $inputIntent = [];
    private $tmpInputText;
    private $tmpInputIntent;
    private $from;
    private $text;
    private $prompt;

    private function __construct($url, $token, $countryCode = '62') {
        $this->countryCode = $countryCode;
        $this->sender = new BotSender($url, $token, $countryCode);
        $this->checkSecurity();
        $this->prompt = $this->getPrompt();
    }

    public static function setup($url, $token, $countryCode = '62') {
        if (is_null(self::$instance)) {
            self::$instance = new self($url, $token, $countryCode);
        }

        return self::$instance;
    }

    private function getPrompt() {
        return "You are dialogflow agent. Extract intent from text. Intent options are: %s. Output in structured json format: {intent: ''}. snake case. Input and output is bahasa indonesia. If not matched any intent response will be {intent: null}";
    }

    public function setPrompt($prompt) {
        $this->prompt = $prompt;
    }

    public function on($param) {
        $this->tmpInputText = strtolower($param);
        return $this;
    }

    public function onIntent($param) {
        $this->tmpInputIntent = $this->toSnakeCase($param);
        return $this;
    }

    public function reply($answer) {
        if (!empty($this->tmpInputText)) {
            $key = md5($this->tmpInputText);
            $this->inputText[$key] = $answer;
        } elseif (!empty($this->tmpInputIntent)) {
            $this->inputIntent[$this->tmpInputIntent] = $answer;
        }

        $this->tmpInputText = null;
        $this->tmpInputIntent = null;
    }

    public function send() {
        $request = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON');
        }

        $this->from = $this->getFrom($request);
        $this->text = $request['message_text'] ?? '';

        if (!$this->isValidWebhook($request)) {
            throw new Exception('Invalid webhook');
        }

        $hash = md5($this->text);
        if (isset($this->inputText[$hash])) {
            $message = $this->inputText[$hash];
            list($res, $error) = $this->sender->sendWa($this->from, $message);
            print_r($res);
            return;
        } elseif (!empty($this->inputIntent)) {
            $resIntent = $this->getGeminiResult();
            $message = $this->inputIntent[$resIntent] ?? false;
            if ($message) {
                list($res, $error) = $this->sender->sendWa($this->from, $message);
                print_r($res);
                return;   
            } 
        }

        print_r('Failed');
    }

    public function withSecurity($code) {
        $this->securityCode = $code;
    }

    private function checkSecurity() {
        if ($this->securityCode) {
            $headerKey = $_SERVER['HTTP_ONESENDER_KEY'] ?? false;
            if(!$headerKey || $this->securityCode !== $headerKey) {
                throw new Exception('Security header tidak valid');
            }
        }
    }

    public function withGeminiAI($code) {
        $this->geminiApiKey = $code;
    }

    private function getGeminiResult() {
        $systemPrompt = sprintf($this->prompt, implode(', ', array_keys($this->inputIntent)));

        $system = [
            'role' => 'model',
            'parts' => [['text' => $systemPrompt]]
        ];

        $user = [
            'role' => 'user',
            'parts' => [['text' => $this->text]]
        ];

        $prompt = ['contents' => [$system, $user]];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

        $headers = [
            'x-goog-api-key: ' . $this->geminiApiKey,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($prompt),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $error = curl_errno($curl) ? curl_error($curl) : '';

        curl_close($curl);

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid AI response');
        }

        $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? false;

        if (!$aiResponse) {
            throw new Exception('No AI response');
        }

        return $this->extractIntent($aiResponse);
    }

    private function extractIntent($str) {
        $pattern = '/\{(?:[^{}]|(?R))*\}/';
        preg_match($pattern, $str, $matches);
    
        if (!empty($matches)) {
            $jsonString = $matches[0];
            $data = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data['intent'] ?? false;
            }
        }

        return false;
    }

    private function getFrom($request) {
        return $request['is_group'] ?? false ? $request['from_group_id'] ?? '' : preg_replace('/[^0-9]/', '', $request['from_id'] ?? '');
    }

    private function isValidWebhook($request) {
        $isOutbox = $request['is_from_me'] ?? false;
        return !$isOutbox && !empty($this->from) && !empty($this->text);
    }

    private function toSnakeCase($input) {
        $input = str_replace(' ', '_', $input);
        $input = preg_replace_callback('/([a-z])([A-Z])/', function($matches) {
            return strtolower($matches[1]) . '_' . strtolower($matches[2]);
        }, $input);
        
        return strtolower($input);
    }
}

class BotSender {
    protected $url;
    protected $token;
    protected $countryCode;

    public function __construct($url, $token, $countryCode = '62') {
        $this->url = $url;
        $this->token = $token;
        $this->countryCode = $countryCode;
    }

    private function parsePhone(string $phone) {
        $type = strpos($phone, '@g.us') !== false ? 'group' : 'individual';

        if ($type === 'individual') {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (substr($phone, 0, 1) === "0") {
                $phone = $this->countryCode . substr($phone, 1);
            }
        }

        return [$phone, $type];
    }

    public function sendWa($phone, $text) {

        list($to, $recipientType) = $this->parsePhone($phone);
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];

        $message = [
            'to' => $to,
            'recipient_type' => $recipientType,
            'type' => 'text',
            'text' => [
                'body' => $text,
            ],
            'priority' => 50,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $error = curl_errno($curl) ? curl_error($curl) : '';

        curl_close($curl);

        $result = json_decode($response, true);
        return json_last_error() === JSON_ERROR_NONE ? [$result, $error] : [[], $error];
    }

}
