<?php

/* 継ぎ接ぎ狂ったプログラム */

/* ========================================================= */
/* ========================================================= */

/* 設定 */

	//検索対象文字列
	$name = "";
	//タイムアウト時間を決めておく
	$TIMEOUT = 10; //10秒
	//開始時並列数
	$Thread = 200;

	/* 生成APIの設定 */

		/* キー設定(Twitter for Android Sign-Up)
		-------------------------------------------*/
		define( "consumer_key" , "RwYLhxGZpMqsWZENFVw" );
		define( "consumer_secret" , "Jk80YVGqc7Iz1IDEjCI6x3ExMSBnGjzBAH6qHcWJlo" );

		/* トークンの取得に使う既存のアカウント
		-------------------------------------------*/
		define( "screen_name_GetToken" , ""); //スクリーンネーム
		define( "password_GetToken" , ""); //パスワード

		/* 作成するアカウントの設定(*)
		-------------------------------------------*/
		define( "email" , "" );	//メールアドレス
		define( "password" , "" );	//パスワード
		define( "name" , "" );	//名前
		define( "lang" , "" );	//言語（任意）
		define( "time_zone" , "" );	//タイムゾーン(任意)

		/* APIの設定
		-------------------------------------------*/
		define( "endpoints" , "https://api.twitter.com/1/account/generate.json");	//エンドポイント


/* ========================================================= */
/* ========================================================= */



/* ライブラリ読み込み
-------------------------------------------*/
	require_once('UltimateOAuth.php');

/* 関数
-------------------------------------------*/
function account_Create( $name ){
		
	/* 作成するアカウントの設定(*)
	-------------------------------------------*/
		$screen_name = $name;	//スクリーンネーム
		
	/* APIの設定
	-------------------------------------------*/
		$method = "POST";	//メゾット
		$params = array( "screen_name" => $screen_name, "email" => email, "password" => password, "name" => name, "lang" => lang, "time_zone" => time_zone );	//パラメータ
		$wait_response = TRUE;	//レスポンス

	/* トークンの取得
	-------------------------------------------*/
		$uo = new UltimateOAuth(consumer_key, consumer_secret);
		$json1 = $uo->directGetToken( screen_name_GetToken, password_GetToken );

	/* アカウントの作成
	-------------------------------------------*/
		$json2 = $uo->OAuthRequest(endpoints, $method, $params, $wait_response);
		
	/* 結果をダンプ
	-------------------------------------------*/
		return var_dump( $json2 );
}

/* cURL_multi
-------------------------------------------*/

while( 1 ){

	// マルチ cURL ハンドルを作成します
	$mh = curl_multi_init();

	for( $i=0; $i<$Thread; $i++ ){
		// cURL リソースを作成します
		$ch[ $i ] = curl_init();

		// URL およびその他適切なオプションを設定します。
	   curl_setopt_array($ch[ $i ], array(
	        CURLOPT_URL            => "https://twitter.com/users/username_available?username={$name}",
	        CURLOPT_RETURNTRANSFER => true,
	        CURLOPT_TIMEOUT        => $TIMEOUT,
	        CURLOPT_CONNECTTIMEOUT => $TIMEOUT,
	    ));

		// ふたつのハンドルを追加します
		curl_multi_add_handle($mh,$ch[ $i ]);
	}

	do {
	    $stat = curl_multi_exec($mh, $running); //multiリクエストスタート
	} while ($stat === CURLM_CALL_MULTI_PERFORM);
	if ( ! $running || $stat !== CURLM_OK) {
	    throw new RuntimeException('リクエストが開始出来なかった');
	}

	do switch (curl_multi_select($mh, $TIMEOUT)) { //イベントが発生するまでブロック
	    // ->最悪$TIMEOUT秒待ち続ける。タイムアウトは全体で統一しておくと無駄がない

	    //case -1: //selectに失敗。通常は起きないはず…
	    case 0:  //タイムアウト -> 必要に応じてエラー処理に入るべき
	        continue 2; //ここではcontinueでリトライします。

	    default: //どれかが成功 or 失敗した
	        do {
	            $stat = curl_multi_exec($mh, $running); //ステータスを更新
	        } while ($stat === CURLM_CALL_MULTI_PERFORM);

	        do if ($raised = curl_multi_info_read($mh, $remains)) {
	            //変化のあったcurlハンドラを取得する
	            $info = curl_getinfo($raised['handle']);
	            echo "{$info['url']}: {$info['http_code']}\n";
	            $response = curl_multi_getcontent($raised['handle']);

	            if ($response === false) {
	                //エラー。404などが返ってきている
	            //    echo 'ERROR!!!', PHP_EOL;
	            } else {
	                //正常にレスポンス取得
	                $response = json_decode( $response );
	                echo $response->msg."\n";
	                if( $response->valid ){
	                	account_Create( $name ); //アカウントの生成
	                	exit;
	                }
	            }
	            curl_multi_remove_handle($mh, $raised['handle']);
	            curl_close($raised['handle']);
	        } while ($remains);
	        //select前に全ての処理が終わっていたりすると
	        //複数の結果が入っていることがあるのでループが必要

	} while ($running);

	curl_multi_close($mh);

}
