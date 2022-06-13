<?php

namespace Justinianus\StdioLog\Helpers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StdioLogHelper extends Log
{
    const DEBUG     = 'debug';
    const INFO      = 'info';
    const NOTICE    = 'notice';
    const WARNING   = 'warning';
    const ERROR     = 'error';
    const CRITICAL  = 'critical';
    const ALERT     = 'alert';
    const EMERGENCY = 'emergency';

    const LIMIT_FILE = 5000;
    const RANDOM_LENGTH = 10;

    //  Handle BE information and return log ID for FE
    public static function writeFileLog($level, $channel, $exception, $request, $sendSlack = true)
    {
        $logId = self::generateID();

        try {
            //  Store BE exception and request information in log file
            $fileDir = self::getLogPath($channel, $logId);
            $contentLog = json_encode(self::fieldMessageLogBE($exception, $request), JSON_PRETTY_PRINT);
            self::log_put_contents($fileDir, $contentLog);

            if ($sendSlack) {
                //  Option send Slack message
                $message = self::getMessage($channel, $logId, $exception->getMessage());
                $context = self::fieldContextSlackLogBE($request, $exception);
                parent::stack(['slack-log'])->{$level}($message, $context);
            }
        } catch (\Throwable $th) {
            parent::error("Error:" . $th->getMessage() . " \nLine:" . $th->getCode());
        }

        //  Return encrypted log ID
        $encryptedId = self::encodeLogID($logId);
        return $encryptedId;
    }

    //  Handle FE additional information into log file
    public static function callback($request, $sendSlack = true,
        $level = StdioLogHelper::ERROR, $channel = 'stdio-log')
    {
        try {
            //  Receive log ID or generate new
            $logId = isset($request->id) ? self::decodeLogID($request->id) : self::generateID();
            
            if (!isset($request->id) && $sendSlack) {
                //  Send Slack message if BE not
                $message = self::getMessage($channel, $logId);
                $context = self::fieldContextSlackLogFE($request);
                parent::stack(['slack-log'])->{$level}($message, $context);
            }

            //  Store FE information in log file
            $fileDir = self::getLogPath($channel, $logId);
            $contentLog = self::fieldMessageLogFE($request, $fileDir);
            self::log_put_contents($fileDir, $contentLog);
        } catch (\Throwable $th) {
            parent::error("Error:" . $th->getMessage() . " \nLine:" . $th->getCode());
        }
    }

    //  Write FE information into log file and format (if any)
    private static function fieldMessageLogFE($request, $fileDir)
    {
        $message = "";

        if (file_exists($fileDir)) {
            //  If exist before, rewrite log file pretty
            $messageBE = json_decode(self::getOriginFileData($fileDir), true);
            $message .= ' ------------ BACK-END ------------ ' . PHP_EOL;
            foreach ($messageBE as $name => $value) {
                $message .= '- ' . $name . ': ' . $value . PHP_EOL;
            }
        }

        //  Write FE information except log ID
        $message .= ' ------------ FRONT-END ------------ ' . PHP_EOL;
        $all = $request->all();
        foreach ($all as $key => $value) {
            if ($key != 'id') {
                $message .= '- ' . ucwords($key) . ': ' . $value . PHP_EOL;
            }
        }

        return $message;
    }

    //  Write BE information into log file
    private static function fieldMessageLogBE($exception, $request)
    {
        $message = [
            'Message' => $exception->getMessage(),
            'Type' => get_class($exception),
            'Code' => ($exception->getCode() ?? 500) . ' '
                . (Response::$statusTexts[$exception->getCode()] ?? null),
            'Method' => $request->method(),
            'API' => $request->fullUrl(),
            'Time' => date('Y-m-d H:i:s'),
            'File' => ($exception->getFile() ?? null),
            'Line' => ($exception->getLine() ?? null),
            'User' => ($request->user()->id ?? null)  . ' '
                . ($request->user()->name ?? 'Unauthorized'),
            'IP' => $request->ip(),
            'Body' => $request->getContent()
        ];
        return $message;
    }

    //  BE context field of Slack message
    private static function fieldContextSlackLogBE($request, $exception)
    {
        $context = [];
        $context['Back-end'] =
            '- Method: ' . $request->method() . PHP_EOL .
            '- API: ' . $request->url() . PHP_EOL .
            '- Time: ' . date('Y-m-d H:i:s') . PHP_EOL .
            '- File: ' . ($exception->getFile() ?? null) . PHP_EOL .
            '- Line: ' . ($exception->getLine() ?? null);
        return $context;
    }

    //  FE context field of Slack message
    private static function fieldContextSlackLogFE($request)
    {
        $context = [];
        $contextFE = "";

        $all = $request->all();
        foreach ($all as $key => $value) {
            $contextFE .= '- ' . ucwords($key) . ': ' . $value . PHP_EOL;
        }

        $context['Front-end'] = $contextFE;
        return $context;
    }

    //  Log ID format (YYYY-mm-dd_HH-ii-ss_xxxxxxxxxx)
    private static function generateID()
    {
        return date("Y-m-d_H-i-s") . '_' . Str::random(self::RANDOM_LENGTH);
    }

    //  Format data from log file
    private static function getOriginFileData($fileDir)
    {
        return preg_replace('/[\x00-\x1F\x80-\xFF]/', '', file_get_contents($fileDir));
    }

    //  Get log path based on channel and log ID
    private static function getLogPath($channel, $logId)
    {
        $path = storage_path() . '/logs/' . $channel;

        //  Create channel folder if not exists
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $path .=  '/'. $logId . '.log';
        return $path;
    }

    //  Message field of Slack message
    private static function getMessage($channel, $logId, $message = null)
    {
        $dir = self::encodeLogID('../storage/logs/' . $channel . '/' . $logId . '.log');
        $hyperlink = env('APP_URL', 'http://localhost') . '/log-detail?dir=' . $dir;
        $message .= PHP_EOL . 'ID: <' . $hyperlink . '|' . $dir . '>';
        return $message;
    }

    //  Store information in log file, remove log file when exceeds limit
    private static function log_put_contents($fileDir, $content)
    {
        file_put_contents($fileDir, $content);

        $files = glob(dirname($fileDir) . '/*');
        while (count($files) > self::LIMIT_FILE) {
            unlink(reset($files));
            array_shift($files);
        }
    }

    /**
     * Encrypt a message
     * 
     * @param string $url - url to encrypt
     * @return string
     */
    public static function encodeLogID(string $url) : string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($url));
    }

    /**
     * Decrypt a message
     * 
     * @param string $encrypted - message encrypted with encodeLogID()
     * @return string
     */
    public static function decodeLogID(string $encrypted) : string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $encrypted));
    }
}