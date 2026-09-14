# ダイバージェンスメーター風時計

シュタインズ・ゲートのダイバージェンスメーターを模したウェブ時計です。  
`BO NX` フォントを3枚以上重ねてニキシー管風に表示し、読み込み時は各桁が高速回転してから左から順に停止します。

動作確認例: `https://kinokuni.ac/1048599/`

## 必要なファイル

```
公開ディレクトリ/
  index.php          … 本体（このファイルを開けば表示される）
  ntp.php            … NTP時刻取得
  README.md
  font/              … 必須。BO NX フォントを置く
    BONX-Frame.otf
    BONX-Medium.otf
    BONX-Silhouette.otf
    BONX-TubeMedium.otf
    BONX-TubeMediumReverse.otf
```

`font/` が無い、ファイル名が違う、または大文字小文字が違うと、数字は出ても管の形になりません。  
フォントは HTML と同じ階層の **`font/`**（例: `/1048599/font/`）です。サイト直下の `/font/` ではありません。

任意:

```
    BONX-TubeBold.otf
    BONX-TubeBoldReverse.otf
```

出典は『STEINS;GATE VISUALWORKS 1.5 + Nixie FONT KIT』付属の BO NX ファミリーです。

## 重ね順（PDF準拠）

表示は下から次の順です。

1. `BO NX Silhouette` … 消灯している陰極
2. `BO NX Tube Medium Reverse` … 管の裏打ち
3. `BO NX Medium` … 点灯数字
4. `BO NX Tube Medium` … ニキシー管のガラス
5. `BO NX Frame` … 管の枠

小数点は PDF どおり中黒 `•`（bullet）です。通常のピリオド `.` は使いません。  
行送りはフォントサイズの 1.7 倍です。表示はウィンドウに合わせて拡大縮小します。

## 動かし方

PHP が使えるサーバに置いてください。ブラウザからディレクトリを開けば `index.php` が使われます。

```bash
php -S localhost:8080
```

その後 `http://localhost:8080/` を開きます。  
`file://` で HTML だけ開くとフォントは読めますが、NTP は動きません。

## 画面下の操作

- 時計 0•時分秒
- 時計 時•分•秒
- 世界線変動率（毎分ランダム）
- NTP再同期
- 再回転

時刻は JS が NTP ホストを乱択し、`ntp.php` が UDP/123 で取得します。失敗時は PHP サーバ時刻、それも失敗するとブラウザ時刻です。  
候補: `ntp.nict.jp` / `ntp.jst.mfeed.ad.jp` / `jp.pool.ntp.org` / `time.google.com` / `time.cloudflare.com`

## うまく出ないとき

- 配置が `.../font/BONX-Medium.otf` になっているか確認する（`fonts/` やファイル名違いだと読まない）
- ブラウザの開発者ツール → Network で `.otf` が 200 か見る
- キャッシュが残っている場合は強制再読み込みする
- サーバが `.otf` を `font/otf` として返すか確認する
