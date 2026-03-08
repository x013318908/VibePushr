# VibePushr

[English README](README.en.md)

フォルダーをそのままサーバーにデプロイするためのツールです。

サーバーには `vp.php` を1ファイル置くだけ。
ブラウザからフォルダーをアップロードして、そのまま公開できます。

FTPより楽で、Gitデプロイより軽い。
AIで作ったコードを、その勢いのままサーバーに反映できます。

`vp.php` 単一ファイル配布を維持しつつ、開発作業を分離した構成です。

## 特徴

- 単一ファイル（`vp.php`）でデプロイ可能
- フォルダー単位で公開できるアップロード方式
- 転送サイズを抑える任意の gzip アップロード
- シンプルな認証と不正ログインガード
- PHPUnit / CI によるモダンな開発ワークフロー

## Directory Layout

- `public_html/vp.php`: 配布・運用する本体（単一ファイル）
- `docs/specs/`: 仕様書
- `dev/`: PHPUnit / Composer など開発専用ファイル
- `scripts/`: よく使う運用コマンド（PowerShell）

## Quick Commands (PowerShell)

- 構文チェック: `./scripts/lint.ps1`
- テスト実行: `./scripts/test.ps1`
- 依存監査: `./scripts/audit.ps1`
- 一括チェック: `./scripts/check.ps1`

## Optional: just

`just` がある場合は以下でも実行できます。

- `just lint`
- `just test`
- `just audit`
- `just check`

## CI

- GitHub Actions (`.github/workflows/ci.yml`) で `main` への push / PR 時に lint と PHPUnit を実行します。

## Development Notes

- 編集対象は基本 `public_html/vp.php`
- テストや依存更新は `dev/` 配下で実行されます

## Auth Note

- 初回起動時セットアップで管理パスワードを設定します。
- 設定されたハッシュは `public_html/.vp_auth.php` に保存され、以後はその値で認証します。
- ログイン失敗回数上限または長期間未使用でロックされた場合は、`public_html/.vp_login_guard.json` を FTP 等で削除すると復旧できます。

## License

- CC0 1.0 Universal
