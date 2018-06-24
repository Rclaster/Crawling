<?php
$csv = new CSV();

$csv->create_csv();


class CSV{
    private $server = "";
    private $username = "";
    private $password = "";
    private $database = "";
    //private $csv_file_path = ""; //csvファイルのパス
    private $csv_file_path = "hotpepper.csv";
    private $export_csv_title = array( "都道府県", "地区", "住所", "店名", "電話番号", "URL","アクセス", "営業時間", "定休日", "クレジットカード", "設備", "スタッフ数", "駐車場", "備考");
    private $dbconnect ;

    /**
     *
     * コンストラクタ関数
     * DB接続の確立と文字コードの設定を行う
     */
    function __construct()
    {
        $this->dbconnect = mysql_connect($this->server, $this->username, $this->password);
        if (!$this->dbconnect) die('接続失敗です。' . mysql_error());

        $db_selected = mysql_select_db($this->database, $this->dbconnect);
        if (!$db_selected) die('データベース選択失敗です。' . mysql_error());
        print('<p>接続に成功しました。</p>');

        // 時間制限を無効にする
        set_time_limit(0);

        // 文字コードの設定
        mb_internal_encoding("UTF-8");
        mysql_query('SET NAMES utf8', $this->dbconnect);
    }

    /**
     * 詳細情報まで取得済みの店舗情報を
     * CSVファイル形式に変換して生成するメソッド
     * 引数なし、戻り値なし
     *
     */
    public function create_csv(){
        $sql = "SELECT ";
        $sql .= "prefecturals, municipalityName, shopAddress, shopName, phoneNumber, url, shopAccess, businessHours, RegularHoliday, creditCard, facility, numberOfStaff, parkingLot, remarks ";
        $sql .= "FROM hotpepper_beauty ";
        $sql .= "WHERE attribute = 2";
        print($sql);
        if(!$this->dbconnect) {
            return;
        }
        $result = mysql_query($sql);
        if( touch($this->csv_file_path) ) {

            // オブジェクト生成
            $file = new SplFileObject($this->csv_file_path, "w");

            // タイトル行のエンコードをSJIS-winに変換（一部環境依存文字に対応用）
            foreach ($this->export_csv_title as $key => $val) {
                $export_header[] = mb_convert_encoding($val, 'SJIS-win', 'UTF-8');
            }

            // エンコードしたタイトル行を配列ごとCSVデータ化
            $file->fputcsv($export_header);

            while ($row_export = mysql_fetch_assoc($result)) {

                $export_arr = "";


                foreach ($row_export as $key => $val) {
                    // 内容行のエンコードをSJIS-winに変換（一部環境依存文字に対応用）
                    $export_arr[] = mb_convert_encoding($val, 'SJIS-win', 'UTF-8');
                }
                $file->fputcsv($export_arr);
            }

        }


        mysql_close($this->dbconnect);
    }
}
