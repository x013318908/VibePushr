# VibePushr

`vp.php` 単一ファイル配布を維持しつつ、開発作業を分離した構成です。

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

## Development Notes

- 編集対象は基本 `public_html/vp.php`
- テストや依存更新は `dev/` 配下で実行されます

## Auth Note

- 配布時は `APP_PASSWORD_HASH` をプレースホルダーにしておき、利用者が本番前に差し替える運用です。
- 将来は初回起動時セットアップでパスワード設定できるようにする計画です。

## License

- CC0 1.0 Universal
