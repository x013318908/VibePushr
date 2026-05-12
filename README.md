# VibePushr

[English README](README.en.md)

フォルダーをそのままサーバーにデプロイするためのツールです。

[XREA](https://www.xrea.com/)などの無料サーバーに `vp.php` を1ファイル置くだけ。
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

## 想定ユーザーと前提環境

- PHP 8.2 以上が動作するサーバーで使う想定です。
- FTP やファイルマネージャーで `vp.php` を1ファイル配置できることが前提です。
- XREA のような共有ホスティングで、軽く素早く公開したいケースに向いています。
- 配布物は `public_html/vp.php` のみです。`dev/` 配下は開発専用です。

## 導入

1. GitHub Releases から最新版の `vp.php` を取得します。
2. サーバー上の公開ディレクトリに `vp.php` をアップロードします。
3. 公開 URL をブラウザで開きます。
4. 初回セットアップ画面で管理パスワードを設定します。

## 更新

1. GitHub Releases から最新版の `vp.php` を取得します。
2. 既存の `vp.php` を新しいファイルで置き換えます。
3. ブラウザで開き、ログイン・dry-run・同期の基本動作を確認します。

## セキュリティ運用の推奨

- 配置時は `vp.php` を、運用者が覚えやすく十分に長い任意名へ変更することを推奨します（例: `foruda-appuro-do.php` / `appu-sagyo-iriguchi.php`）。
- ファイル名を変更しても、VibePushr のログイン・同期・dry-run はそのまま動作します。
- 脆弱性報告や運用上の注意は [SECURITY.md](SECURITY.md) を参照してください。

## おすすめのアップロード運用

フォルダーには、開発用ファイル、API キー、AI エージェント用メモ、未公開の下書きなど、サーバーへアップロードしてはいけないものが混ざることがあります。
そのため、作業フォルダーをそのまま選ぶのではなく、公開してよいファイルだけを集めた「リリース用フォルダー」を別に作ってから VibePushr でアップロードする運用をおすすめします。

1. AI エージェントに「このフォルダーを公開したいので、アップロードしてよいファイルだけを含むリリース用フォルダーを作って」と依頼します。
2. 生成されたリリース用フォルダーの中身を確認し、秘密情報や不要ファイルが含まれていないことをチェックします。
3. VibePushr では、そのリリース用フォルダーを選んで dry-run してからアップロードします。

このリポジトリ自身の開発では必須手順にしていませんが、VibePushr の使い方としては安全で分かりやすい運用です。

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
- E2E スモーク: `cd dev; npm run test:e2e:smoke`
- E2E 読み取りエラー回復: `cd dev; npm run test:e2e:client-read-error`

## CI

- GitHub Actions (`.github/workflows/ci.yml`) で `main` への push / PR 時に lint と PHPUnit を実行します。
- Playwright E2E は正式リリース前の手動確認項目として扱います。

## Development Notes

- 編集対象は基本 `public_html/vp.php`
- テストや依存更新は `dev/` 配下で実行されます
- 正式リリース前の確認手順は [docs/release-checklist.md](docs/release-checklist.md) を参照してください。
- 変更履歴は [CHANGELOG.md](CHANGELOG.md) に記録します。

## Auth Note

- 初回起動時セットアップで管理パスワードを設定します。
- 設定されたハッシュは `public_html/.vp_data/.vp_auth.php` に保存され、以後はその値で認証します。
- ログイン失敗回数上限または長期間未使用でロックされた場合は、`public_html/.vp_data/.vp_login_guard.json` を FTP 等で削除すると復旧できます。
- `.vp_data` 配下には認証・ログインガードなどの運用データが保存されます。

## License

- CC0 1.0 Universal
