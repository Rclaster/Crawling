<?php
$crawler = new Crawler();

class Crawler
{
    private $server = "127.0.0.1";
    private $username = "root";
    private $password = "asdf";
    private $database = "crawling";

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

        // テーブルを更新
        $sql = "
			UPDATE	tabelog
			SET		ATTRIBUTE = 1
			WHERE TODOUFUKEN = 'master'
		";
        mysql_query($sql);

        print('<p>更新に成功しました。</p>');

        $close_flag = mysql_close($this->link);
        if ($close_flag){
            print('<p>データベースとの接続を切断しました。</p>');
        }
    }
}
?>
<!DOCTYPE html>
<html lang = “ja”>
<head>
    <meta charset = “UFT-8”>
    <title>マスターフラグのリセット</title>
</head>
<body>
<p><a href="crawling.html">戻る</a></p>
</body>
</html>
