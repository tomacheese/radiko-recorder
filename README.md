# radiko-recorder

radiko のタイムシフトを録音（ダウンロード）する。

PHPで書いてて微妙っちゃ微妙なので、TypeScriptか何かで書き直したい。

## Configuration

`docker-compose.yml` と同じディレクトリ の `radiko-recorder.env` が基本設定ファイル、`data/schedules.json` が録音するタイムシフトのスケジュールテーブルデータファイル。  
実行前にこの二つのファイルを作成・編集する必要がある。

### radiko-recorder.env

キーとバリューをイコールで繋ぐ env 形式で、いずれの型も String （Null 非許容）である。

- `DISCORD_TOKEN`: Discord Bot のトークン
- `DISCORD_CHANNEL_ID`: Discord の通知先チャンネル ID
- `PROXY_URL`: 録音対象の番組を視聴できるプロキシサーバのURL。 `example.com:1111` のようなURL形式で指定
- `PROXY_AUTH`: プロキシサーバの認証情報。`USERNAME:PASSWORD` で指定

## data/schedules.json

配列の中にオブジェクトを置く形で記述。

```jsonc
[
  {
    "title": "タイトル", // タイトルを指定。設定ファイル上で項目を認識するために利用し、それ以外の用途では使用されない
    "SID": "SID", // 対象番組のサービス ID (局 ID) を指定
    "dayOfWeek": "Wed,Fri", // 番組の放送曜日を指定。複数指定する場合はカンマ区切り
    "time": "00:00", // 番組開始時刻を指定。`hH:mm` で指定することを推奨
  }
]
```