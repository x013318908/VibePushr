# VibePushr 仕様書（統合版）

## 1. 目的 / コンセプト

バイブコーディングの成果物を、共有ホスティング上で素早く公開するための
単一 `vp.php` ベース同期ツール。

- UIはフォルダー中心（ファイル一覧編集はしない）
- 転送はファイル単位（失敗時の再送が可能）
- 必要な場合のみクライアント側でgzip圧縮
- サーバー側は安全な相対パス検証を経て `ROOT_DIR` 配下に保存

## 2. スコープ

### 2.1 In Scope
- ログイン/ログアウト（セッション）
- フォルダー一覧（相対パス、再帰ファイル数、合計bytes）
- 同期ジョブ（初期化、ファイル送信、進捗、終了）
- 失敗のみ再送
- テスト実行（dry-run、書き込みなし）

### 2.2 Out of Scope
- ファイル編集、リネーム、削除
- アーカイブ展開による一括配置
- 巨大ファイルのチャンク再開

## 3. 環境 / 配置

- 配布形態：`public_html/vp.php` 単一ファイル
- 実行環境：PHP 7.4+（`zlib` 利用）
- 同期先ルート：`ROOT_DIR`（`vp.php` 設置ディレクトリ）

## 4. 認証仕様

- 初回起動時にセットアップ画面を表示し、管理パスワードを設定
- パスワードハッシュは `public_html/.vp_auth.php` に保存
- 認証は `.vp_auth.php` のみ参照（`vp.php` 固定ハッシュは持たない）
- ログイン成功時 `session_regenerate_id(true)`
- 失敗回数上限と長期未使用でロック
  - `LOGIN_MAX_FAILED_ATTEMPTS = 100`
  - `LOGIN_MAX_IDLE_DAYS = 30`
  - ガード状態は `public_html/.vp_login_guard.json`
  - 復旧は `.vp_login_guard.json` を削除

## 5. 同期仕様

### 5.1 クライアント送信
- 1リクエスト1ファイル
- `fetch` で `action=sync_put` へ送信
- パラメータ：`job_id`, `relpath`, `size`, `mtime`, `dry_run`, `force`
- 圧縮時は `X-Vibe-Encoding: gzip`（非圧縮は `identity`）

### 5.2 サーバー処理
- `relpath` を正規化し、`ROOT_DIR` 外を拒否
- 既存ファイルが `size + mtime` 一致時は `skip`
- 通常同期は保存（上書き）
- dry-run は書き込みを行わない
- dry-run はディレクトリも作成しない

### 5.3 結果と進捗
- `sync_put` 応答：`status = ok | skip | fail` と `message`
- 進捗：`total`, `done`, `ok`, `skip`, `fail`, `current_path`, `errors[]`
- UIログに各ファイル結果と最終集計を表示

## 6. API 一覧（action）

- `POST action=login`
- `POST action=logout`
- `GET action=list_dirs`
- `POST action=sync_init`
- `POST action=sync_put`
- `POST action=sync_finish`
- `GET action=progress&job_id=...`
- `POST action=stat`（高速スキップ判定の補助）

## 7. セキュリティ / 安全性

- パス検証（絶対パス、`..`、NULL byte を拒否）
- 操作対象を `ROOT_DIR` 配下に限定
- POST系はCSRF保護
- 画面出力は `htmlspecialchars` でエスケープ
- エラーメッセージに内部絶対パスを出さない

## 8. 受け入れ基準

1. `vp.php` 単一ファイルで運用できる
2. `ROOT_DIR` 外へ読み書きできない
3. フォルダー一覧に `path / file_count / total_bytes` が出る
4. 同期開始でファイル単位アップロードができる
5. テスト実行（dry-run）は書き込みもディレクトリ作成もしない
6. gzip送信時にサーバー側で展開保存できる
7. 失敗のみ再送が機能する
