<?php
/**
 * Created by PhpStorm.
 * Date: 2018/06/11
 * Time: 16:50
 */
$csv = new CSV();

$csv->create_csv();


class CSV{
    private $server = "127.0.0.1";
    private $username = "root";
    private $password = "";
    private $database = "work";
    //private $csv_file_path = "C:\\xampp\csv\\hotpepper.csv"; //csvファイルのパス
    private $csv_file_path = "enjapan.csv";
    private $export_csv_title = array( "URL", "屋号", "住所", "TEL");
    private $dbconnect ;

    /**
     *
     * コンストラクタ関数
     * DB接続の確立と文字コードの設定を行う
     */
    function __construct()
    {
        $this->dbconnect = mysqli_connect($this->server, $this->username, $this->password);
        if (!$this->dbconnect) die('接続失敗です。' . mysqli_error($this->dbconnect));

        $db_selected = mysqli_select_db($this->dbconnect, $this->database);
        if (!$db_selected) die('データベース選択失敗です。' . mysqli_error($this->dbconnect));
        print('<p>接続に成功しました。</p>');

        // 時間制限を無効にする
        set_time_limit(0);

        // 文字コードの設定
        mb_internal_encoding("UTF-8");
        mysqli_query($this->dbconnect, 'SET NAMES utf8');
    }

    /**
     * 詳細情報まで取得済みの店舗情報を
     * CSVファイル形式に変換して生成するメソッド
     * 引数なし、戻り値なし
     *
     */
    public function create_csv(){
        $sql = "SELECT ";
        $sql .= "url, company_name, address, tel ";
        $sql .= "FROM enjapan ";
        print($sql);
        if(!$this->dbconnect) {
            return;
        }
        $result = mysqli_query($this->dbconnect, $sql);
        if( touch($this->csv_file_path) ) {

            // オブジェクト生成
            $file = new SplFileObject($this->csv_file_path, "w");

            // タイトル行のエンコードをSJIS-winに変換（一部環境依存文字に対応用）
            foreach ($this->export_csv_title as $key => $val) {
                $export_header[] = mb_convert_encoding($val, 'UTF-8', 'UTF-8');
            }

            // エンコードしたタイトル行を配列ごとCSVデータ化
            $file->fputcsv($export_header);

            while ($row_export = mysqli_fetch_assoc($result)) {

                $export_arr = "";


                foreach ($row_export as $key => $val) {
                    // 内容行のエンコードをSJIS-winに変換（一部環境依存文字に対応用）
                    $export_arr[] = mb_convert_encoding($val, 'UTF-8', 'UTF-8');
                }
                $file->fputcsv($export_arr);
            }

        }
        mysqli_close($this->dbconnect);
    }
}