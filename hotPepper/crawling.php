<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>クローリングTOP</title>
</head>
<body>
<?php
$range = $_GET['range'];
if($range == "tokyo"){
    $prefecturals = ["pre13"];
    $prefectiralsName = ["東京都"];
}
else if($range == "osaka"){
    $prefecturals = ["pre27"];
    $prefectiralsName = ["大阪府"];
}
else if($range == "tokyo_osaka"){
    $prefecturals = ["pre13","pre27"];
    $prefectiralsName = ["東京都","大阪府"];
}

$crawlType = $_GET['crawlType'];
$crawlType += 0;

$crawler = new Crawler();
if($crawlType == 1){
    for ($i = 0; $i < count($prefecturals); $i++) {
        $crawler->crawling_first_phase("https://beauty.hotpepper.jp/sl_seitai/{$prefecturals[$i]}/");
    }
}
else if ($crawlType == 2) {
    for ($i = 0; $i < count($prefecturals); $i++) {
        $crawler->crawling_second_phase("{$prefectiralsName[$i]}");
    }
}
else if ($crawlType == 3) {
    for ($i = 0; $i < count($prefecturals); $i++) {
        $crawler->crawling_first_phase("https://beauty.hotpepper.jp/sl_seitai/{$prefecturals[$i]}/");
        $crawler->crawling_second_phase("{$prefectiralsName[$i]}");
    }
}
class Crawler
{
    private $server = "";
    private $username = "";
    private $password = "";
    private $database = "";
    private $logs_path = "";    // エラーログファイルのパス
    private $crawling_logs = "";
    private $failed_num = 0;                                // 取得に連続で失敗した回数
    private $sleep_time = 3;                                // 再リクエストまでの間隔（秒）

    // コンストラクタ関数
    function __construct()
    {
        $this->link = mysqli_connect($this->server, $this->username, $this->password);
        if (!$this->link) die('接続失敗です。' . mysqli_error($this->link));

        $db_selected = mysqli_select_db($this->link, $this->database);
        if (!$db_selected) die('データベース選択失敗です。' . mysqli_error($this->link));
        print('<p>接続に成功しました。</p>');

        // 時間制限を無効にする
        set_time_limit(0);

        // 文字コードの設定
        mb_internal_encoding("UTF-8");
        mysqli_query($this->link, 'SET NAMES utf8');
    }

    /**
     * 第一フェイズのクローリングを開始するメソッド
     * 引数はクローリング対象のurl
     *
     * @param string $url 都道府県url
     *
     * @author s.fujimura
     */
    public function crawling_first_phase($url)
    {
        $str = "\n" . date("Y/m/d H:i:s") . "　第一フェーズのクローリングを開始しました（{$url}）\n";
        file_put_contents($this->logs_path, $str, FILE_APPEND);
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);


        //ループをまわすための変数
        $i = 0;

        $xml = $this->get_html_data($url);
        if (!$xml) return;
        $lists = $xml->xpath("//*[@id=\"mainContents\"]/div[@class=\"yS mainContentsTitleOuter mT20\"]/ul[@class=\"citiesSearch cFix\"]");
        while(true) {
            $flg = 0;
            foreach ($lists[$i]->li as $data) {
                //liタグ内にaタグがあるか判別する
                if ($data->a) {
                    $url = (string)$data->a->attributes()->href;
                    $area = (string)$data->a;

                    //url内の数字列のみ抽出

                    $num = preg_replace('/[^0-9]/', '', $url);
                    $get = substr($num,9,1);
                    /*
                     * citiesSearchHeadかcitiesSearchItemか判定する
                     * citiesSearchItemであればurlを取得
                     * citiesSearchHeadの場合下一桁が必ず1なので、下一桁のみ取得して判別
                     */
                    if ($get == 0) {
                        //市区町村の店舗一覧ページをget_lettersに渡す
                        $this->get_letters($url, $area ,"東京都");
                    }else
                    {
                        $flg = 1;
                    }
                }
            }
            print("<br>");
            if($flg == 0){
                break;
            }
            $i += 1;
        }
        $str = "\n" . date("Y/m/d H:i:s") . "　第一フェーズのクローリングが完了しました（{$url}）\n";
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);
        print("<p>第一フェイズのクローリングが完了しました</p>");
    }

    /**
     * 店舗一覧ページのURLを受け取り、店舗情報をDBに保存するメソッド
     * 引数は店舗一覧ページURLと市区名、都道府県名
     *
     * @param String $url 店舗一覧ページURL
     * @param String $area  市区名
     * @param String $prefecturals  都道府県名
     *
     * @author s.fujimura
     */
    private function get_letters($url, $area, $prefecturals) {
        $xml = $this->get_html_data($url);
        if($xml) {
            if ($xml->xpath("//*[@id=\"mainContents\"]/ul/li")) {
                $lists = $xml->xpath("//div[@id=\"mainContents\"]/ul/li");
                $next = $xml->xpath("//*[@id=\"mainContents\"]/div[2]/div[1]/div/p[2]/a[@class=\"iS arrowPagingR\"]");

                foreach ($lists as $data) {
                    $shopUrl = (string)$data->div->div->div->h3->a->attributes()->href;
                    $shopName = (string)$data->div->div->div->h3->a;


                    $sql = "
                    INSERT IGNORE INTO hotpepper_beauty
                    (
                    url,
                    prefecturals,
                    municipalityName,
                    shopName,
                    attribute,
                    InsertDateTime
                    )
                    VALUES
                    ('{$shopUrl}',
                    '{$prefecturals}',
                    '{$area}',
                    '{$shopName}',
                    '1',
                    now()
                    )
			    ";
                    mysqli_query($this->link, $sql);
                }

                // 次の２０件があれば再帰
                if ($next) {
                    $next_url = "https://beauty.hotpepper.jp" . (string)$next[0]->attributes()->href;
                    $this->get_letters($next_url, $area, $prefecturals);
                    //再帰前にsleep_time秒待つ
                    sleep($this->sleep_time);
                }

            }
        }
    }

    /* ここまで第一フェイズの処理
     *
     * ここから第二フェイズの処理
     */

    /**
     * 詳細未取得の店舗情報の詳細情報を取得するメソッド(第二フェイズ)
     * 都道府県を元に詳細を取得するレコードを抽出する
     * 引数は都道府県名
     *
     * @param $prefecturals String 都道府県名
     */
    public function crawling_second_phase($prefecturals) {
        $str = "\n".date("Y/m/d H:i:s")."　第二フェーズのクローリングを開始しました（{$prefecturals}）\n";
        file_put_contents($this->logs_path, $str, FILE_APPEND);
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);

        $sql = "
			SELECT	url
			FROM	`hotpepper_beauty`
			WHERE	attribute = 1
			AND		prefecturals = '{$prefecturals}'
		";
        $ret = $this->get_data($sql);
        foreach($ret as $data) {
            /*  if($i == 50) {
                  sleep($this->sleep_time);
                  $i = -1;
              }*/;
            $this->get_shops($data["url"]);
            //再帰前にsleep_time秒待つ
            sleep($this->sleep_time);
        }
        $str = "\n".date("Y/m/d H:i:s")."　第二フェーズのクローリングが完了しました（{$prefecturals}）\n";
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);
    }

    /**
     * SQL文を発行するメソッド
     * 引数はSQL文
     * 戻り値は実行結果(array)
     *
     * @param String $sql　SQL文
     * @return array
     * @author s.fujimura
     */
    private function get_data($sql) {
        $result = mysqli_query($this->link, $sql);
        if (!$result)
            die('クエリーが失敗しました。'.mysqli_error($this->link));

        $data = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * ページ情報を取得するメソッド
     * 引数はデータを取得したいURL
     * 戻り値は場合によって異なる
     * 1.取得に成功した場合は取得データ
     * 2.取得に失敗した場合はboolean型
     *
     * @param String $url 取得したいurl
     * @return bool|SimpleXMLElement|string
     */
    private function get_html_data($url) {

        // HTMLデータの取得
        /*
         * HTTPステータスコードが200番台(Sucseed)の時以外はエラーをログに保存する。
         */
        //コンテキストを作成
        $context = stream_context_create(
            [
                "http"=>
                    [
                        "ignore_errors"=>true
                    ]
            ]
        );

        $data = file_get_contents($url, false, $context);
        preg_match("/[0-9]{3}/", $http_response_header[0], $stcode);
        //ステータスコードによる分岐
        if((int)$stcode[0] >= 100 && (int)$stcode[0] <= 199){
            file_put_contents($this->crawling_logs, "Status Code : ".$stcode[0]. "\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Informational\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Object(".$url.")\n", FILE_APPEND);
        }else if((int)$stcode[0] >= 300 && (int)$stcode[0] <= 399){
            file_put_contents($this->crawling_logs, "Status Code : ".$stcode[0]. "\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Redirection\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Object(".$url.")\n", FILE_APPEND);
        }else if((int)$stcode[0] >= 400 && (int)$stcode[0] <= 499){
            file_put_contents($this->crawling_logs, "Status Code : ".$stcode[0]. "\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Client Error\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Object(".$url.")\n", FILE_APPEND);
            return false;
        }else if((int)$stcode[0] >= 500 && (int)$stcode[0] <= 599){
            file_put_contents($this->crawling_logs, "Status Code : ".$stcode[0]. "\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Server Error\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Object(".$url.")\n", FILE_APPEND);
            return false;
        }

        // 取得に成功した場合
        if ($data) {
            $this->failed_num = 0;
            $data = mb_convert_encoding($data, 'HTML-ENTITIES', 'auto');
            $data = @DOMDocument::loadHTML($data);
            $data = simplexml_import_dom($data);
            // 取得に失敗した場合
        } else {
            $this->failed_num++;
            $str = date("Y/m/d H:i:s")."　HTMLデータの取得に失敗 {$this->failed_num}回目（{$url}）\n";
            if (10 <= $this->failed_num) {
                $str .= "10回連続で失敗したのでプログラムを停止しました。\n";
                file_put_contents($this->logs_path, $str, FILE_APPEND);
                die("10回連続で失敗したのでプログラムを停止します。");
            } else {
                file_put_contents($this->logs_path, $str, FILE_APPEND);
                return false;
            }
        }
        return $data;
    }

    private function checkURL($url){
        $sql = "SELECT COUNT(todoufuken)
                        FROM tabelog 
                        WHERE tabelog_url = '{$url}' ";

        $get_count = $this->getSQL($sql);
        if($get_count == 0){
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * 電話番号リンクから電話番号を取得するためのメソッド
     * 引数は電話番号情報のあるurl
     * 戻り値は電話番号
     *
     * @param $url String 電話番号情報のあるurl
     * @return $phoneNumber String 電話番号
     * @author s.fujimura 2017/07/03
     */
    private function get_phoneNumber($url){

        // ページ情報の取得
        $xml = $this->get_html_data($url);
        if (!$xml) {
            return $phoneNumber = "";
        }

        $phoneNumber = $xml->xpath("//div[@id=\"contents\"]/div[@id=\"mainContents\"]/table/tr/th[contains( ./text() , \"電話番号\" )]/following-sibling::td/text()");
        if (empty($phoneNumber)) {
            $phoneNumber = "";
        } else {
            $phoneNumber =  trim( $phoneNumber[0], chr(0xC2).chr(0xA0) );
            $phoneNumber  = preg_replace("/( |　)/", "", $phoneNumber );
            $phoneNumber   = '"' . strip_tags($phoneNumber) . '"';
        }
        return $phoneNumber;
    }

    /**
     * 店舗の詳細情報をDBに挿入するメソッド
     * 引数は店舗URL
     *
     * @param String $url 店舗URL
     */
    private function get_shops($url)
    {
        // ページ情報の取得
        $xml = $this->get_html_data($url);
        if (!$xml) {
            return;
        }
        //電話番号
        $phoneNumber = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"電話番号\"]/following-sibling::td");

        if (empty($phoneNumber)) {
            $phoneNumber = "''";
        } else {
            $phoneNumber = (string)$phoneNumber[0]->a->attributes()->href;
            //URLを取得できたら電話番号を取得する
            $phoneNumber = $this -> get_phoneNumber($phoneNumber);
        }

        // 住所
        $shopAddr = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"住所\"]/following-sibling::td/text()");
        if (empty($shopAddr)) {
            $shopAddr = "''";
        } else {
            $shopAddr = trim( $shopAddr[0], chr(0xC2).chr(0xA0) );
            $shopAddr  = preg_replace("/( |　)/", "", $shopAddr );
            $shopAddr   = '"' . strip_tags($shopAddr) . '"';
        }

        // アクセス
        $shopAccess = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"アクセス・道案内\"]/following-sibling::td/text()");
        if (empty($shopAccess)) {
            $shopAccess = "''";
        } else {
            $shopAccess = trim( $shopAccess[0], chr(0xC2).chr(0xA0) );
            $shopAccess  = preg_replace("/( |　)/", "", $shopAccess );
            $shopAccess   = '"' . strip_tags($shopAccess) . '"';
        }

        // 営業時間
        $businessHour = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"営業時間\"]/following-sibling::td/text()");
        if (empty($businessHour)) {
            $businessHour = "''";
        } else {
            $businessHour = trim( $businessHour[0], chr(0xC2).chr(0xA0) );
            $businessHour  = preg_replace("/( |　)/", "", $businessHour );
            $businessHour = '"' . $businessHour . '"';
        }
        // 定休日
        $regularHoliday = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"定休日\"]/following-sibling::td/text()");
        if (empty($regularHoliday)) {
            $regularHoliday = "''";
        } else {
            $regularHoliday = trim( $regularHoliday[0], chr(0xC2).chr(0xA0) );
            $regularHoliday  = preg_replace("/( |　)/", "", $regularHoliday );
            $regularHoliday = '"' . $regularHoliday . '"';
        }

        // クレジットカード
        $creditCard = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"クレジットカード\"]/following-sibling::td/text()");
        if (empty($creditCard)) {
            $creditCard = "''";
        } else {
            $creditCard = trim( $creditCard[0], chr(0xC2).chr(0xA0) );
            $creditCard  = preg_replace("/( |　)/", "", $creditCard );
            $creditCard   = '"' . strip_tags($creditCard) . '"';
        }

        // 設備
        $facility = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"設備\"]/following-sibling::td/text()");
        if (empty($facility)){
            $facility = "''";
        }
        else {
            $facility = trim( $facility[0], chr(0xC2).chr(0xA0) );
            $facility  = preg_replace("/( |　)/", "", $facility );
            $facility   = '"' . strip_tags($facility) . '"';
        }

        // スタッフ数
        $numberOfStaff = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"スタッフ数\"]/following-sibling::td/text()");
        if (empty($numberOfStaff)){
            $numberOfStaff = "";
        }
        else {
            $numberOfStaff = trim( $numberOfStaff[0], chr(0xC2).chr(0xA0) );
            $numberOfStaff  = preg_replace("/( |　)/", "", $numberOfStaff );
            $numberOfStaff   = '"' . strip_tags($numberOfStaff) . '"';
        }

        // 駐車場
        $parkingLot = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"駐車場\"]/following-sibling::td/text()");
        if (empty($parkingLot)){
            $parkingLot = "";
        }
        else {
            $parkingLot = trim( $parkingLot[0], chr(0xC2).chr(0xA0) );
            $parkingLot = preg_replace("/( |　)/", "", $parkingLot );
            $parkingLot   = '"' . strip_tags($parkingLot) . '"';
        }

        //備考　備考はある場合とない場合や2行ある場合がある。
        $remarks = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"備考\"]/following-sibling::td/text()");
        $subRemarks = $xml->xpath("//div[@id=\"mainContents\"]/div[@class=\"mT30\"]/table/tbody/tr/th[text()=\"備考\"]/following-sibling::td/p");
        if (empty($remarks)){
            $remark = "''";
        }
        else {
            //pタグがあれば追加する
            if (empty($subRemarks)) {
                $subRemake = "";
            }else {
                $subRemake = "";
                foreach ($subRemarks as $data) {
                    $subRemake = $subRemake . (string)$data;
                }
            }
            //複数スペースをひとつにまとめる
            $remark = trim($remarks[0], chr(0xC2) . chr(0xA0));
            $remark = str_replace("/( |　)/", "\r\n", $remark);
            $remark = $remark . $subRemake;
            $remark = '"' . strip_tags($remark) . '"';

        }
        // テーブルを更新

        $sql = "
			UPDATE	hotpepper_beauty
			SET		
			        phoneNumber = {$phoneNumber},
					shopAddress = {$shopAddr},
					shopAccess = {$shopAccess},
					businessHours     = {$businessHour},
					RegularHoliday         = {$regularHoliday},
					creditCard        = {$creditCard},
					facility     = {$facility},
					numberOfStaff     = {$numberOfStaff},
					parkingLot     = {$parkingLot},
					remarks      = {$remark},
					attribute    = 2,
					UpdateDateTime = now()
			WHERE	url  = '{$url}'
		";

        mysqli_query($this->link, $sql);
    }
}
?>
</body>
</html>
