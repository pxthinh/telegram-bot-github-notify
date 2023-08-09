<?php

namespace TelegramGithubNotify\App\Services;

use TelegramGithubNotify\App\Models\Setting;

class SettingService extends AppService
{
    protected Setting $setting;

    protected array $settingConfig = [];

    public function __construct()
    {
        parent::__construct();
        $this->setting = new Setting();
        $this->settingConfig = $this->setting->getSettingConfig();
    }

    /**
     * @return void
     */
    public function settingHandle(): void
    {
        $this->sendMessage(
            view('tools.settings'),
            ['reply_markup' => $this->settingMarkup()]
        );
    }

    /**
     * @return array[]
     */
    public function settingMarkup(): array
    {
        return [
            [
                $this->telegram->buildInlineKeyBoardButton(
                    $this->settingConfig['is_notified']
                        ? '✅ Github notification' : 'Github notification',
                    '',
                    $this->setting::SETTING_IS_NOTIFIED
                ),
            ],
            [
                $this->telegram->buildInlineKeyBoardButton(
                    $this->settingConfig['all_events_notify']
                        ? '✅ Enable All Events Notify'
                        : 'Enable All Events Notify',
                    '',
                    $this->setting::SETTING_ALL_EVENTS_NOTIFY
                ),
                $this->telegram->buildInlineKeyBoardButton(
                    '⚙ Custom individual events',
                    '',
                    $this->setting::SETTING_CUSTOM_EVENTS
                ),
            ],
            [
                $this->telegram->buildInlineKeyBoardButton(
                    '🔙 Back to menu',
                    '',
                    $this->setting::SETTING_PREFIX . '.back.menu'
                ),
            ]
        ];
    }

    /**
     * @param string $callback
     * @return void
     */
    public function settingCallbackHandler(string $callback): void
    {
        if (str_contains($callback, $this->setting::SETTING_CUSTOM_EVENTS)) {
            (new EventService())->eventHandle($callback);
            return;
        }

        $callback = str_replace($this->setting::SETTING_PREFIX, '', $callback);

        if ($this->updateSettingItem($callback, !$this->settingConfig[$callback])) {
            $this->editMessageReplyMarkup([
                'reply_markup' => $this->settingMarkup(),
            ]);
        } else {
            $this->answerCallbackQuery('Something went wrong!');
        }
    }

    /**
     * @param string $settingName
     * @param $settingValue
     * @return bool
     */
    public function updateSettingItem(string $settingName, $settingValue = null): bool
    {
        $keys = explode('.', $settingName);
        $lastKey = array_pop($keys);
        $nestedSettings = &$this->settingConfig;

        foreach ($keys as $key) {
            if (!isset($nestedSettings[$key]) || !is_array($nestedSettings[$key])) {
                return false;
            }
            $nestedSettings = &$nestedSettings[$key];
        }

        if (isset($nestedSettings[$lastKey])) {
            $nestedSettings[$lastKey] = $settingValue ?? !$nestedSettings[$lastKey];
            if ($this->saveSettingsToFile()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    private function saveSettingsToFile(): bool
    {
        if (file_exists($this->setting::SETTING_FILE)) {
            $json = json_encode($this->settingConfig, JSON_PRETTY_PRINT);
            file_put_contents($this->setting::SETTING_FILE, $json, LOCK_EX);

            return true;
        }

        return false;
    }
}
