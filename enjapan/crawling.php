<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>エンジャパン　クローリング</title>
</head>
<body>
<?php
/**
 * Created by PhpStorm.
 * Date: 2018/06/11
 * Time: 11:56
 */

date_default_timezone_set('Asia/Tokyo');

$get_pref = $_GET['map'];
$prefecturals = implode("_", $get_pref);

$crawler = new Crawler();

$crawler->crawling_method("https://employment.en-japan.com/wish/search_list/?topicsid=110&sort_wish&areaid={$prefecturals}");

class Crawler
{
    private $server = "127.0.0.1";
    private $username = "root";
    private $password = "";
    private $database = "work";
    private $logs_path = "/Users/hiroki/PhpstormProjects/php_workspace/work/enjapan/logs/enjapan_failed_log";    // エラーログファイルのパス
    private $crawling_logs = "/Users/hiroki/PhpstormProjects/php_workspace/work/enjapan/logs/enjapan_crawling_logs";
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
     * クローリング(会社詳細のURLを取得する)
     *
     * @param $url          募集一覧のURL
     * @param $search_name  検索勤務地
     */
    public function crawling_method($url) {
        $start = microtime(true);

        $str = "\n".date("Y/m/d H:i:s")."　第一フェーズのクローリングを開始しました（{$url}）\n";
        file_put_contents($this->logs_path, $str, FILE_APPEND);
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);

        $xml = $this->get_html_data($url);
        $strurl = "https://employment.en-japan.com/";
        $next_url = "https://employment.en-japan.com";
        if($xml->xpath("//div[2]/div[3]/div[2]/div[@class=\"list\"]")) {
            $lists = $xml->xpath("//div[2]/div[3]/div[2]/div[@class=\"list\"]");
            $next = $xml->xpath("//*[@id=\"jobSearchListNum\"]/div[2]/ul/li/a[text()=\"次へ\"]");

            //echo '<pre>';
            //print_r($lists[1]);
            //echo '</pre>';

            foreach ($lists as $data) {
                $craw_url = $strurl.(string)$data->div[1]->div->div[1]->a->attributes()->href;
                //$text = (string)$data->xpath('/html/body/div[2]/div[3]/div[2]/div[1]/div[2]/div/div[7]/a[2]/text()')[0];

                $this->get_letters($craw_url);
            }
            // 次へがあれば再帰
            if ($next) {
                //　次のペーシのURL
                $ex_url = $next_url.(string)$next[0]->attributes()->href;
                //$limit = "https://employment.en-japan.com/wish/search_list/?areaid=2&topicsid=110&indexNoWishArea=0&pagenum=4";

                //　limitをかける文字列と不一致の場合
                if(!preg_match("/pagenum=2/", $ex_url)) {
                    echo '--------------------------------------------------------------------------------------------------------------';
                    echo '<br>';
                    //　再帰
                    $this->crawling_method($ex_url);
                    //再帰前にsleep_time秒待つ
                    sleep($this->sleep_time);
                }
            }
        }
        $str = "\n" . date("Y/m/d H:i:s") . "　クローリングが完了しました（{$url}）\n";
        file_put_contents($this->crawling_logs, $str, FILE_APPEND);
        print("<p>クローリングが完了しました</p>");
        echo '<br>';

        $end = microtime(true);
        echo '処理時間：' . ($end - $start) . '秒';
        echo '<br>';
    }

    /**
     * 会社詳細のURLを受け取り、会社情報をDBに保存するメソッド
     * 引数は会社詳細URLと検索勤務地
     *
     * @param $url
     * @param $search_name
     */
    private function get_letters($url) {
        $xml = $this->get_html_data($url);

        if($xml) {
            $company_name = (string)$xml->xpath("//*[@class=\"descArticleUnit dataCompanyInfoSummary\"]/div[@class=\"title\"]/h2[text()=\"会社概要\"]/span/text()")[0];

            /**
             * 住所取得
             */
            $all_address = $xml->xpath("//*[text()=\"連絡先\"]/following-sibling::td/text()")[0];
            $dele = strpos($all_address, '〒');
            $address = substr($all_address, $dele);
            //$address = substr($sub_address, 0, strcspn($sub_address, '担当'));

            /**
             * TEL取得
             */
            //　div2つ目にTELがない場合
            if(!isset($xml->xpath("//*[@class=\"data\"]/div[2]/span[2]/text()")[0])){
                //　div1つ目にTELがある場合
                if(isset($xml->xpath("//*[@class=\"data\"]/div/span[2]/text()")[0])){
                    //　div1つ目のTELを取得
                    $tel = $xml->xpath("//*[@class=\"data\"]/div/span[2]/text()")[0];
                }
                //　TEL／以降のアドレスを取得
                else{
                    $pos = strpos($all_address, 'TEL／');
                    $tel = substr($all_address, $pos+6,12);
                }
            }
            //　div２つ目を取得
            else{
                $tel = $xml->xpath("//*[@class=\"data\"]/div[2]/span[2]/text()")[0];
            }

            print_r('会社名は：'.$company_name);
            echo '<br>';
            print_r('住所は：'.$address);
            echo '<br>';
            print_r('電話番号は：'.$tel);
            echo '<br>';
            echo '<br>';

            $sql = "
            INSERT IGNORE INTO enjapan
            (
            url,
            company_name,
            address,
            tel,
            InsertDateTime
            )
            VALUES
            ('{$url}',
            '{$company_name}',
            '{$address}',
            '{$tel}',
            now()
            )
            ";
            mysqli_query($this->link, $sql);

        }
    }

    /**
     * ページ情報を取得する処理
     *
     * @param $url
     * @return bool|SimpleXMLElement|string
     */
    private function get_html_data($url) {
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
}
?>
</body>
</html>



