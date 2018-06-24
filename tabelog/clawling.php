<?php
date_default_timezone_set('Asia/Tokyo');
$range = $_GET['range'];

$crawlType = $_GET['crawlType'];
$crawlType += 0;

$nationwide = ['hokkaido' ,'aomori' ,'akita', 'yamagata', 'iwate', 'miyagi', 'fukushima', 'tokyo', 'kanagawa', 'saitama', 'chiba', 'tochigi', 'ibaraki', 'gunma', 'aichi', 'gifu', 'shizuoka', 'mie', 'niigata', 'yamanashi', 'nagano', 'ishikawa', 'toyama', 'fukui', 'osaka', 'hyogo', 'kyoto', 'shiga', 'nara', 'wakayama', 'okayama', 'hiroshima', 'tottori', 'shimane', 'yamaguchi', 'kagawa', 'tokushima', 'ehime', 'kochi', 'fukuoka', 'saga', 'nagasaki', 'kumamoto', 'ooita', 'miyazaki', 'kagoshima', 'okinawa' ];

$majorCities = ['tokyo','oosaka','aichi',"fukuoka","hokkaido"];

$kinkiKanto = ['osaka', 'hyogo', 'kyoto', 'shiga', 'nara', 'wakayama', 'tokyo', 'kanagawa', 'saitama', 'chiba', 'tochigi', 'ibaraki', 'gunma'];

$prefectural = "";


if($range == 'majorCities'){
    $prefectural = $majorCities;
}
if($range == 'kinkiKanto'){
    $prefectural = $kinkiKanto;
}
else{
    $prefectural = $nationwide;
}



// クローラーインスタンスの生成
$crawler = new Crawler();

if($range == "majorCities" || $range == "kinkiKanto") {
    for ($i = 0; $i < count($prefectural); $i++) {
        if($crawlType == 1){
            $crawler->crawling_first_phase("https://tabelog.com/sitemap/{$prefectural[$i]}/");
        }
        else if ($crawlType == 2) {
            $crawler->crawling_second_phase("{$prefectural[$i]}");
        }
        else if ($crawlType == 3){
            $crawler->crawling_first_phase("https://tabelog.com/sitemap/{$prefectural[$i]}/");
            $crawler->crawling_second_phase("{$prefectural[$i]}");
        }
    }
}
else{
    $range += 0;
    for ($i = $range; $i < count($prefectural); $i++) {
        if($crawlType == 1){
            $crawler->crawling_first_phase("https://tabelog.com/sitemap/{$prefectural[$i]}/");
        }
        else if ($crawlType == 2) {
            $crawler->crawling_second_phase("{$prefectural[$i]}");
        }
        else if ($crawlType == 3){
            $crawler->crawling_first_phase("https://tabelog.com/sitemap/{$prefectural[$i]}/");
            $crawler->crawling_second_phase("{$prefectural[$i]}");
        }
    }
}


class Crawler {
    private $server     = "";
    private $username   = "";
    private $password   = "";
    private $database   = "";
    private $logs_path  = "";	// エラーログファイルのパス
    private $crawling_logs = "";
    private $failed_num = 0;								// 取得に連続で失敗した回数
    private $sleep_time = 10;								// 再リクエストまでの間隔（秒）
    private $sleep_program = 600;                       //再起をかけるためのタイマー

    // コンストラクタ関数
    function __construct() {
        $this->link = mysql_connect($this->server, $this->username, $this->password);
        if (!$this->link) die('接続失敗です。'.mysql_error());

        $db_selected = mysql_select_db($this->database, $this->link);
        if (!$db_selected) die('データベース選択失敗です。'.mysql_error());

        print('<p>接続に成功しました。</p>');

        // 時間制限を無効にする
        set_time_limit(0);

        // 文字コードの設定
        mb_internal_encoding("UTF-8");
        mysql_query('SET NAMES utf8', $this->link);
    }


    // 第一フェーズのクローリング（店舗URLを取得する）
    public function crawling_first_phase($url) {
        $str = "\n".date("Y/m/d H:i:s")."　第一フェーズのクローリングを開始しました（{$url}）\n";
        file_put_contents($this->logs_path, $str, FILE_APPEND);
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);

        $xml = $this->get_html_data($url);
        if($xml) {
            $lists = $xml->xpath("//div[contains(@class,'area')]");

            foreach ($lists[1]->ul->li as $data) {
                $url = (string)$data->a->attributes()->href;
                $area = (string)$data->a;

                $this->get_letters($url, $area);
            }
            $str = "\n" . date("Y/m/d H:i:s") . "　第一フェーズのクローリングが完了しました（{$url}）\n";
            file_put_contents($this->crawling_logs, $str, FILE_APPEND);
        }
    }


    // 第二フェーズのクローリング（詳細未取得の店舗URLを取得し、それぞれの詳細を取得する）
    public function crawling_second_phase($todoufuken) {
        $str = "\n".date("Y/m/d H:i:s")."　第二フェーズのクローリングを開始しました（{$todoufuken}）\n";
        file_put_contents($this->logs_path, $str, FILE_APPEND);
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);

        $sql = "
			SELECT	tabelog_url
			FROM	`tabelog`
			WHERE	attribute = 0
			AND		todoufuken = '{$todoufuken}'
		";
        $ret = $this->get_data($sql);

        foreach($ret as $data) {
            $this->get_restaurant($data["tabelog_url"]);
        }
        $str = "\n".date("Y/m/d H:i:s")."　第二フェーズのクローリングが完了しました（{$todoufuken}）\n";
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);
    }


    
    private function get_data($sql) {
        $result = mysql_query($sql);
        if (!$result) die('クエリーが失敗しました。'.mysql_error());

        $data = array();
        while ($row = mysql_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }


    // ページ情報を取得する処理
    private function get_html_data($url) {

        /*
         * 食べログへの接続はアクセス数の多い時間を避けるため
         * 10時から14時、16時から20時の間はクローリングプログラムを停止する。
         * 10分ごとに時間を確認して上記の時間から外れていた場合はクローリングを再開する。
         */
        $time = intval(date('H'));
        while((10 <= $time && $time <= 14)||(16 <= $time && $time <= 20)){
            sleep($this->sleep_program);
        }

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
            return false;
        }else if((int)$stcode[0] >= 300 && (int)$stcode[0] <= 399){
            file_put_contents($this->crawling_logs, "Status Code : ".$stcode[0]. "\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Redirection\n", FILE_APPEND);
            file_put_contents($this->crawling_logs, "Object(".$url.")\n", FILE_APPEND);
            return false;
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
            if ($data) {
                $this->failed_num = 0;
                $data = mb_convert_encoding($data, 'HTML-ENTITIES', 'auto');
                $data = @DOMDocument::loadHTML($data);
                $data = simplexml_import_dom($data);
                // 取得に失敗した場合
            } else {
                $this->failed_num++;
                $str = date("Y/m/d H:i:s") . "　HTMLデータの取得に失敗 {$this->failed_num}回目（{$url}）\n";
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


    // 50音一覧ページのURLを受け取り、店舗一覧ページのURLリストを取得する処理
    private function get_letters($url, $area) {

        $xml = $this->get_html_data($url);
        if($xml) {
            $lists = $xml->xpath("//div[contains(@class,'taglist')]");

            foreach ($lists[0]->ul->li as $data2) {
                $url = $data2->a->attributes()->href;
                $first_letter = $data2->a;
                $first_letter = explode("(", (string)$first_letter);

                $this->get_restaurants((string)$url, $area, $first_letter[0]);
            }
        }
    }


    // 店舗一覧ページのURLを受け取り、店舗のURLリストを取得する処理
    private function get_restaurants($url, $area, $first_letter) {


        $xml   = $this->get_html_data($url);
        if($xml){

            if($xml->xpath("//div[contains(@class,'rstname')]")) {
                $lists = $xml->xpath("//div[contains(@class,'rstname')]");
                $next = $xml->xpath("//descendant::a[attribute::rel='next']");

                foreach ($lists as $data) {
                    $rst_name = (string)$data->a;
                    $url = (string)$data->a->attributes()->href;
                    $url_pieces = explode("/", $url);
                    $url = "https://tabelog.com" . $url;
                    $time = date('Y/m/d H:i:s');
                    $rst_name = str_replace("'", "\'", $rst_name);
                    $rst_name = str_replace('"', '\"', $rst_name);

                    $checkurl = $this->checkURL($url);

                    if (!$checkurl) {
                        $sql = "
                    INSERT INTO tabelog
                    VALUES (
                        '{$url_pieces[1]}',
                        '{$area}',
                        '{$first_letter}',
                        '{$url}',
                        '{$rst_name}',
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        0,
                        '{$time}',
                        ''
                    )
                    ON DUPLICATE KEY UPDATE
                        modified = '{$time}'
                ";
                        mysql_query($sql);
                    }
                }


                // 次の２００件があれば再帰
                if ($next) {
                    $next_url = "https://tabelog.com" . (string)$next[0]->attributes()->href;
                    $this->get_restaurants($next_url, $area, $first_letter);
                    //再帰前にsleep_time秒待つ
                    sleep($this->sleep_time);
                }
            }
        }
    }

    //食べログURLの重複があるか確認する関数
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

    //カウントを取ってくるための関数
    public function getSQL($sql){
        $result = mysql_query($sql);
        if (!$result)
        {
            die('クエリーが失敗しました。' . mysql_error());
        }

        $row = mysql_fetch_assoc($result);
        return $row['COUNT(todoufuken)'];
    }

    // 文字列に修正を加える関数
    private function string_processing($str) {

        // コロンを閉じコロンと間違えないようにする
        $str = str_replace("'", "\'", $str);
        $str = str_replace('"', '\"', $str);
        // 特殊記号の&amp;を&に置換する
        $str = str_replace("&amp;", "&", $str);
        // 記号をShift-JISで表現可能な文字コードに変換する
        $str = str_replace("?", "～", $str);

        return $str;
    }


    // 店舗URLを受け取り、詳細情報を取得する処理
    private function get_restaurant($tabelog_url) {

        // ページ情報の取得
        $xml = $this->get_html_data($tabelog_url);
        if (!$xml){
            return;
        }

        // 更新日時
        $time = date('Y/m/d H:i:s');

        // 店名
        $rst_name = $xml->xpath("//*[text()='店名']/following-sibling::td");
        if (empty($rst_name)) {
            $rst_name = "";
        } else {
            $rst_name = $rst_name[0]->asXML();
            $rst_name = $this->string_processing($rst_name);
            $rst_name = "rst_name = '" . strip_tags($rst_name) . "',";
        }

        // ジャンル
        $jenre = $xml->xpath("//*[@id=\"contents-rstdata\"]/div[3]/table[1]/tbody/tr[2]/td/span");
        if(!$jenre)
            $jenre = $xml->xpath("//*[@id=\"contents-rstdata\"]/div[2]/table[1]/tbody/tr[2]/td/span");
        if (empty($jenre)) {
            $jenre = "NULL";
        } else {
            $jenre   = $jenre[0]->asXML();
            $pettern = array("/\n/", "/ /");
            $jenre   = preg_replace($pettern, "", $jenre);
            $jenre   = $this->string_processing($jenre);
            $jenre   = "'" . strip_tags($jenre) . "'";
        }
        // 電話番号
        $phone_number = $xml->xpath("//*[@id=\"contents-rstdata\"]/div[3]/table[1]/tbody/tr[3]/td/p/strong");
        if(!$phone_number)
            $phone_number = $xml->xpath("//*[@id=\"contents-rstdata\"]/div[2]/table[1]/tbody/tr[3]/td/p/strong");
        if (!$phone_number) {
            // 電話番号が無ければ閉店しているのでフラグを変更して終了

            $sql = "
				UPDATE	tabelog
				SET		attribute    = 10,
						modified     = '{$time}'
				WHERE	tabelog_url  = '{$tabelog_url}'
			";
            mysql_query($sql);
            return;

        }

        $phone_number = $phone_number[0];
        // タグを取り除く
        $phone_number = strip_tags($phone_number);
        // 半角スペースを取り除く
        $phone_number = str_replace(" ", "", $phone_number);

        // 共通の文句を省いて必要な文字列だけを残す
        $tmp_array = array();
        foreach(explode("\n", $phone_number) as $val) {
            if ($val === '') continue;
            if (strpos($val, "※お問い合わせの") !== false) continue;
            if (strpos($val, "空席・ネット予約") !== false) break;
            $tmp_array[] = $val;
        }

        // 配列を繋げて文字列にする
        $phone_number = implode("\n", $tmp_array);
        $phone_number = $this->string_processing($phone_number);
        $phone_number = "'" . $phone_number . "'";

        // 住所
        $address = $xml->xpath("//*[text()='住所']/following-sibling::td/p");
        if (empty($address)) {
            $address = "NULL";
        } else {
            $address = strip_tags($address[0]->asXML());
            $address = $this->string_processing($address);
            $address = "'" . $address . "'";
        }

        // 営業時間
        $open_xml = $xml->xpath("//*[text()='営業時間']/following-sibling::td/p");
        $open = "";
        if (empty($open_xml)) {
            $open = "NULL";
        } else {
            // Pタグが複数の場合があるためforeach
            foreach ($open_xml as $data) {
                $str = $data->asXML();

                // <br>タグと<p>タグを\nに置換
                $pettern = array("/<br>/", "/<br*\/>/", "/<p>/", "/<\/p>/");
                $str = preg_replace($pettern, "\n", $str);
                // 末尾の改行文字を取り除く
                $str = rtrim($str);
                // タグを取り除く
                $open .= strip_tags($str);
            }
            // 先頭と末尾の改行文字を取り除く
            $open = trim($open);
            $open = $this->string_processing($open);
            $open = "'" . $open . "'";
        }

        // 座席数
        $seats = $xml->xpath("//*[text()='席数']/following-sibling::td/p");
        if (empty($seats)) {
            $seats = "NULL";
        } else {
            $seats = $seats[0]->asXML();
            // 改行文字とスペースとタグを削除
            $pettern = array("/\n/", "/ /");
            $seats   = preg_replace($pettern, "", $seats);
            $seats   = $this->string_processing($seats);
            $seats   = "'" . strip_tags($seats) . "'";
        }

        // 店舗ページURL
        $homepage = $xml->xpath("//*[@class='homepage']/a");
        if (empty($homepage))	$homepage = "NULL";
        else					$homepage = "'".(string)$homepage[0]->attributes()->href."'";

        // アクセス数
        /*
        $access_data = $xml->xpath("//descendant::div[@class='access']");
        $access1 = (string)$access_data[0]->div->ul->li[0]->em;	// 先週
        $access2 = (string)$access_data[0]->div->ul->li[1]->em;	// 先々週
        $access3 = (string)$access_data[0]->p->em;				// 累計
        $access1 = str_replace(",", "", $access1);
        $access2 = str_replace(",", "", $access2);
        $access3 = str_replace(",", "", $access3);
        */
        // 公式情報アイコンがなければ非会員
        if (empty($xml->xpath("//*[@class='owner-badge']"))) {
            $plan = "非会員";

            // 公式情報アイコンがあれば会員(無料／有料)
        } else {
            // メイン写真を出せるのは有料会員
            $mainphotos = $xml->xpath("//*[@id='contents-mainphotos']");
            if (empty($mainphotos))	$plan = "無料会員";
            else					$plan = "ライトプラン以上";

            // 予約機能を使えるのはベーシックプラン以上
            $yoyaku = $xml->xpath("//a[text()='予約する']");
            if (!empty($yoyaku))	$plan = "ベーシックプラン以上";
        }

        // テーブルを更新
        $sql = "
			UPDATE	tabelog
			SET		{$rst_name}
					jenre        = {$jenre},
					phone_number = {$phone_number},
					address      = {$address},
					open         = {$open},
					seats        = {$seats},
					homepage     = {$homepage},
					plan         = '{$plan}',
					attribute    = 1,
					modified     = '{$time}'
			WHERE	tabelog_url  = '{$tabelog_url}'
		";

        mysql_query($sql);
    }


    // デストラクタ関数
    function __destruct() {
        $close_flag = mysql_close($this->link);
        if ($close_flag){
            print('<p>データベースとの接続を切断しました。</p>');
        }
    }
}
