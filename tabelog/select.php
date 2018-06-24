<?php
$prefecturals = $_GET['prefecturals'];
$phase = $_GET['phase'];
$phase += 0;

$crawler = new Crawler();


$crawler->select($phase,$prefecturals);

class Crawler
{
    private $server = "";
    private $username = "";
    private $password = "";
    private $database = "";



    // コンストラクタ関数
    function __construct()
    {
        $this->link = mysql_connect($this->server, $this->username, $this->password);
        if (!$this->link) die('接続失敗です。' . mysql_error());

        $db_selected = mysql_select_db($this->database, $this->link);
        if (!$db_selected) die('データベース選択失敗です。' . mysql_error());

        print('<p>接続に成功しました。</p>');

        // 文字コードの設定
        mb_internal_encoding("UTF-8");
        mysql_query('SET NAMES utf8', $this->link);

        // 文字コードの設定
        mb_internal_encoding("UTF-8");
        mysql_query('SET NAMES utf8', $this->link);
    }


    public function select($phase, $prefecturals){
            $sql = "";
            if($phase == 1){
                /**
                 * phase1のデータ取得
                 */
                $sql =
                    "SELECT COUNT(todoufuken) 
                     FROM tabelog
                     WHERE todoufuken = '{$prefecturals}'
                     AND attribute = 0";
            }

            if($phase == 2){
                /**
                 * phase2のデータ取得
                 */
                $sql = "SELECT COUNT(todoufuken) 
                        FROM tabelog 
                        WHERE todoufuken = '{$prefecturals}' 
                        AND attribute = 1";
            }
            $get_count = $this->getSQL($sql);
        echo $prefecturals,':フェイズ',$phase,'の終了件数は',$get_count,'件です';
    }

    public function getSQL($sql){
        $result = mysql_query($sql);
        if (!$result)
        {
            die('クエリーが失敗しました。' . mysql_error());
        }

        $row = mysql_fetch_assoc($result);
        return $row['COUNT(todoufuken)'];
    }

    function __destruct() {
        $close_flag = mysql_close($this->link);
        if ($close_flag){
            print('<p>データベースとの接続を切断しました。</p>');
        }
    }
}
?>
