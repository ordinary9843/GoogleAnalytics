<?php

namespace App\Classes;

use Google_Client;
use Google_Service_Analytics;
use Google_Service_Analytics_Accounts;
use Google_Service_Analytics_Profile;
use Google_Service_Analytics_Webproperty;
use Google_Exception;
use Psr\SimpleCache\InvalidArgumentException;
use Exception;
use Log;

class GoogleAnalytics
{
    /** @var string cache 訪客量 */
    const CACHE_USERS = 'google_analytics';

    /** @var string cache 訪客量天數 */
    const CACHE_USERS_DAYS = 7;

    /** @var string 訪客量計算起始日期 */
    const USERS_START_AT = '2020-01-01';

    /** @var string 專案名稱 */
    protected $profileName = 'Project';

    /** @var Google_Client Google Client */
    protected $client = null;

    /** @var Google_Service_Analytics Google Service Analytics */
    protected $analytics = null;

    /** @var string 專案帳號 */
    protected $accountId = null;

    /**
     * 建構子
     *
     * @return mixed
     *
     * @throws Google_Exception
     */
    public function __construct()
    {
        $this->setGoogleClient();
        $this->setGoogleAnalytics();
    }

    /**
     * 設定 Google Client
     *
     * @return void
     *
     * @throws Google_Exception
     */
    public function setGoogleClient()
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Google Analytics Reporting API');
        $this->client->setAuthConfig(storage_path('google/secret/analytics.json'));
        $this->client->setScopes([
            Google_Service_Analytics::ANALYTICS_READONLY
        ]);
    }

    /**
     * 設定 Google Analytics
     * 
     * @return void
     */
    public function setGoogleAnalytics()
    {
        $this->analytics = new Google_Service_Analytics($this->client);
    }

    /**
     * 取得帳號
     * 
     * @return Google_Service_Analytics_Accounts
     * 
     * @throws Exception
     */
    public function getAccounts()
    {
        $accounts = $this->analytics
            ->management_accounts
            ->listManagementAccounts();

        if (empty($accounts->getItems())) {
            throw new Exception('無法取得帳號');
        }

        return $accounts;
    }

    /**
     * 取得項目
     *
     * @param Google_Service_Analytics_Accounts $accounts
     *
     * @return Google_Service_Analytics_Webproperty
     *
     * @throws Exception
     */
    public function getItems(Google_Service_Analytics_Accounts $accounts)
    {
        $accountItems = $accounts->getItems();
        $this->accountId = $accountItems[0]->getId();
        
        $properties = $this->analytics
            ->management_webproperties
            ->listManagementWebproperties($this->accountId);
        $items = $properties->getItems();

        if (empty($items)) {
            throw new Exception('無法取得項目');
        }

        return $items;
    }

    /**
     * 取得專案項目
     *
     * @param array $items
     *
     * @return Google_Service_Analytics_Profile
     *
     * @throws Exception
     */
    public function getProfiles(array $items)
    {
        $profileKey = -1;
        foreach ($items as $key => $value){
            if (!strcmp($value->getName(), $this->profileName)){
                $profileKey = $key;
                break;
            }
        }
        
        if ($profileKey === -1) {
            throw new Exception('無法取得專案');
        }

        $propertyId = $items[$profileKey]->getId();
        $profiles = $this->analytics
            ->management_profiles
            ->listManagementProfiles($this->accountId, $propertyId);
        $profileItems = $profiles->getItems();

        if (empty($profileItems)) {
            throw new Exception('無法取得專案項目');
        }

        return $profileItems;
    }

    /**
     * 取得專案項目編號
     *
     * @param array $profiles
     *
     * @return string
     *
     * @throws Exception
     */
    public function getProfileId(array $profiles)
    {
        if (empty($profiles) || !isset($profiles[0])) {
            throw new Exception('無法取得專案編號');
        }

        $profileId = $profiles[0]->getId();

        return $profileId;
    }

    /**
     * 依日期區間取得訪客數
     *
     * @param string $profileId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getUsersByDateRange(string $profileId)
    {
        $params = [
            'dimensions' => 'ga:medium',
            'sort' => '-ga:users'
        ];

        $result = $this->analytics
            ->data_ga
            ->get('ga:' . $profileId, self::USERS_START_AT, date('Y-m-d'), 'ga:users', $params);

        $rows = $result->getRows();

        if (empty($rows)) {
            throw new Exception('無法取得訪客數');
        }

        $users = [
            'total' => 0
        ];
        foreach ($rows as $row) {
            $type = $row[0];
            $count = $row[1];

            $users[$type] = $count;
            $users['total'] += $count;
        }

        return $users;
    }

    /**
     * 取得已快取訪客數
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getCacheUsersByDateRange()
    {
        $cacheKey = cacheKey(self::CACHE_USERS);

        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $users = [];
        try {
            $accounts = $this->getAccounts();
            $items = $this->getItems($accounts);
            $profiles = $this->getProfiles($items);
            $profileId = $this->getProfileId($profiles);
            $users = $this->getUsersByDateRange($profileId);
        } catch (Exception $e) {
            Log::error($e);
        }

        cache()->put($cacheKey, $users, now()->addDays(self::CACHE_USERS_DAYS));

        return $users;
    }
}