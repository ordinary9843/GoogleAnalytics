# GoogleAnalytics
使用 Google Analytics 服務取得網站訪客數量

### 備註
- php 7.2
- laravel 5.8
- 依賴 composer require google/apiclient:^2.0
- 申請 Google API 服務 https://console.developers.google.com/start/api?id=analytics&credential=client_key
- Google Anylytics API 文件：https://ga-dev-tools.appspot.com/dimensions-metrics-explorer/

### ga 權限取得流程
- 新增憑證 https://console.developers.google.com/flows/enableapi?apiid=analytics&credential=client_key
- 設定一組專屬電子信箱
- 前往 Google Analytics https://analytics.google.com/ 左側選單「管理」→「帳戶使用者管理」→ 新增電子信箱