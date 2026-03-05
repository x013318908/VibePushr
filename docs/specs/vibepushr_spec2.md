## VibePushr MVP 実装計画（単一 `vp.php` / 安定同期優先）

### Summary
- 目的は「共有ホスティング上で、単一PHPファイルを置くだけでフォルダー単位の同期を安全に実行できること」。
- 初期方針は以下で固定する。
  - 同期品質優先（UIは必要最小限）
  - 認証は `APP_PASSWORD_HASH` 固定定数
  - フォルダー一覧は「設置先基点の相対パス」フラット表示
- 1ファイル同期・リトライ・進捗可視化・gzip（効く場合のみ）・パス安全性をMVP完了条件とする。

### 実装範囲（In Scope）
1. 単一エントリ `vp.php` に以下を同居
- 画面HTML/CSS/JS
- `action` ベースのAPIルーティング
- 認証/CSRF/セッション
- 同期処理・進捗管理・ジョブログ

2. API実装
- `POST action=login`（`password_verify`）
- `POST action=logout`
- `GET action=list_dirs`
- `POST action=sync_init`
- `POST action=sync_put`
- `POST action=sync_finish`
- `GET action=progress&job_id=...`
- `POST action=stat`（任意要件として実装）

3. UI実装
- ログインフォーム
- フォルダー一覧テーブル（相対パス/再帰ファイル数/合計bytes）
- `webkitdirectory` の同期UI
- 進捗表示（総数、完了数、失敗数、現在パス、直近エラー）
- 失敗ファイルのみ再送ボタン

### 重要な公開I/F・型（固定仕様）
1. APIレスポンス形式（JSON）
- 共通: `ok: bool`
- エラー: `error: string`
- `list_dirs`: `dirs: [{ path: string, file_count: int, total_bytes: int }]`
- `sync_init`: `job_id: string`
- `sync_put`: `result: "ok" | "skip" | "fail"`, `message: string`
- `progress`: `progress: { total, done, ok, skip, fail, current_path, errors[] }`
- `sync_finish`: `summary: progressと同構造`

2. 同期送信仕様（クライアント→サーバー）
- 1リクエスト1ファイル
- `query/form` に `job_id`, `relpath`, `mtime`, `size`
- ボディは生バイト列
- 圧縮時は `X-Vibe-Encoding: gzip`、非圧縮は `identity`

3. サーバー内ジョブ状態
- `ROOT_DIR/.vp_jobs/{job_id}.state.json`（進捗）
- `ROOT_DIR/.vp_jobs/{job_id}.jsonl`（イベントログ）

### 実装詳細（決定済み）
1. セキュリティ
- `ROOT_DIR = __DIR__` 固定
- `normalize_relpath()` で以下拒否
  - 絶対パス
  - `..`
  - NULL byte
- `\` は `/` に正規化
- `resolve_safe_path()` 経由以外でファイルI/Oしない
- POST系は全て `X-CSRF-Token` 必須（`login` 含める）
- ログイン成功で `session_regenerate_id(true)`
- 画面出力は `htmlspecialchars`

2. 書き込み整合性
- 一時ファイル + `file_put_contents(..., LOCK_EX)` + `rename()` で原子的置換
- 親ディレクトリは `mkdir(..., true)` で自動作成
- `mtime` が渡された場合は `touch()` 反映

3. スキップ判定
- `sync_put` 内で既存ファイルが `size` と `mtime` 一致なら `skip`
- `stat` APIは将来の事前判定用として実装（MVP UIでは必須利用しない）

4. gzip方針（クライアント）
- 対象拡張子: `js css html json txt md svg php xml yml yaml ts tsx jsx`
- 最低サイズ: 8KB
- 圧縮後が元の90%未満なら gzip採用
- ブラウザが `CompressionStream` 非対応なら非圧縮で送信

5. 同期実行制御
- 同時送信数 3
- 最大リトライ 3回（指数バックオフなし、即時再試行）
- 失敗リストを保持し「失敗のみ再送」を可能にする

### テストケース / 受け入れ検証
1. 認証
- 正しいパスワードでログイン成功
- 誤パスワードで401
- 未ログインで `list_dirs/sync_*` が401

2. パス安全性
- `../`, `/abs/path`, `a\..\b`, NULL byte を全て拒否
- `ROOT_DIR` 外に書き込めないこと

3. フォルダー一覧
- 相対パスが設置先基点で出る
- 再帰ファイル数・合計サイズが正しい
- `.vp_jobs` が表示対象外

4. 同期基本
- 新規ファイル作成、既存上書きが可能
- 親ディレクトリ自動作成
- `sync_finish` 集計と実ファイル結果が一致

5. 進捗
- `done/total/current_path/errors` が更新される
- 失敗発生時にUIへ表示される

6. 圧縮
- gzip送信→サーバーで `gzdecode` 展開保存される
- 不正gzipで400エラー

7. リトライ
- 一時的失敗で再試行し成功する
- 3回失敗後は failed に残る
- failed のみ再送できる

### リスクと対策
- 大規模ディレクトリで `list_dirs` が重い
  - MVPでは許容。必要なら次段でキャッシュ/遅延計算を追加
- `CompressionStream` 非対応ブラウザ
  - 非圧縮フォールバックで機能維持
- 共有ホスティングの制限（`post_max_size` / `max_input_time`）
  - 1ファイル単位送信で影響を局所化。ドキュメントに制限値確認を明記

### Assumptions / Defaults
- PHP 7.4+ かつ `zlib` 有効
- `vp.php` 設置先を同期ルート（`ROOT_DIR`）とする
- パスワードハッシュは手動で `APP_PASSWORD_HASH` を更新して運用
- UIは日本語固定、モバイルでも最低限操作可能なレスポンシブ
